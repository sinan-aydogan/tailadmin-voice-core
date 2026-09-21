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

    public function appendMessages(array $newMessages, int $maxHistory = 30): void
    {
        $messages = $this->messages ?? [];
        foreach ($newMessages as $m) {
            $messages[] = $m;
        }

        $this->messages = static::pruneMessages($messages, $maxHistory);
        $this->last_activity_at = now();
        $this->save();
    }

    /**
     * Slide message history while strictly preserving assistant tool_calls and tool result parity.
     */
    public static function pruneMessages(array $messages, int $maxMessages = 30): array
    {
        if (count($messages) <= $maxMessages) {
            return $messages;
        }

        $targetSlice = array_slice($messages, -$maxMessages);

        // Find the first 'user' turn boundary so we don't start with an orphaned 'tool' or assistant tool_use
        $firstUserIdx = null;
        foreach ($targetSlice as $idx => $msg) {
            if (($msg['role'] ?? '') === 'user') {
                $firstUserIdx = $idx;
                break;
            }
        }

        if ($firstUserIdx !== null && $firstUserIdx > 0) {
            $targetSlice = array_slice($targetSlice, $firstUserIdx);
        }

        return array_values($targetSlice);
    }

    public function clearMessages(): void
    {
        $this->messages = [];
        $this->last_activity_at = now();
        $this->save();
    }
}
