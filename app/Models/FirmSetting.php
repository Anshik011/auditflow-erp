<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class FirmSetting extends Model
{
    public const ENCRYPTED_KEYS = [
        'whatsapp_access_token',
        'mail_password',
        'razorpay_key_secret',
        'razorpay_webhook_secret',
        'cron_secret_token',
    ];

    protected $fillable = ['key', 'value', 'group'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();
        if (! $setting || $setting->value === null) {
            return $default;
        }

        if (in_array($key, self::ENCRYPTED_KEYS, true) && $setting->value !== '') {
            try {
                return Crypt::decryptString($setting->value);
            } catch (\Throwable) {
                return $setting->value;
            }
        }

        return $setting->value;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::getValue($key);
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function setValue(string $key, mixed $value, string $group = 'general'): self
    {
        $stored = is_array($value) ? json_encode($value) : $value;

        if (in_array($key, self::ENCRYPTED_KEYS, true) && $stored !== null && $stored !== '') {
            $stored = Crypt::encryptString((string) $stored);
        }

        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'group' => $group]
        );
    }

    public static function allAsMap(): array
    {
        $raw = static::query()->pluck('value', 'key')->toArray();

        foreach (self::ENCRYPTED_KEYS as $key) {
            if (! empty($raw[$key])) {
                try {
                    $raw[$key] = Crypt::decryptString($raw[$key]);
                } catch (\Throwable) {
                    // Keep as-is if stored before encryption
                }
            }
        }

        return $raw;
    }
}
