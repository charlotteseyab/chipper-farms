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
        $isSuperuser = $user && optional($user->role)->name === 'superuser';

        $notifications = $isSuperuser
            ? DatabaseNotification::query()->latest()->paginate(15)
            : $user->notifications()->latest()->paginate(15);

        return view('notifications.index', compact('notifications'));
    }

    public function markRead(string $id)
    {
        $user = Auth::user();
        $isSuperuser = $user && optional($user->role)->name === 'superuser';

        $notification = $isSuperuser
            ? DatabaseNotification::query()->where('id', $id)->firstOrFail()
            : $user->notifications()->where('id', $id)->firstOrFail();

        if (is_null($notification->read_at)) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return back();
    }

    public function markAllRead(Request $request)
    {
        $user = Auth::user();
        $isSuperuser = $user && optional($user->role)->name === 'superuser';

        if ($isSuperuser) {
            DatabaseNotification::query()->whereNull('read_at')->update(['read_at' => now()]);
            $remaining = DatabaseNotification::whereNull('read_at')->count();
        } else {
            $user->unreadNotifications()->update(['read_at' => now()]);
            $remaining = $user->unreadNotifications()->count();
        }

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
