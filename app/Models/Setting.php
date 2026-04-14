<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Setting extends Model
{
    protected $table = 'system_configs';

    protected $fillable = [
        'key',
        'value',
    ];

    /** * ORIGINAL METHODS (Do not remove)
     */
    public static function get(string $key, $default = null)
    {
        $row = static::query()->where('key', $key)->first();
        if (!$row) {
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

    /**
     * NEW ADDITIONS for General Settings
     */

    /**
     * Get all settings as a key-value pair array.
     * Useful for the General Settings Blade view.
     */
    public static function getAll(): array
    {
        return self::all()->pluck('value', 'key')->map(function ($value) {
            $decoded = json_decode($value, true);
            return ($decoded === null && json_last_error() !== JSON_ERROR_NONE) ? $value : $decoded;
        })->toArray();
    }

    /**
     * Helper to get the full URL of the uploaded farm logo.
     */
    public static function logoUrl(): string
    {
        $path = self::get('logo');
        if ($path && Storage::disk('public')->exists($path)) {
            return Storage::url($path);
        }
        // Fallback to a default logo or a placeholder
        return asset('images/default-logo.png');
    }
}