<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\DownloadModelJob;
use App\Models\ModelDownload;
use App\Services\PythonVoiceService;
use App\Services\QueueWorkerService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ModelApiController extends Controller
{
    #[OA\Get(
        path: '/api/v1/models',
        summary: 'Desteklenen AI modellerini listeleme',
        description: 'İndirme durumu ve boyutlarıyla birlikte desteklenen tüm AI modellerini döner.',
        security: [['ApiKeyAuth' => []]],
        tags: ['Models'],
        responses: [
            new OA\Response(response: 200, description: 'Model listesi'),
            new OA\Response(response: 401, description: 'Geçersiz veya eksik API anahtarı'),
        ]
    )]
    public function index(PythonVoiceService $service): JsonResponse
    {
        $models = $service->getAvailableModels();

        return response()->json([
            'success' => true,
            'models' => $models,
        ]);
    }

    #[OA\Post(
        path: '/api/v1/models/download/{modelId}',
        summary: 'Bir AI modeli için arka planda indirme tetikleme',
        security: [['ApiKeyAuth' => []]],
        tags: ['Models'],
        parameters: [
            new OA\Parameter(name: 'modelId', description: 'Model kimliği', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Model zaten hazır ya da indirme zaten sürüyor'),
            new OA\Response(response: 202, description: 'İndirme görevi kuyruğa alındı'),
            new OA\Response(response: 404, description: 'Model tanınmıyor'),
        ]
    )]
    public function download(string $modelId, PythonVoiceService $service): JsonResponse
    {
        $models = $service->getAvailableModels();
        $target = null;

        foreach ($models as $category => $items) {
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (($item['id'] ?? '') === $modelId) {
                        $target = $item;
                        break 2;
                    }
                }
            }
        }

        if (!$target) {
            return response()->json([
                'success' => false,
                'message' => "Model '{$modelId}' is not recognized.",
            ], 404);
        }

        if (!empty($target['is_ready'])) {
            return response()->json([
                'success' => true,
                'message' => "Model '{$modelId}' is already downloaded and ready.",
                'model' => $target,
            ]);
        }

        // Check if download is already in progress
        $existing = ModelDownload::where('model_id', $modelId)
            ->whereIn('status', ['pending', 'downloading'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => "Download is already in progress for '{$modelId}'.",
                'download_id' => $existing->id,
                'status' => $existing->status,
                'progress' => $existing->progress,
            ]);
        }

        $download = ModelDownload::create([
            'model_id' => $modelId,
            'status' => 'pending',
            'progress' => 0.0,
            'total_bytes' => $target['size_bytes'] ?? 0,
        ]);

        DownloadModelJob::dispatch($download->id)->onQueue('downloads');
        QueueWorkerService::ensureRunning();

        return response()->json([
            'success' => true,
            'message' => "Download task queued for model '{$modelId}'.",
            'download_id' => $download->id,
            'status' => 'pending',
        ], 202);
    }

    #[OA\Get(
        path: '/api/v1/models/downloads',
        summary: 'Aktif ve son model indirmelerinin durumu',
        security: [['ApiKeyAuth' => []]],
        tags: ['Models'],
        responses: [
            new OA\Response(response: 200, description: 'İndirme durumları listesi'),
        ]
    )]
    public function downloads(): JsonResponse
    {
        $downloads = ModelDownload::latest()->limit(20)->get();

        return response()->json([
            'success' => true,
            'downloads' => $downloads,
        ]);
    }
}
