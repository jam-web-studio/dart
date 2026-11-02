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
        'meta_description' => '',
        'og_image' => '',
    ];

    /** Шапка, підвал, віджет доступності й загальний скрипт старого сайту (HTML Webflow), шаблони сторінок записів */
    public const LAYOUT = ['layout_header', 'layout_footer', 'layout_accessibility', 'layout_global_script', 'layout_head_styles'];

    public const TEMPLATES = ['template_post', 'template_order', 'template_report'];

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
