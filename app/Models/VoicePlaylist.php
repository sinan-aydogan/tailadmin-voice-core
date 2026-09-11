<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoicePlaylist extends Model
{
    protected $fillable = [
        'title',
        'description',
        'engine',
        'language',
        'profile_id',
        'status',
        'items',
    ];

    protected $casts = [
        'items' => 'array',
    ];

    protected $appends = [
        'total_items',
        'completed_items',
        'progress_percentage',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(VoiceProfile::class, 'profile_id');
    }

    public function getTotalItemsAttribute(): int
    {
        return count($this->items ?? []);
    }

    public function getCompletedItemsAttribute(): int
    {
        $items = $this->items ?? [];
        return count(array_filter($items, fn($i) => ($i['status'] ?? '') === 'completed'));
    }

    public function getProgressPercentageAttribute(): int
    {
        $total = $this->total_items;
        if ($total === 0) {
            return 0;
        }
        return (int) round(($this->completed_items / $total) * 100);
    }

    /**
     * Sync the status of playlist items with their associated VoiceTask records.
     */
    public function syncItemStatuses(): self
    {
        $items = $this->items ?? [];
        if (empty($items)) {
            return $this;
        }

        $taskIds = array_filter(array_column($items, 'task_id'));
        $tasks = !empty($taskIds) ? VoiceTask::whereIn('id', $taskIds)->get()->keyBy('id') : collect();
        $changed = false;
        $allCompleted = true;
        $anyProcessing = false;
        $anyFailed = false;
        $anyQueued = false;

        foreach ($items as &$item) {
            if (!empty($item['task_id']) && isset($tasks[$item['task_id']])) {
                $t = $tasks[$item['task_id']];
                $oldStatus = $item['status'] ?? null;
                $item['status'] = $t->status;
                if ($t->status === 'completed') {
                    $item['filename'] = $t->payload['filename'] ?? (isset($t->result['output_path']) ? basename($t->result['output_path']) : ($item['filename'] ?? null));
                    $item['error_message'] = null;
                } elseif ($t->status === 'failed') {
                    $item['error_message'] = $t->error_message;
                    $allCompleted = false;
                    $anyFailed = true;
                } else {
                    $allCompleted = false;
                    if ($t->status === 'running') {
                        $anyProcessing = true;
                    } elseif ($t->status === 'pending') {
                        $anyQueued = true;
                    }
                }

                if ($oldStatus !== $item['status']) {
                    $changed = true;
                }
            } else {
                // If item has no task_id and status is pending, normalize it to 'ready'
                if (empty($item['task_id']) && ($item['status'] ?? '') === 'pending') {
                    $item['status'] = 'ready';
                    $changed = true;
                }

                if (($item['status'] ?? 'ready') !== 'completed') {
                    $allCompleted = false;
                }
            }
        }
        unset($item);

        if ($allCompleted && count($items) > 0) {
            $this->status = 'completed';
        } elseif ($anyProcessing || $anyQueued) {
            $this->status = 'processing';
        } elseif ($anyFailed) {
            $this->status = 'failed';
        } else {
            $this->status = 'ready';
        }

        if ($changed || $this->isDirty('status')) {
            $this->items = $items;
            $this->save();
        }

        return $this;
    }
}
