<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentRunStep extends Model
{
    protected $fillable = [
        'agent_run_id',
        'step_type',
        'tool_name',
        'flow_id',
        'input',
        'output',
        'error_message',
        'duration_ms',
    ];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'duration_ms' => 'integer',
    ];

    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }
}
