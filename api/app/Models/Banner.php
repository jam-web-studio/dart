<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasMedia;

    protected $guarded = ['id'];

    protected $attributes = ['locale' => 'uk', 'place' => 'home', 'is_published' => true, 'sort' => 0];

    protected $casts = ['is_published' => 'boolean'];
}
