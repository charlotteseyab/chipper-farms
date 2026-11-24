<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Setting extends Model
{
    /**
     * Operational choices owned by each farm. These keys must never inherit a
     * platform row because breeds, timings, units and prices are farm policy.
     */
    private const FARM_OWNED_PREFIXES = [
        'breeding.',
        'stages.',
        'sales.pricing.',
        'feed.units.',
        'feed.alerts.',
        'purchase_requests.feed_items',
    ];

    protected $table = 'system_configs';

    protected $fillable = [
        'organization_id',
        'key',
        'value',
    ];

    /**
     * Settings are organisation-scoped, with a shared default (IMP-001).
     *
     * `system_configs` was global: one row per key for the whole installation.
     * That is harmless for a logo, and a money defect for
     * `sales.pricing.live_weight_price`, which `RecordPigSaleService` reads to
     * price a real sale — on a second tenant, one farm's price per kilo would
     * set another farm's revenue.
     *
     * A row with a NULL `organization_id` is the default for every farm. A row
     * with an id belongs to that farm alone and wins for it. Nothing was
     * migrated, so every value that exists today is still the default and still
     * applies everywhere until a farm saves its own.
     *
     * Reads resolve the organisation from `CurrentOrganization`, the same
     * resolver the rest of the application uses. Outside any organisation
     * context — console commands, the installer — the default row answers,
     * which is the behaviour those callers had before.
     */
    public static function get(string $key, $default = null)
    {
        return static::getForOrganization(static::currentOrganizationId(), $key, $default);
    }

    /** Any save or delete empties the per-request store, so a change is seen at once. */
    protected static function booted(): void
    {
        $flush = fn () => app()->bound('settings.memo') ? app('settings.memo')->exchangeArray([]) : null;
        static::saved($flush);
        static::deleted($flush);
    }

    public static function getForOrganization(?int $organizationId, string $key, $default = null)
    {
        // Read once per request or job (see the settings.memo binding).
        $memo = app()->bound('settings.memo') ? app('settings.memo') : null;
        $memoKey = ($organizationId ?? 'shared') . '|' . $key;
        if ($memo !== null && $memo->offsetExists($memoKey)) {
            $stored = $memo[$memoKey];

            return $stored === null ? $default : static::decode($stored);
        }

        $row = null;

        if ($organizationId !== null) {
            $row = static::query()
                ->where('organization_id', $organizationId)
                ->where('key', $key)
                ->first();
        }

        // General platform settings may still inherit a shared value, but
        // operational setup belongs exclusively to the individual farm.
        if (! ($organizationId !== null && static::isFarmOwnedKey($key))) {
            $row ??= static::query()
                ->whereNull('organization_id')
                ->where('key', $key)
                ->first();
        }

        if ($memo !== null) {
            $memo[$memoKey] = $row?->value;
        }

        if (! $row) {
            return $default;
        }

        return static::decode($row->value);
    }

    /**
     * Write the value for the current organisation.
     *
     * Inside an organisation context this writes that farm's own row and leaves
     * the shared default untouched, so saving a price for one farm cannot move
     * another farm's prices. Outside one it writes the default, which is what a
     * seeder or installer means.
     */
    public static function set(string $key, $value): void
    {
        $stored = is_array($value) ? json_encode($value) : (string) $value;

        static::updateOrCreate(
            ['organization_id' => static::currentOrganizationId(), 'key' => $key],
            ['value' => $stored],
        );
    }

    /**
     * Every setting visible to the current organisation.
     *
     * Its own rows layered over the shared defaults, so a settings screen shows
     * what this farm would actually get.
     *
     * @return array<string, mixed>
     */
    public static function getAll(): array
    {
        $organizationId = static::currentOrganizationId();

        $defaults = static::query()
            ->whereNull('organization_id')
            ->pluck('value', 'key')
            ->reject(fn ($value, $key) => static::isFarmOwnedKey((string) $key))
            ->map(fn ($value) => static::decode($value))
            ->toArray();

        if ($organizationId === null) {
            return $defaults;
        }

        $scoped = static::query()
            ->where('organization_id', $organizationId)
            ->pluck('value', 'key')
            ->map(fn ($value) => static::decode($value))
            ->toArray();

        return array_merge($defaults, $scoped);
    }

    protected static function currentOrganizationId(): ?int
    {
        if (! app()->bound(\App\Support\CurrentOrganization::class)) {
            return null;
        }

        try {
            return app(\App\Support\CurrentOrganization::class)->id();
        } catch (\Throwable $e) {
            // No organisation context — the shared default is the right answer.
            return null;
        }
    }

    protected static function decode($value)
    {
        $decoded = json_decode((string) $value, true);

        return ($decoded === null && json_last_error() !== JSON_ERROR_NONE)
            ? $value
            : $decoded;
    }

    private static function isFarmOwnedKey(string $key): bool
    {
        return collect(static::FARM_OWNED_PREFIXES)
            ->contains(fn (string $prefix) => str_starts_with($key, $prefix));
    }

    public static function purchaseFeedItems(): array
    {
        $items = self::get('purchase_requests.feed_items', []);

        return self::normalizePurchaseFeedItems(is_array($items) ? $items : []);
    }

    public static function setPurchaseFeedItems(array $items): void
    {
        self::set('purchase_requests.feed_items', self::normalizePurchaseFeedItems($items));
    }

    public static function normalizePurchaseFeedItems(array $items): array
    {
        $cleaned = collect($items)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique(fn ($item) => strtolower($item))
            ->values()
            ->all();

        return $cleaned;
    }

    /** @return array<string,string> */
    public static function animalBreeds(): array
    {
        $breeds = self::get('breeding.animal_breeds', []);

        return self::normalizeAnimalBreeds(is_array($breeds) ? $breeds : []);
    }

    public static function setAnimalBreeds(array $breeds): void
    {
        self::set('breeding.animal_breeds', self::normalizeAnimalBreeds($breeds));
    }

    public static function normalizeAnimalBreeds(array $breeds): array
    {
        $normalized = [];
        foreach ($breeds as $code => $name) {
            $code = strtoupper(trim((string) $code));
            $name = trim((string) $name);
            if ($code !== '' && $name !== '') {
                $normalized[$code] = $name;
            }
        }

        return $normalized;
    }

    public static function breedName(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return self::animalBreeds()[strtoupper(trim($code))] ?? $code;
    }

    public static function normalizedLogoPath(): ?string
    {
        $path = self::get('logo');

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if (Str::startsWith($path, ['http://', 'https://'])) {
            $appUrl = rtrim((string) config('app.url'), '/');
            $path = preg_replace('#^'.preg_quote($appUrl, '#').'#', '', $path);
        }

        $path = ltrim($path, '/');

        if (Str::startsWith($path, 'storage/')) {
            $path = Str::after($path, 'storage/');
        }

        if (Str::startsWith($path, 'public/')) {
            $path = Str::after($path, 'public/');
        }

        return $path !== '' ? $path : null;
    }

    public static function hasLogo(): bool
    {
        $path = self::normalizedLogoPath();

        return $path ? Storage::disk('public')->exists($path) : false;
    }

    public static function logoUrl(): string
    {
        $path = self::normalizedLogoPath();

        if ($path && Storage::disk('public')->exists($path)) {
            return asset('storage/'.$path);
        }

        return asset('images/chipper-farms-logo.jpg');
    }
}
