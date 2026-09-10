<?php

namespace App\Jobs;

use App\Models\ModelDownload;
use App\Services\PythonVoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DownloadModelJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800; // 30 minutes for large models

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
            $result = $service->downloadModel($download->model_id);

            $download->update([
                'status' => 'completed',
                'progress' => 100.0,
                'error_message' => null,
            ]);

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
