<?php

namespace App\Listeners;

use Illuminate\Auth\Events\LoggedOut;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

class ClearUserLastSeenOnLogout
{
    /**
     * Handle the event.
     */
    public function handle(LoggedOut $event): void
    {
        // Check if a user model is available in the event data
        if ($event->user) {
            // Update the last_seen column directly for the logged out user
            $event->user->forceFill(['last_seen' => null])->save();

            // Note: We use forceFill to bypass $fillable check for this specific action.
            // If 'last_seen' is in your User model's $fillable array, 
            // you can use $event->user->update(['last_seen' => null]);
        }
    }
}