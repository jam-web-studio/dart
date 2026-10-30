<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasMedia;

    protected $guarded = ['id'];

    protected $attributes = ['locale' => 'uk', 'is_published' => true, 'sort' => 0];

    protected $casts = ['is_published' => 'boolean', 'date' => 'date'];

    /** Посилання для завантаження: свій файл або зовнішня адреса */
    public function link(): ?string
    {
        return static::mediaUrl($this->file) ?? $this->url;
    }
}
