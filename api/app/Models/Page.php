<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    use HasMedia;

    protected $guarded = ['id'];

    protected $attributes = ['locale' => 'uk', 'template' => 'default', 'is_published' => true, 'sort' => 0];

    protected $casts = ['is_published' => 'boolean'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('sort');
    }

    public function scopePublished(Builder $q): void
    {
        $q->where('is_published', true);
    }

    public function url(): string
    {
        return Site::prefix($this->locale).'/'.$this->path;
    }

    protected static function booted(): void
    {
        // повна адреса = адреса батька + slug; оновлюється й у дочірніх сторінок
        static::saving(function (Page $p) {
            $p->path = trim(($p->parent_id ? Page::find($p->parent_id)?->path.'/' : '').$p->slug, '/');
        });
        static::saved(function (Page $p) {
            if ($p->wasChanged('path')) {
                $p->children()->get()->each->save();
            }
        });
    }
}
