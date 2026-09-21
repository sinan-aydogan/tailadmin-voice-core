<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlowRun extends Model
{
    protected $fillable = [
        'flow_id',
        'status',
        'trigger_payload',
        'context',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'trigger_payload' => 'array',
        'context' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(FlowRunLog::class)->orderBy('id');
    }
}
