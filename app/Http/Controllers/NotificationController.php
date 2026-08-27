<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $notifications = $user->notifications()->latest()->paginate(15);

        return view('notifications.index', compact('notifications'));
    }

    public function markRead(string $id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->where('id', $id)->firstOrFail();

        if (is_null($notification->read_at)) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return back();
    }

    public function markAllRead(Request $request)
    {
        $user = Auth::user();
        $user->unreadNotifications()->update(['read_at' => now()]);
        $remaining = $user->unreadNotifications()->count();

        $realTimeAlertCount = 0;
        if (class_exists(\App\Services\RealTimeNotificationService::class)) {
            try {
                $realTimeAlertCount = \App\Services\RealTimeNotificationService::getTotalAlertCount();
            } catch (\Throwable $e) {
                // ignore service failures
            }
        }

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'count' => $remaining + $realTimeAlertCount,
            ]);
        }

        return back();
    }
}
