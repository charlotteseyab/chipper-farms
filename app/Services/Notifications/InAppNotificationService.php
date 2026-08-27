<?php

namespace App\Services\Notifications;

use App\Models\Storefront\StorefrontBuyer;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Support\InAppNotificationCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class InAppNotificationService
{
    public function notifyBuyer(StorefrontBuyer|User|null $buyer, string $key, string $title, string $message, ?string $link = null, string $severity = 'info', array $context = []): void
    {
        if (! $buyer || ! $this->supportsAudience($key, 'buyer')) {
            return;
        }

        $buyer->notify($this->make($key, $title, $message, $link, $severity, $context));
    }

    /**
     * Notify only active staff in the same organisation who can access the
     * affected module. Explicit recipients are intersected with those rules.
     */
    public function notifyRelevantStaff(int $organizationId, string $key, string $title, string $message, ?string $link = null, string $severity = 'info', array $context = [], ?array $recipientUserIds = null): Collection
    {
        $definition = InAppNotificationCatalog::definitions()[$key] ?? null;
        if (! $definition || $definition['audience'] !== 'staff') {
            return collect();
        }

        $users = User::query()
            ->where('organization_id', $organizationId)
            ->where('active_status', true)
            ->when($recipientUserIds !== null, fn ($query) => $query->whereIn('id', $recipientUserIds))
            ->with('role.permissions')
            ->get()
            ->filter(fn (User $user) => $user->isSuperuser()
                || $user->isAdmin()
                || collect($definition['permissions'])->contains(fn (string $permission) => $user->hasPermission($permission)))
            ->values();

        if ($users->isNotEmpty()) {
            Notification::send($users, $this->make($key, $title, $message, $link, $severity, $context));
        }

        return $users;
    }

    protected function supportsAudience(string $key, string $audience): bool
    {
        return (InAppNotificationCatalog::definitions()[$key]['audience'] ?? null) === $audience;
    }

    protected function make(string $key, string $title, string $message, ?string $link, string $severity, array $context): SystemNotification
    {
        return new SystemNotification(
            key: $key,
            module: InAppNotificationCatalog::definitions()[$key]['module'] ?? 'System',
            title: $title,
            message: $message,
            link: $link,
            severity: $severity,
            context: $context,
        );
    }
}
