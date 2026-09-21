<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\VoiceTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TaskApiController extends Controller
{
    #[OA\Get(
        path: '/api/v1/tasks',
        summary: 'Ses görevlerini listeleme ve filtreleme',
        security: [['ApiKeyAuth' => []]],
        tags: ['Tasks'],
        parameters: [
            new OA\Parameter(name: 'type', description: 'Görev tipine göre filtre (tts, stt)', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', description: 'Duruma göre filtre (pending, running, completed, failed)', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limit', description: 'Maksimum kayıt sayısı (1-100, varsayılan 20)', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Görev listesi'),
            new OA\Response(response: 401, description: 'Geçersiz veya eksik API anahtarı'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = VoiceTask::query();

        if ($request->has('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $limit = min(100, max(1, (int) $request->query('limit', 20)));
        $tasks = $query->latest()->limit($limit)->get()->map(function ($task) {
            return $this->formatTask($task);
        });

        return response()->json([
            'success' => true,
            'count' => $tasks->count(),
            'tasks' => $tasks,
        ]);
    }

    #[OA\Get(
        path: '/api/v1/tasks/{id}',
        summary: 'Tek bir görevin detayını alma',
        security: [['ApiKeyAuth' => []]],
        tags: ['Tasks'],
        parameters: [
            new OA\Parameter(name: 'id', description: 'Görev ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Görev detayı'),
            new OA\Response(response: 404, description: 'Görev bulunamadı'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $task = VoiceTask::find($id);
        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => "Task not found with ID: {$id}",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'task' => $this->formatTask($task),
        ]);
    }

    #[OA\Delete(
        path: '/api/v1/tasks/{id}',
        summary: 'Görevi ve ilişkili dosyaları silme',
        security: [['ApiKeyAuth' => []]],
        tags: ['Tasks'],
        parameters: [
            new OA\Parameter(name: 'id', description: 'Görev ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Görev silindi'),
            new OA\Response(response: 404, description: 'Görev bulunamadı'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $task = VoiceTask::find($id);
        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => "Task not found with ID: {$id}",
            ], 404);
        }

        if ($task->output_path && file_exists($task->output_path)) {
            @unlink($task->output_path);
        }
        if (!empty($task->payload['audio_path']) && file_exists($task->payload['audio_path'])) {
            @unlink($task->payload['audio_path']);
        }

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => "Task #{$id} deleted successfully.",
        ]);
    }

    /**
     * Format task object for JSON response.
     */
    protected function formatTask(VoiceTask $task): array
    {
        $payload = $task->payload ?? [];
        $filename = $payload['filename'] ?? ($task->result['filename'] ?? null);
        if (!$filename && $task->output_path) {
            $filename = basename($task->output_path);
        }

        $audioUrl = $filename ? url("/api/v1/audio/{$filename}") : null;

        return [
            'id' => $task->id,
            'type' => $task->type,
            'status' => $task->status,
            'progress' => $task->progress ?? 0,
            'audio_url' => $audioUrl,
            'output_path' => $task->output_path,
            'payload' => $payload,
            'result' => $task->result,
            'error_message' => $task->error_message,
            'started_at' => $task->started_at?->toIso8601String(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
        ];
    }
}
