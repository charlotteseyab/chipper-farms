<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Setting extends Model
{
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
        $organizationId = static::currentOrganizationId();

        $row = null;

        if ($organizationId !== null) {
            $row = static::query()
                ->where('organization_id', $organizationId)
                ->where('key', $key)
                ->first();
        }

        // Fall back to the shared default.
        $row ??= static::query()
            ->whereNull('organization_id')
            ->where('key', $key)
            ->first();

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

    public static function defaultPurchaseFeedItems(): array
    {
        return [
            'Piglet Starter Feed',
            'Weaner Feed',
            'Grower Feed',
            'Finisher Feed',
            'Sow and Boar Feed',
            'Lactating Sow Feed',
            'Maize',
            'Soybean Meal',
            'Wheat Bran',
            'Premix',
            'Concentrate',
        ];
    }

    public static function purchaseFeedItems(): array
    {
        $items = self::get('purchase_requests.feed_items', self::defaultPurchaseFeedItems());

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