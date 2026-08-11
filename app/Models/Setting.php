<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'label', 'description'];

    protected static function booted(): void
    {
        static::saved(fn($setting) => Cache::forget('setting:' . $setting->key));
        static::deleted(fn($setting) => Cache::forget('setting:' . $setting->key));
    }

    /**
     * Lit une valeur de paramètre (mise en cache — ce getter est appelé à
     * chaque saisie de bulletin par procuration).
     */
    public static function get(string $key, $default = null)
    {
        return Cache::rememberForever('setting:' . $key, function () use ($key, $default) {
            return static::where('key', $key)->value('value') ?? $default;
        });
    }

    public static function set(string $key, $value): self
    {
        return static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
