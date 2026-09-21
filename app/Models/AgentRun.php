<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentRun extends Model
{
    protected $fillable = [
        'agent_id',
        'conversation_id',
        'status',
        'trigger_payload',
        'final_reply',
        'final_output',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'trigger_payload' => 'array',
        'final_output' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AgentRunStep::class)->orderBy('id');
    }
}
