<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SystemConfig extends Model
{
    protected $fillable = ['key', 'value'];

    public const FAVICON_KEY = 'favicon';
    public const LOGO_KEY = 'logo';
    public const CACHE_TTL = 3600; // 1 hour
    public const CACHE_KEY_PREFIX = 'system_config:';

    /**
     * Get config value by key. Uses cache for performance.
     */
    public static function getValue(string $key, ?string $default = null): ?string
    {
        if (! Schema::hasTable('system_configs')) {
            return $default;
        }
        $cacheKey = self::CACHE_KEY_PREFIX . $key;
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default) {
            $config = static::where('key', $key)->first();
            return $config?->value ?? $default;
        });
    }

    /**
     * Set config value and clear cache.
     */
    public static function setValue(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY_PREFIX . $key);
    }

    /**
     * Get full URL for favicon (for use in blade/HTML).
     */
    public static function faviconUrl(): string
    {
        $path = self::getValue(self::FAVICON_KEY);
        if ($path) {
            return asset('storage/' . ltrim($path, '/'));
        }
        return asset('images/moh.png');
    }

    /**
     * Get full URL for logo (for use in blade/HTML).
     */
    public static function logoUrl(): string
    {
        $path = self::getValue(self::LOGO_KEY);
        if ($path) {
            return asset('storage/' . ltrim($path, '/'));
        }
        return asset('assets/images/moh.png');
    }
}
