<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class EmailSetting extends Model
{
    protected $fillable = [
        'mailer',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_address',
        'from_name',
    ];

    protected $casts = [
        'port' => 'integer',
    ];

    /**
     * Get the email settings (singleton pattern - only one row)
     */
    public static function getSettings()
    {
        return static::first() ?? static::create([
            'mailer' => env('MAIL_MAILER', 'smtp'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 587),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD') ? Crypt::encryptString(env('MAIL_PASSWORD')) : null,
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'from_address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
            'from_name' => env('MAIL_FROM_NAME', 'Example'),
        ]);
    }

    /**
     * Accessor for password - decrypt when retrieved
     */
    public function getPasswordAttribute($value)
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            // If decryption fails, return null (might be plain text from old data)
            return null;
        }
    }

    /**
     * Mutator for password - encrypt when stored
     */
    public function setPasswordAttribute($value)
    {
        if (empty($value)) {
            // If password is empty, don't update it (keep existing)
            unset($this->attributes['password']);
        } else {
            $this->attributes['password'] = Crypt::encryptString($value);
        }
    }
}
