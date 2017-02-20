<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

use Hashids\Hashids;

use App\Mail\Locked as Locked;

/* Root URL */
$app->get('/', function() use ($app) {
  try {
    \Socialite::driver('google')->userFromToken($_SESSION['token']);
    return redirect('booking', 302, [], true);
  } catch (\Exception $e) {
    unset($_SESSION['token']);
    unset($_SESSION['expiresIn']);
    unset($_SESSION['email']);
    return redirect('login', 302, [], true);
  }
});

/* Login page */
$app->get('/login', function() use ($app) {
  try {
    \Socialite::driver('google')->userFromToken($_SESSION['token']);
    return redirect('booking', 302, [], true);
  } catch (\Exception $e) {
    unset($_SESSION['token']);
    unset($_SESSION['expiresIn']);
    unset($_SESSION['email']);
  }

  $errors = [];
  if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
  }

  return $app->make('view')->make('login', ['allowed_domains'=>env('SILID_ALLOWED_DOMAINS'), 'errors' => $errors]);
});

/* Booking form */
$app->get('/booking', 'BookingController@getBooking');

/* Booking reset form */
$app->get('/booking/reset', 'BookingController@getReset');

/* Booking saving */
$app->post('/booking', 'BookingController@postBooking');

/* Booking Confirmation */
$app->get('/booking/confirmation/{confirmation_id}', 'BookingController@getConfirmation');

/* Booking View */
$app->get('/booking/view/{booking_id_param}', 'BookingController@getView');

/* Cancel Booking */
$app->post('/booking/cancel/{booking_id_param}', 'BookingController@postCancel');

/* Booking All */
$app->get('/booking/view-all/{date}/{status}', 'BookingController@getViewAll');
$app->post('/booking/view-all/{date}/{status}', 'BookingController@postViewAll');

$app->get('/booking/view-own/{date}/{status}', 'BookingController@getViewAll');
$app->post('/booking/view-own/{date}/{status}', 'BookingController@postViewAll');


/*
 * https://github.com/laravel/socialite
 * http://socialiteproviders.github.io/providers/google+/
 * https://laracasts.com/discuss/channels/lumen/cant-get-config-data-in-lumen
 * https://lumen.laravel.com/docs/5.4/configuration#configuration-files)
 * http://itsolutionstuff.com/post/solved-access-not-configured-google-api-truncated-on-google-console-developerexample.html
 * http://stackoverflow.com/questions/35536548/unable-to-use-laravel-socialite-with-lumen
 */
$app->get('/socialite/google/login', function () use ($app) {
  return \Socialite::driver('google')->stateless(false)->redirect();
});

/* Socialite Google callback - after google login */
$app->get('/socialite/google/callback', function () use ($app) {
  try {
    $user = \Socialite::driver('google')->stateless(false)->user();

    $regex = '/@((([^.]+)\.)+)([a-zA-Z]{3,}|[a-zA-Z.]{5,})/';
    preg_match($regex, $user->email, $matches);
    $hostname = substr($matches[0], 1);

    if (! in_array($hostname, explode(",",env('SILID_ALLOWED_DOMAINS')))) {
      $_SESSION['errors'] = ['Your email is not part of the allowed domains. Please sign-in with an email from the allowed domains.'];
      return redirect('login', 302, [], true);
    }

    // OAuth Two Providers
    $token = $user->token;
    $expiresIn = $user->expiresIn;

    $_SESSION['token'] = $token;
    $_SESSION['expiresIn'] = time() + $expiresIn;
    $_SESSION['email'] = $user->email;
    return redirect('/booking/view-all/' . date('Y-m-d') . '/confirmed', 302, [], true);
  } catch (\Exception $e) {
    return redirect('login', 302, [], true);
  }
});

/* Logout */
$app->get('/logout', function () use ($app) {
  unset($_SESSION['token']);
  unset($_SESSION['expiresIn']);
  unset($_SESSION['email']);
  return redirect('login', 302, [], true);
});


/* decode booking id for view */
function decodeBookingIdForConfirmation($booking_id) {
  $hashids = new Hashids(env('APP_KEY'), config('booking.hashes.CONFIRMATION_HASH_LENGTH'));
  return $hashids->decode($booking_id);
}

/* encode booking id for view */
function encodeBookingIdForConfirmation($booking_id) {
  $hashids = new Hashids(env('APP_KEY'), config('booking.hashes.CONFIRMATION_HASH_LENGTH'));
  return $hashids->encode($booking_id);
}

/* decode booking id for view */
function decodeBookingIdForView($booking_id) {
  $hashids = new Hashids(env('APP_KEY'), config('booking.hashes.VIEW_HASH_LENGTH'));
  return $hashids->decode($booking_id);
}

/* encode booking id for view */
function encodeBookingIdForView($booking_id) {
  $hashids = new Hashids(env('APP_KEY'), config('booking.hashes.VIEW_HASH_LENGTH'));
  return $hashids->encode($booking_id);
}

/* generateBookingViewRoute */
function generateBookingViewRoute($booking_id) {
  $booking_id_hashed = encodeBookingIdForView($booking_id);

  return "booking/view/$booking_id_hashed";
}

/* generateBookingViewLink */
function generateBookingViewLink($booking_id) {
  $hostname = env('SILID_HOSTNAME');
  return "$hostname/" . generateBookingViewRoute($booking_id);
}

/* generateBookingViewRoute */
function generateBookingCancellationRoute($booking_id) {
  $booking_id_hashed = encodeBookingIdForView($booking_id);

  return "booking/cancel/$booking_id_hashed";
}

function generateBookingCancellationLink($booking_id) {
  $hostname = env('SILID_HOSTNAME');
  return "$hostname/" . generateBookingCancellationRoute($booking_id);
}

// \Event::listen('Illuminate\Database\Events\QueryExecuted', function ($query) {
//     var_dump($query->sql);
//     var_dump($query->bindings);
//     var_dump($query->time);
// });
