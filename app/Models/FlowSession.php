<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowSession extends Model
{
    protected $fillable = [
        'flow_id',
        'conversation_id',
        'messages',
        'last_activity_at',
    ];

    protected $casts = [
        'messages' => 'array',
        'last_activity_at' => 'datetime',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }

    /**
     * Append a user/assistant turn and trim to the last $maxTurns exchanges.
     */
    public function appendTurn(string $userContent, string $assistantContent, int $maxTurns): void
    {
        $messages = $this->messages ?? [];
        $messages[] = ['role' => 'user', 'content' => $userContent, 'at' => now()->toIso8601String()];
        $messages[] = ['role' => 'assistant', 'content' => $assistantContent, 'at' => now()->toIso8601String()];

        $maxMessages = max(1, $maxTurns) * 2;
        if (count($messages) > $maxMessages) {
            $messages = array_slice($messages, -$maxMessages);
        }

        $this->messages = $messages;
        $this->last_activity_at = now();
        $this->save();
    }

    /**
     * Render the last $maxTurns exchanges as a plain-text transcript block.
     */
    public function renderHistory(int $maxTurns): string
    {
        $messages = $this->messages ?? [];
        $messages = array_slice($messages, -(max(1, $maxTurns) * 2));

        if (empty($messages)) {
            return '';
        }

        $lines = [];
        foreach ($messages as $m) {
            $label = ($m['role'] ?? 'user') === 'assistant' ? 'Asistan' : 'Kullanıcı';
            $lines[] = "{$label}: " . ($m['content'] ?? '');
        }

        return implode("\n", $lines);
    }
}
