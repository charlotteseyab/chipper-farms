<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Setting extends Model
{
    protected $table = 'system_configs';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, $default = null)
    {
        $row = static::query()->where('key', $key)->first();
        if (! $row) {
            return $default;
        }

        $decoded = json_decode($row->value, true);

        return $decoded === null && json_last_error() !== JSON_ERROR_NONE
            ? $row->value
            : $decoded;
    }

    public static function set(string $key, $value): void
    {
        $stored = is_array($value) ? json_encode($value) : (string) $value;
        static::updateOrCreate(['key' => $key], ['value' => $stored]);
    }

    public static function getAll(): array
    {
        return self::all()->pluck('value', 'key')->map(function ($value) {
            $decoded = json_decode($value, true);

            return ($decoded === null && json_last_error() !== JSON_ERROR_NONE)
                ? $value
                : $decoded;
        })->toArray();
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