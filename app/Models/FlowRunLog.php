<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowRunLog extends Model
{
    protected $fillable = [
        'flow_run_id',
        'node_id',
        'node_type',
        'status',
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

    public function flowRun(): BelongsTo
    {
        return $this->belongsTo(FlowRun::class);
    }
}
