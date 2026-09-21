<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentSession extends Model
{
    protected $fillable = [
        'agent_id',
        'conversation_id',
        'messages',
        'last_activity_at',
    ];

    protected $casts = [
        'messages' => 'array',
        'last_activity_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function appendMessages(array $newMessages): void
    {
        $messages = $this->messages ?? [];
        foreach ($newMessages as $m) {
            $messages[] = $m;
        }
        $this->messages = $messages;
        $this->last_activity_at = now();
        $this->save();
    }
}
