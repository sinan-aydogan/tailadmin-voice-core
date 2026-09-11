<?php

namespace App\Jobs;

use App\Models\VoiceTask;
use App\Services\PythonVoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateTtsJob implements ShouldQueue
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
            'progress' => 10,
        ]);

        try {
            $payload = $task->payload ?? [];
            $text = $payload['text'] ?? '';
            $engine = $payload['engine'] ?? 'piper-tr';
            $language = $payload['language'] ?? 'tr';
            $profilePath = $payload['profile_path'] ?? null;
            $outputPath = $payload['output_path'] ?? null;

            $result = $service->generateTts($text, $engine, $language, $profilePath, $outputPath);

            $task->update([
                'status' => 'completed',
                'progress' => 100,
                'result' => $result,
                'output_path' => $result['output_path'] ?? $outputPath,
                'completed_at' => now(),
            ]);

            if (!empty($payload['playlist_id'])) {
                $playlist = \App\Models\VoicePlaylist::find($payload['playlist_id']);
                $playlist?->syncItemStatuses();
            }

            Log::info("GenerateTtsJob #{$task->id} completed successfully.");
        } catch (\Throwable $e) {
            Log::error("GenerateTtsJob #{$task->id} failed: " . $e->getMessage());

            $task->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            if (!empty($task->payload['playlist_id'])) {
                $playlist = \App\Models\VoicePlaylist::find($task->payload['playlist_id']);
                $playlist?->syncItemStatuses();
            }

            throw $e;
        }
    }
}
