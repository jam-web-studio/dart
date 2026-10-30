<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['menu' => 'header', 'locale' => 'uk', 'new_tab' => false, 'sort' => 0];

    protected $casts = ['new_tab' => 'boolean'];

    public const MENUS = ['header' => 'Головне меню', 'footer' => 'Підвал', 'top' => 'Верхня смуга'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort');
    }

    public static function tree(string $menu, string $locale): array
    {
        return static::where('menu', $menu)->where('locale', $locale)->whereNull('parent_id')
            ->with('children')->orderBy('sort')->get()
            ->map(fn ($i) => [
                'label' => $i->label, 'url' => $i->url, 'new_tab' => $i->new_tab,
                'children' => $i->children->map(fn ($c) => ['label' => $c->label, 'url' => $c->url, 'new_tab' => $c->new_tab])->values(),
            ])->all();
    }
}
