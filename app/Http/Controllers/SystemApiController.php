<?php

namespace App\Http\Controllers;

use App\Models\ModelDownload;
use App\Models\VoiceTask;
use App\Services\QueueWorkerService;
use App\Services\PythonVoiceService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SystemApiController extends Controller
{
    public function stats(PythonVoiceService $service): JsonResponse
    {
        return response()->json($service->getSystemStats());
    }

    public function operations(PythonVoiceService $voiceService): JsonResponse
    {
        $isWorkerRunning = QueueWorkerService::isRunning();

        // Model lookup dictionary for friendly names
        $knownModels = [
            'xtts-v2' => 'Coqui XTTS v2',
            'bark' => 'Suno Bark (Small)',
            'tortoise' => 'Tortoise TTS',
            'piper-tr' => 'Piper TTS (Turkish)',
            'piper-en' => 'Piper TTS (English)',
            'musicgen-small' => 'MusicGen (Small)',
            'musicgen-medium' => 'MusicGen (Medium)',
            'musicgen-large' => 'MusicGen (Large)',
            'musicgen-melody' => 'MusicGen (Melody)',
            'whisper-tiny' => 'Faster Whisper (Tiny)',
            'whisper-base' => 'Faster Whisper (Base)',
            'whisper-small' => 'Faster Whisper (Small)',
            'whisper-medium' => 'Faster Whisper (Medium)',
            'whisper-large-v3' => 'Faster Whisper (Large V3)',
            'qwen2.5-7b-instruct' => 'Qwen 2.5 7B Instruct',
            'llama-3.1-8b-instruct' => 'Llama 3.1 8B Instruct',
            'mistral-7b-instruct-v0.3' => 'Mistral 7B Instruct',
        ];

        // 1. Model downloads
        $downloads = ModelDownload::whereIn('status', ['downloading', 'pending'])->get();
        $formattedDownloads = [];
        foreach ($downloads as $d) {
            $name = $knownModels[$d->model_id] ?? ucwords(str_replace(['-', '_'], ' ', $d->model_id));

            if ($d->status === 'downloading') {
                $dir = base_path('data/models/' . $d->model_id);
                if (is_dir($dir)) {
                    $bytes = PythonVoiceService::getDirectorySize($dir);
                    if ($bytes > $d->downloaded_bytes && $d->total_bytes > 0) {
                        $pct = min(99.0, max(5.0, round(($bytes / $d->total_bytes) * 100, 1)));
                        $d->downloaded_bytes = $bytes;
                        $d->progress = $pct;
                    }
                }
            }

            $formattedDownloads[] = [
                'id' => 'dl_' . $d->id,
                'raw_id' => $d->id,
                'type' => 'model_download',
                'model_id' => $d->model_id,
                'title' => $name,
                'detail' => $d->status === 'downloading'
                    ? 'Model indiriliyor (%' . number_format($d->progress, 1) . ')'
                    : 'İndirme kuyrukta bekliyor',
                'status' => $d->status,
                'progress' => (float) $d->progress,
                'downloaded_bytes' => (int) $d->downloaded_bytes,
                'total_bytes' => (int) $d->total_bytes,
                'created_at' => $d->created_at?->toIso8601String(),
            ];
        }

        // 2. Active & pending voice tasks
        $tasks = VoiceTask::whereIn('status', ['running', 'pending'])->oldest()->get();
        $formattedTasks = [];
        foreach ($tasks as $t) {
            $payload = $t->payload ?? [];
            $isTts = $t->type === 'tts';
            $title = $isTts ? 'Ses Üretimi (TTS)' : 'Sesten Metne (STT)';
            $detail = $isTts
                ? ($payload['text'] ?? 'Metin Okuma')
                : ($payload['original_name'] ?? 'Ses Dosyası');

            $formattedTasks[] = [
                'id' => 'task_' . $t->id,
                'raw_id' => $t->id,
                'type' => $t->type,
                'title' => $title,
                'detail' => $detail,
                'engine' => $payload['engine'] ?? null,
                'language' => $payload['language'] ?? null,
                'status' => $t->status,
                'progress' => (int) $t->progress,
                'started_at' => $t->started_at?->toIso8601String(),
                'created_at' => $t->created_at?->toIso8601String(),
            ];
        }

        // Separate into active and queued
        $active = [];
        $queued = [];

        foreach ($formattedDownloads as $dl) {
            if ($dl['status'] === 'downloading') {
                $active[] = $dl;
            } else {
                $queued[] = $dl;
            }
        }

        foreach ($formattedTasks as $tsk) {
            if ($tsk['status'] === 'running') {
                $active[] = $tsk;
            } else {
                $queued[] = $tsk;
            }
        }

        // Recent 6 completed or failed tasks
        $recentTasks = VoiceTask::whereIn('status', ['completed', 'failed'])
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(function ($t) {
                $payload = $t->payload ?? [];
                $filename = $payload['filename'] ?? ($t->result['filename'] ?? null);
                if (!$filename && $t->output_path) {
                    $filename = basename($t->output_path);
                }

                return [
                    'id' => 'task_' . $t->id,
                    'raw_id' => $t->id,
                    'type' => $t->type,
                    'title' => $t->type === 'tts' ? 'Ses Üretimi' : 'Sesten Metne',
                    'detail' => $t->type === 'tts' ? ($payload['text'] ?? '') : ($payload['original_name'] ?? ''),
                    'status' => $t->status,
                    'error_message' => $t->error_message,
                    'audio_url' => $filename ? '/api/audio/' . $filename : null,
                    'completed_at' => $t->completed_at?->toIso8601String() ?? $t->updated_at?->toIso8601String(),
                ];
            })->all();

        // Primary active operation for footer display
        $primary = !empty($active) ? $active[0] : null;

        return response()->json([
            'is_worker_running' => $isWorkerRunning,
            'active_count' => count($active),
            'queued_count' => count($queued),
            'primary_operation' => $primary,
            'active_operations' => $active,
            'queued_operations' => $queued,
            'recent_operations' => $recentTasks,
        ]);
    }

    public function cancelOperation(string $type, int $id): JsonResponse
    {
        if ($type === 'model_download' || $type === 'download') {
            $dl = ModelDownload::find($id);
            if ($dl) {
                $dl->delete();
                return response()->json(['success' => true]);
            }
        } else {
            return $this->deleteTask($id);
        }

        return response()->json(['success' => false, 'message' => 'Operation not found'], 404);
    }

    public function tasks(string $type): JsonResponse
    {
        $tasks = VoiceTask::where('type', $type)->latest()->limit(25)->get();
        return response()->json($tasks);
    }

    public function deleteTask(int $id): JsonResponse
    {
        $task = VoiceTask::find($id);
        if ($task) {
            if ($task->output_path && file_exists($task->output_path)) {
                @unlink($task->output_path);
            }
            if (!empty($task->payload['audio_path']) && file_exists($task->payload['audio_path'])) {
                @unlink($task->payload['audio_path']);
            }
            $task->delete();
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false, 'message' => 'Task not found'], 404);
    }

    public function audio(string $filename): BinaryFileResponse
    {
        $possiblePaths = [
            base_path('data/outputs/' . $filename),
            base_path('data/profiles/' . $filename),
            base_path('data/uploads/' . $filename),
        ];

        $path = null;
        foreach ($possiblePaths as $p) {
            if (file_exists($p)) {
                $path = $p;
                break;
            }
        }

        if (!$path) {
            abort(404, 'Audio file not found');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $contentType = match ($extension) {
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            'webm' => 'audio/webm',
            'm4a' => 'audio/mp4',
            default => 'audio/wav',
        };

        return response()->file($path, [
            'Content-Type' => $contentType,
            'Accept-Ranges' => 'bytes',
        ]);
    }

    public function openUrl(\Illuminate\Http\Request $request): JsonResponse
    {
        $url = $request->input('url');
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['success' => false, 'message' => 'Invalid URL'], 422);
        }

        try {
            if (class_exists(\Native\Desktop\Facades\Shell::class)) {
                \Native\Desktop\Facades\Shell::openExternal($url);
            }
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true]);
    }
}
