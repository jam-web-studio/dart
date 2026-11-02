<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Наказ (/orders/{slug}, PDF) або звіт щодо запитів на інформацію (/reports/{slug}) */
class Document extends Model
{
    use HasMedia;

    public const KINDS = ['order' => 'Наказ', 'report' => 'Звіт щодо запитів'];

    protected $guarded = ['id'];

    protected $attributes = ['kind' => 'order', 'locale' => 'uk', 'is_published' => true, 'sort' => 0];

    protected $casts = ['is_published' => 'boolean', 'date' => 'date', 'meta' => 'array'];

    public function scopePublished(Builder $q): void
    {
        $q->where('is_published', true);
    }

    /** Накази: новіші вгорі; звіти: за місяцем звіту, як на старому сайті */
    public function scopeOrderedFor(Builder $q, string $kind): void
    {
        $kind === 'report'
            ? $q->orderBy('sort')
            : $q->orderByDesc('date')->orderBy('sort')->orderByDesc('id');
    }

    /** PDF: свій файл або зовнішня адреса */
    public function link(): ?string
    {
        return static::mediaUrl($this->file) ?? $this->url;
    }

    public function path(): string
    {
        return ($this->kind === 'report' ? 'reports' : 'orders').'/'.$this->slug;
    }

    public function urlPath(): string
    {
        return Site::prefix($this->locale).'/'.$this->path();
    }
}
