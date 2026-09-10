<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoicePlaylist extends Model
{
    protected $fillable = [
        'title',
        'description',
        'items',
    ];

    protected $casts = [
        'items' => 'array',
    ];
}
