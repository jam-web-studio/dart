<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['locale' => 'uk', 'is_published' => true];

    protected $casts = ['is_published' => 'boolean', 'scripts' => 'array', 'styles' => 'array'];

    public function scopePublished(Builder $q): void
    {
        $q->where('is_published', true);
    }

    /** Головна має path «home», але живе на «/» */
    public function url(): string
    {
        return Site::prefix($this->locale).'/'.($this->path === 'home' ? '' : $this->path);
    }
}
