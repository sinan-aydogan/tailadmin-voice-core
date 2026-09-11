<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\TranscribeSttJob;
use App\Models\VoiceTask;
use App\Services\PythonVoiceService;
use App\Services\QueueWorkerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SttApiController extends Controller
{
    /**
     * Transcribe speech from audio file (STT).
     */
    public function transcribe(Request $request, PythonVoiceService $service): JsonResponse
    {
        $validated = $request->validate([
            'audio' => 'required|file|mimes:wav,mp3,ogg,m4a,flac,webm|max:102400', // max 100MB
            'language' => 'nullable|string|max:10',
            'model_size' => 'nullable|string|max:20',
            'sync' => 'nullable|boolean',
        ]);

        $file = $request->file('audio');
        $uploadDir = base_path('data/uploads');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = 'stt_' . Str::random(12) . '.' . $file->getClientOriginalExtension();
        $filePath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        $file->move($uploadDir, $filename);

        $language = $validated['language'] ?? 'tr';
        $modelSize = $validated['model_size'] ?? null;
        $isSync = filter_var($validated['sync'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Synchronous transcription
        if ($isSync) {
            $task = VoiceTask::create([
                'type' => 'stt',
                'status' => 'running',
                'payload' => [
                    'audio_path' => $filePath,
                    'filename' => $filename,
                    'original_name' => $file->getClientOriginalName(),
                    'language' => $language,
                    'model_size' => $modelSize,
                ],
                'started_at' => now(),
            ]);

            try {
                $result = $service->transcribeStt($filePath, $language, $modelSize);

                $task->update([
                    'status' => 'completed',
                    'progress' => 100,
                    'result' => $result,
                    'completed_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Audio transcribed successfully.',
                    'task_id' => $task->id,
                    'status' => 'completed',
                    'text' => $result['text'] ?? '',
                    'language' => $result['language'] ?? $language,
                    'duration' => $result['duration'] ?? null,
                    'segments' => $result['segments'] ?? [],
                    'result' => $result,
                ]);
            } catch (\Throwable $e) {
                $task->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => now(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Transcription failed: ' . $e->getMessage(),
                    'task_id' => $task->id,
                    'status' => 'failed',
                ], 500);
            }
        }

        // Asynchronous transcription via queue
        $task = VoiceTask::create([
            'type' => 'stt',
            'status' => 'pending',
            'payload' => [
                'audio_path' => $filePath,
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'language' => $language,
                'model_size' => $modelSize,
            ],
        ]);

        TranscribeSttJob::dispatch($task->id)->onQueue('default');
        QueueWorkerService::ensureRunning();

        return response()->json([
            'success' => true,
            'message' => 'Transcription task queued successfully.',
            'task_id' => $task->id,
            'status' => 'pending',
            'poll_url' => url("/api/v1/tasks/{$task->id}"),
        ], 202);
    }
}
