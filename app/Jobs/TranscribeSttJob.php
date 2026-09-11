<?php

namespace App\Jobs;

use App\Models\VoiceTask;
use App\Services\PythonVoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class TranscribeSttJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public int $taskId
    ) {}

    public function handle(PythonVoiceService $service): void
    {
        $task = VoiceTask::find($this->taskId);
        if (!$task) {
            return;
        }

        $task->update([
            'status' => 'running',
            'started_at' => now(),
            'progress' => 15,
        ]);

        try {
            $payload = $task->payload ?? [];
            $audioPath = $payload['audio_path'] ?? '';
            $language = $payload['language'] ?? 'tr';
            $modelSize = $payload['model_size'] ?? null;

            $result = $service->transcribeStt($audioPath, $language, $modelSize);

            $task->update([
                'status' => 'completed',
                'progress' => 100,
                'result' => $result,
                'completed_at' => now(),
            ]);

            Log::info("TranscribeSttJob #{$task->id} completed successfully.");
        } catch (\Throwable $e) {
            Log::error("TranscribeSttJob #{$task->id} failed: " . $e->getMessage());

            $task->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }
}
