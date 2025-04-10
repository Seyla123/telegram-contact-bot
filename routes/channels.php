<?php

use Illuminate\Support\Facades\Broadcast;

// Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
//     return (int) $user->id === (int) $id;
// });
// Broadcast::channel('user.{contact_id}', function ($user, $contact_id) {
//     // Make sure the user is authorized to listen to the channel
//     return (int) $user->id === (int) $contact_id;
// });

// routes/channels.php
// Broadcast::channel('user.{user_id}', function () {
//     \Log::info('User joined channel');
//     return true; // Allow any user to join the channel
// });

// Broadcast::channel('new_message', function () {
//     \Log::info('User joined channel');
//     return true; // Allow any user to join the channel
// });

