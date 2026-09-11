<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'api_key_id',
        'action_type',
        'endpoint',
        'method',
        'ip_address',
        'status_code',
        'task_id',
        'request_summary',
        'response_time_ms',
        'created_at',
    ];

    protected $casts = [
        'request_summary' => 'array',
        'status_code' => 'integer',
        'response_time_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(VoiceTask::class, 'task_id');
    }
}
