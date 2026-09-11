<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateTtsJob;
use App\Models\VoiceProfile;
use App\Models\VoiceTask;
use App\Services\PythonVoiceService;
use App\Services\QueueWorkerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TtsApiController extends Controller
{
    /**
     * Generate speech from text (TTS).
     */
    public function generate(Request $request, PythonVoiceService $service): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string|min:1|max:10000',
            'engine' => 'nullable|string',
            'language' => 'nullable|string|max:10',
            'profile_id' => 'nullable|integer',
            'sync' => 'nullable|boolean',
        ]);

        $engine = $validated['engine'] ?? 'piper-tr';
        $language = $validated['language'] ?? 'tr';
        $isSync = filter_var($validated['sync'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $profilePath = null;
        if (!empty($validated['profile_id'])) {
            $profile = VoiceProfile::find($validated['profile_id']);
            if (!$profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voice profile not found with ID: ' . $validated['profile_id'],
                ], 404);
            }
            $profilePath = $profile->sample_path;
        }

        $outputDir = base_path('data/outputs');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        $filename = 'tts_' . Str::random(12) . '.wav';
        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $filename;

        // Synchronous generation
        if ($isSync) {
            $task = VoiceTask::create([
                'type' => 'tts',
                'status' => 'running',
                'payload' => [
                    'text' => $validated['text'],
                    'engine' => $engine,
                    'language' => $language,
                    'profile_id' => $validated['profile_id'] ?? null,
                    'profile_path' => $profilePath,
                    'output_path' => $outputPath,
                    'filename' => $filename,
                ],
                'output_path' => $outputPath,
                'started_at' => now(),
            ]);

            try {
                $result = $service->generateTts($validated['text'], $engine, $language, $profilePath, $outputPath);

                $task->update([
                    'status' => 'completed',
                    'progress' => 100,
                    'result' => $result,
                    'output_path' => $result['output_path'] ?? $outputPath,
                    'completed_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'TTS generated successfully.',
                    'task_id' => $task->id,
                    'status' => 'completed',
                    'filename' => $filename,
                    'audio_url' => url("/api/v1/audio/{$filename}"),
                    'engine' => $engine,
                    'language' => $language,
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
                    'message' => 'TTS generation failed: ' . $e->getMessage(),
                    'task_id' => $task->id,
                    'status' => 'failed',
                ], 500);
            }
        }

        // Asynchronous generation via queue
        $task = VoiceTask::create([
            'type' => 'tts',
            'status' => 'pending',
            'payload' => [
                'text' => $validated['text'],
                'engine' => $engine,
                'language' => $language,
                'profile_id' => $validated['profile_id'] ?? null,
                'profile_path' => $profilePath,
                'output_path' => $outputPath,
                'filename' => $filename,
            ],
            'output_path' => $outputPath,
        ]);

        GenerateTtsJob::dispatch($task->id)->onQueue('default');
        QueueWorkerService::ensureRunning();

        return response()->json([
            'success' => true,
            'message' => 'TTS generation task queued successfully.',
            'task_id' => $task->id,
            'status' => 'pending',
            'poll_url' => url("/api/v1/tasks/{$task->id}"),
            'audio_url' => url("/api/v1/audio/{$filename}"),
        ], 202);
    }
}
