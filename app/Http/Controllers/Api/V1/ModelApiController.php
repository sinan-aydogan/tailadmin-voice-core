<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\DownloadModelJob;
use App\Models\ModelDownload;
use App\Services\PythonVoiceService;
use App\Services\QueueWorkerService;
use Illuminate\Http\JsonResponse;

class ModelApiController extends Controller
{
    /**
     * List all supported AI models with download status and sizes.
     */
    public function index(PythonVoiceService $service): JsonResponse
    {
        $models = $service->getAvailableModels();

        return response()->json([
            'success' => true,
            'models' => $models,
        ]);
    }

    /**
     * Trigger background download for a specific AI model.
     */
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

    /**
     * Get status of active and recent model downloads.
     */
    public function downloads(): JsonResponse
    {
        $downloads = ModelDownload::latest()->limit(20)->get();

        return response()->json([
            'success' => true,
            'downloads' => $downloads,
        ]);
    }
}
