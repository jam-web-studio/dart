<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['value' => 'array'];

    public const DEFAULTS = [
        'site_name' => 'Державне агентство розвитку туризму України',
        'site_name_en' => 'State Agency for Tourism Development of Ukraine',
        'phone' => '',
        'email' => '',
        'address' => '',
        'address_en' => '',
        'facebook' => '',
        'instagram' => '',
        'youtube' => '',
        'telegram' => '',
        'twitter' => '',
        'hero_title' => '',
        'hero_text' => '',
        'hero_image' => '',
        'footer_text' => '',
    ];

    public static function get(string $key, $default = null)
    {
        $all = Cache::remember('settings', 600, fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function publicAll(): array
    {
        return collect(self::DEFAULTS)->keys()->mapWithKeys(fn ($k) => [$k => static::get($k)])->all();
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('settings'));
    }
}
