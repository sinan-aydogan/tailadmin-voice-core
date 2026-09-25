<?php

namespace App\Jobs;

use App\Models\ModelDownload;
use App\Services\PythonVoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DownloadModelJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600; // 60 minutes for large multi-gigabyte models

    public function __construct(
        public int $downloadId
    ) {}

    public function handle(PythonVoiceService $service): void
    {
        $download = ModelDownload::find($this->downloadId);
        if (!$download) {
            return;
        }

        $download->update([
            'status' => 'downloading',
            'progress' => 5.0,
        ]);

        try {
            Log::info("Starting DownloadModelJob for {$download->model_id}...");
            $result = $service->downloadModel($download->model_id, function ($pct, $downloadedBytes, $totalBytes) use ($download) {
                $download->update([
                    'status' => 'downloading',
                    'progress' => $pct,
                    'downloaded_bytes' => $downloadedBytes,
                    'total_bytes' => $totalBytes,
                ]);
            });

            $download->update([
                'status' => 'completed',
                'progress' => 100.0,
                'error_message' => null,
            ]);

            Cache::forget('voice_available_models');

            Log::info("DownloadModelJob for {$download->model_id} completed successfully.");
        } catch (\Throwable $e) {
            Log::error("DownloadModelJob for {$download->model_id} failed: " . $e->getMessage());

            $download->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
