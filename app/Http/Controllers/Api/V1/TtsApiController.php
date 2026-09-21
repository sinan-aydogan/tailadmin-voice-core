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
use OpenApi\Attributes as OA;

class TtsApiController extends Controller
{
    #[OA\Post(
        path: '/api/v1/tts/generate',
        summary: 'Metinden ses üretimi (TTS)',
        description: 'Metni yapay zeka modelleriyle sese dönüştürür. `sync=false` (varsayılan) isteği kuyruğa atar ve `task_id` döner; `sync=true` üretim tamamlanana kadar bekler ve sonucu doğrudan döner.',
        security: [['ApiKeyAuth' => []]],
        tags: ['TTS'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['text'],
                properties: [
                    new OA\Property(property: 'text', type: 'string', maxLength: 10000, description: 'Seslendirilecek metin'),
                    new OA\Property(property: 'engine', type: 'string', example: 'piper-tr', description: 'TTS motoru (piper-tr, piper-en, xtts-v2, bark, tortoise, musicgen-small)'),
                    new OA\Property(property: 'language', type: 'string', example: 'tr', description: 'Dil kodu'),
                    new OA\Property(property: 'profile_id', type: 'integer', nullable: true, description: 'Ses klonlama (XTTS) için referans profil ID'),
                    new OA\Property(property: 'sync', type: 'boolean', default: false, description: 'true ise senkron, false ise kuyruk modunda çalışır'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Senkron üretim tamamlandı'),
            new OA\Response(response: 202, description: 'Görev kuyruğa alındı (asenkron)'),
            new OA\Response(response: 404, description: 'Belirtilen ses profili bulunamadı'),
            new OA\Response(response: 422, description: 'Doğrulama hatası'),
            new OA\Response(response: 500, description: 'TTS üretimi başarısız oldu'),
        ]
    )]
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
