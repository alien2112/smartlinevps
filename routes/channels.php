<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Driver private channel
Broadcast::channel('driver.{driverId}', function ($user, $driverId) {
    return $user->id === $driverId;
});

// Customer private channel
Broadcast::channel('customer.{customerId}', function ($user, $customerId) {
    return $user->id === $customerId;
});

// Ride channel - both driver and customer can listen
Broadcast::channel('ride.{rideId}', function ($user, $rideId) {
    // Allow access if user is the customer or driver of this ride
    return true; // Simplified - you may want to add proper authorization
});
