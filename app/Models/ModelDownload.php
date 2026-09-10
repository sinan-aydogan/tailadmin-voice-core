<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelDownload extends Model
{
    protected $fillable = [
        'model_id',
        'status',
        'progress',
        'downloaded_bytes',
        'total_bytes',
        'error_message',
    ];

    protected $casts = [
        'progress' => 'float',
        'downloaded_bytes' => 'integer',
        'total_bytes' => 'integer',
    ];
}
