<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channel Authorization
|--------------------------------------------------------------------------
| Private channels require the authenticated user to pass the gate defined
| here before Reverb will permit a subscription.
*/

// Default Laravel model broadcast channel (used by model events)
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// ── private-user.{id} ────────────────────────────────────────────────────────
// Personal notification channel. Each user can subscribe ONLY to their own.
// Used by: LeaveRequestFiled, LeaveRequestDecided, PayrollStatusChanged events
// and by the in-app notification bell for real-time badge updates.
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
