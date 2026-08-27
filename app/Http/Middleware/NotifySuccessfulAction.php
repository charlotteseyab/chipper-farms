<?php

namespace App\Http\Middleware;

use App\Notifications\SystemNotification;
use App\Models\ActivityLog;
use App\Services\Audit\AuditTrailService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Response;

class NotifySuccessfulAction
{
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private const EXCLUDED_ROUTES = [
        'notifications.read',
        'notifications.readAll',
        'logout',
        'api.check-ear-tag',
        'check-relationship',
        'all-stocks.import.preview',
        // These already publish richer, recipient-aware notifications.
        'breeding-stocks.store',
        'purchase-requests.store',
        'purchase-requests.approve',
        'purchase-requests.decline',
    ];

    private const AUDIT_EXCLUDED_ROUTES = [
        'notifications.read',
        'notifications.readAll',
        'logout',
        'api.check-ear-tag',
        'check-relationship',
        'all-stocks.import.preview',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $auditStartedAt = now();
        $response = $next($request);

        if (! in_array($request->method(), self::MUTATING_METHODS, true)
            || ! $request->user()
            || $response->getStatusCode() >= 400
            || $this->hasValidationErrors($request)) {
            return $response;
        }

        $routeName = (string) ($request->route()?->getName() ?: 'system.action');
        $successMessage = $request->hasSession()
            ? trim(strip_tags((string) $request->session()->get('success', '')))
            : '';
        $action = $this->actionLabel($routeName);
        $module = $this->moduleLabel($routeName);
        $record = $this->recordLabel($request);
        $occurredAt = now();
        $detail = 'You '.$this->actionPhrase($routeName)
            .($record ? " {$record}" : '')
            ." in {$module} on {$occurredAt->format('j M Y')} at {$occurredAt->format('g:i A')}.";
        if ($successMessage !== '') {
            $detail .= " Outcome: {$successMessage}";
        }

        $this->auditSuccessfulAction($request, $response, $routeName, $module, $record, $detail, $successMessage, $auditStartedAt);

        if (in_array($request->route()?->getName(), self::EXCLUDED_ROUTES, true)) {
            return $response;
        }

        $request->user()->notify(new SystemNotification(
            key: 'system.'.str_replace(['.', '-'], '_', $routeName),
            module: $module,
            title: $action,
            message: $detail,
            link: $this->destination($request, $response),
            severity: 'success',
            context: [
                'route' => $routeName,
                'method' => $request->method(),
                'actor_name' => $request->user()->name,
                'record' => $record,
                'outcome' => $successMessage ?: null,
                'occurred_at' => $occurredAt->toIso8601String(),
            ],
        ));

        return $response;
    }

    private function auditSuccessfulAction(
        Request $request,
        Response $response,
        string $routeName,
        string $module,
        ?string $record,
        string $detail,
        string $successMessage,
        \Illuminate\Support\Carbon $auditStartedAt,
    ): void {
        if (in_array($routeName, self::AUDIT_EXCLUDED_ROUTES, true)) {
            return;
        }

        // Rich domain services and observers may already have recorded this
        // operation. Prefer that entry instead of adding a vague duplicate.
        $alreadyLogged = ActivityLog::query()
            ->where('user_id', $request->user()->id)
            ->where('created_at', '>=', $auditStartedAt->copy()->startOfSecond())
            ->exists();

        if ($alreadyLogged) {
            return;
        }

        $auditable = $this->auditable($request);
        app(AuditTrailService::class)->log(
            module: $module,
            action: $this->auditAction($routeName),
            payload: [
                'auditable' => $auditable,
                'record_label' => $record,
                'description' => $detail,
                'metadata' => [
                    'route' => $routeName,
                    'method' => $request->method(),
                    'outcome' => $successMessage ?: null,
                    'destination' => $this->destination($request, $response),
                    'source' => 'successful_request_fallback',
                ],
            ],
        );
    }

