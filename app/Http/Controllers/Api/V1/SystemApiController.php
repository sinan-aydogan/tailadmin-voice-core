<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PythonVoiceService;
use App\Services\QueueWorkerService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SystemApiController extends Controller
{
    #[OA\Get(
        path: '/api/v1/health',
        summary: 'Servis sağlık kontrolü',
        description: 'API, Python motoru ve kuyruk işçisinin durumunu döner. İzleme, Docker ve Kubernetes readiness/liveness kontrolleri için uygundur.',
        tags: ['System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sağlık durumu',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'ok'),
                        new OA\Property(property: 'app', type: 'string', example: 'TailAdmin Voice Core'),
                        new OA\Property(property: 'version', type: 'string', example: '1.0.0'),
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                        new OA\Property(
                            property: 'services',
                            properties: [
                                new OA\Property(property: 'api', type: 'string', example: 'healthy'),
                                new OA\Property(property: 'python_engine', type: 'string', example: 'online'),
                                new OA\Property(property: 'queue_worker', type: 'string', example: 'running'),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
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

    #[OA\Get(
        path: '/api/v1/system/stats',
        summary: 'Donanım ve süreç istatistikleri',
        description: 'CPU, RAM, disk ve GPU kullanımı ile kuyruk işçisi durumunu anlık olarak döner.',
        security: [['ApiKeyAuth' => []]],
        tags: ['System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sistem istatistikleri',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'stats', type: 'object'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Geçersiz veya eksik API anahtarı'),
        ]
    )]
    public function stats(PythonVoiceService $service): JsonResponse
    {
        $stats = $service->getSystemStats();
        $stats['queue_worker_running'] = QueueWorkerService::isRunning();

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    #[OA\Get(
        path: '/api/v1/audio/{filename}',
        summary: 'Ses dosyası oynatma / indirme',
        description: 'Üretilen (TTS çıktısı) veya yüklenen (STT/profil) ses dosyasını akış olarak döner. API anahtarı `api_key` URL parametresi ile de gönderilebilir (`<audio src>` oynatıcıları için).',
        tags: ['System'],
        parameters: [
            new OA\Parameter(
                name: 'filename',
                description: 'Ses dosyasının adı',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ses dosyası içeriği',
                content: new OA\MediaType(mediaType: 'audio/wav', schema: new OA\Schema(type: 'string', format: 'binary'))
            ),
            new OA\Response(response: 404, description: 'Dosya bulunamadı'),
        ]
    )]
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
