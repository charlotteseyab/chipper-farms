<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuditTrailService
{
    public function log(string $module, string $action, array $payload = []): ?ActivityLog
    {
        try {
            $auditable = $payload['auditable'] ?? null;
            $user = $payload['user'] ?? auth()->user();

            return ActivityLog::create([
                'user_id' => $user?->id,
                'organization_id' => $payload['organization_id'] ?? $user?->organization_id,
                'name' => $payload['name'] ?? $user?->name,
                'action' => $action,
                'module' => $module,
                'auditable_type' => $auditable instanceof Model ? $auditable::class : ($payload['auditable_type'] ?? null),
                'auditable_id' => $auditable instanceof Model ? $auditable->getKey() : ($payload['auditable_id'] ?? null),
                'record_label' => $payload['record_label'] ?? null,
                'description' => $payload['description'] ?? null,
                'old_values' => $payload['old_values'] ?? null,
                'new_values' => $payload['new_values'] ?? null,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'metadata' => $payload['metadata'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Audit trail logging failed.', [
                'module' => $module,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function logModelChanges(Model $model, string $module, string $action, array $before, array $after, array $payload = []): ?ActivityLog
    {
        $oldValues = [];
        $newValues = [];

        foreach ($after as $key => $value) {
            $old = $before[$key] ?? null;
            if ((string) $old !== (string) $value) {
                $oldValues[$key] = $old;
                $newValues[$key] = $value;
            }
        }

        if ($oldValues === [] && $newValues === []) {
            return null;
        }

        return $this->log($module, $action, array_merge($payload, [
            'auditable' => $model,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]));
    }

    public function actionLabel(?string $action): string
    {
        return Str::headline((string) $action);
    }
}