    private function auditable(Request $request): ?Model
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) {
                return $parameter;
            }
        }

        return null;
    }

    private function auditAction(string $routeName): string
    {
        $verb = Str::afterLast($routeName, '.');

        return match ($verb) {
            'store', 'create', 'save' => 'created',
            'update' => 'updated',
            'destroy', 'delete' => 'deleted',
            'approve' => 'approved',
            'decline', 'reject' => 'declined',
            'restore' => 'restored',
            'archive' => 'archived',
            'receive' => 'received',
            'mark-paid', 'pay', 'paid' => 'payment_recorded',
            default => Str::snake($verb),
        };
    }

    private function hasValidationErrors(Request $request): bool
    {
        return $request->hasSession() && $request->session()->has('errors');
    }

    private function actionLabel(string $routeName): string
    {
        $parts = explode('.', $routeName);
        $verb = array_pop($parts);
        $resource = Str::headline((string) (array_pop($parts) ?: 'action'));

        $verbLabel = match ($verb) {
            'store', 'create', 'save' => 'created',
            'update' => 'updated',
            'destroy', 'delete' => 'deleted',
            'approve' => 'approved',
            'decline', 'reject' => 'declined',
            'restore' => 'restored',
            'archive' => 'archived',
            'pay', 'paid', 'mark-paid' => 'marked as paid',
            default => Str::lower(Str::headline((string) $verb)),
        };

        return "{$resource} {$verbLabel}";
    }

    private function actionPhrase(string $routeName): string
    {
        $verb = Str::afterLast($routeName, '.');

        return match ($verb) {
            'store', 'create', 'save' => 'created',
            'update' => 'updated',
            'destroy', 'delete' => 'deleted',
            'approve' => 'approved',
            'decline', 'reject' => 'declined',
            'restore' => 'restored',
            'archive' => 'archived',
            'receive' => 'recorded receipt of',
            'record', 'record-sale', 'pregnancy-check', 'farrowing' => 'recorded',
            'mark-paid', 'pay', 'paid' => 'recorded payment for',
            default => Str::lower(Str::headline($verb)),
        };
    }

    private function recordLabel(Request $request): ?string
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) {
                foreach (['ear_tag_number', 'code', 'name', 'title', 'reference', 'request_no', 'invoice_number', 'batch_number'] as $field) {
                    if (filled($parameter->{$field} ?? null)) {
                        return class_basename($parameter).' “'.$parameter->{$field}.'”';
                    }
                }

                return class_basename($parameter).' #'.$parameter->getKey();
            }
        }

        if (Str::startsWith((string) $request->route()?->getName(), 'weights.') && $request->filled('pig_id')) {
            $pig = \App\Models\PigManagement::find($request->integer('pig_id'));
            if ($pig) {
                return 'a weight for pig “'.($pig->ear_tag_number ?: $pig->code).'”';
            }
        }

        foreach (['name', 'title', 'reference', 'request_no', 'ear_tag_number', 'new_breed_name'] as $field) {
            if ($request->filled($field)) {
                return '“'.Str::limit((string) $request->input($field), 80).'”';
            }
        }

        return null;
    }

    private function moduleLabel(string $routeName): string
    {
        $prefix = explode('.', $routeName)[0] ?? 'system';

        return match (true) {
            Str::contains($prefix, ['settings']) => 'Settings and administration',
            Str::contains($prefix, ['structure', 'room']) => 'Structures and rooms',
            Str::contains($prefix, ['pig', 'stock']) => 'Pig operations',
            Str::contains($prefix, ['weight']) => 'Weight management',
            Str::contains($prefix, ['health', 'medication']) => 'Health and medication',
            Str::contains($prefix, ['mating', 'gestation', 'farrowing', 'weaning']) => 'Breeding and lifecycle',
            Str::contains($prefix, ['death']) => 'Mortality',
            Str::contains($prefix, ['feed', 'purchase', 'raw-material']) => 'Feed and procurement',
            Str::contains($prefix, ['finance', 'invoice', 'payment', 'expense', 'journal', 'bank']) => 'Finance',
            Str::contains($prefix, ['storefront', 'listing', 'quote']) => 'Storefront',
            Str::contains($prefix, ['sale']) => 'Sales',
            Str::contains($prefix, ['user', 'role', 'permission']) => 'Users and security',
            default => 'System operations',
        };
    }

    private function destination(Request $request, Response $response): ?string
    {
        if ($response instanceof RedirectResponse) {
            return $response->getTargetUrl();
        }

        return $request->headers->get('referer');
    }
}
