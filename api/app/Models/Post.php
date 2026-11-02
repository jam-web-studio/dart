<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    use HasMedia;

    protected $guarded = ['id'];

    protected $attributes = ['locale' => 'uk', 'is_published' => true, 'is_featured' => false, 'is_main' => false];

    protected $casts = ['is_published' => 'boolean', 'is_featured' => 'boolean', 'is_main' => 'boolean', 'gallery' => 'array', 'published_at' => 'datetime'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopePublished(Builder $q): void
    {
        $q->where('is_published', true)->where(fn ($w) => $w->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    /** Адреси як на старому сайті: /blog/{slug} */
    public function url(): string
    {
        return Site::prefix($this->locale).'/blog/'.$this->slug;
    }
}
