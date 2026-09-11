<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PythonVoiceService;
use App\Services\QueueWorkerService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SystemApiController extends Controller
{
    /**
     * Health check endpoint for monitoring, Docker and Kubernetes.
     */
    public function health(PythonVoiceService $service): JsonResponse
    {
        $engineOnline = $service->isServiceOnline();
        $workerRunning = QueueWorkerService::isRunning();

        return response()->json([
            'status' => 'ok',
            'app' => 'TailAdmin Voice Core',
            'version' => '1.0.0',
            'timestamp' => now()->toIso8601String(),
            'services' => [
                'api' => 'healthy',
                'python_engine' => $engineOnline ? 'online' : 'offline',
                'queue_worker' => $workerRunning ? 'running' : 'stopped',
            ],
        ]);
    }

    /**
     * Real-time hardware and process statistics.
     */
    public function stats(PythonVoiceService $service): JsonResponse
    {
        $stats = $service->getSystemStats();
        $stats['queue_worker_running'] = QueueWorkerService::isRunning();

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * Stream or download audio output/upload files.
     */
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
            'flac' => 'audio/flac',
            default => 'audio/wav',
        };

        return response()->file($path, [
            'Content-Type' => $contentType,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
