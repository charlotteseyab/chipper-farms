<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
    ];

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
}


