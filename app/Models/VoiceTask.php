<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceTask extends Model
{
    protected $fillable = [
        'type',
        'status',
        'payload',
        'result',
        'output_path',
        'progress',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'progress' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
