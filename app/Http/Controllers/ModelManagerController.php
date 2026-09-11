<?php

namespace App\Http\Controllers;

use App\Models\ModelDownload;
use App\Jobs\DownloadModelJob;
use App\Services\PythonVoiceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

use Illuminate\Http\JsonResponse;

class ModelManagerController extends Controller
{
    public function index(PythonVoiceService $service): Response
    {
        $availableModels = $service->getAvailableModels();
        $downloads = ModelDownload::latest()->get();

        return Inertia::render('Models/Index', [
            'availableModels' => $availableModels,
            'downloads' => $downloads,
        ]);
    }

    public function download(Request $request, $modelId)
    {
        $download = ModelDownload::updateOrCreate(
            ['model_id' => $modelId],
            [
                'status' => 'pending',
                'progress' => 0.0,
                'error_message' => null,
            ]
        );

        DownloadModelJob::dispatch($download->id)->onQueue('default');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$modelId} modeli indirme kuyruğuna alındı.",
                'download' => $download,
            ]);
        }

        return redirect()->back()->with('success', "{$modelId} modeli indirme kuyruğuna alındı.");
    }

    public function downloadsApi(PythonVoiceService $service): JsonResponse
    {
        $downloads = ModelDownload::latest()->get();
        $availableModels = $service->getAvailableModels();

        // Reconcile active downloads with real-time disk bytes
        foreach ($downloads as $d) {
            $targetModel = collect($availableModels)->firstWhere('id', $d->model_id);
            if ($targetModel && !empty($targetModel['is_downloaded'])) {
                if ($d->status !== 'completed') {
                    $d->update([
                        'status' => 'completed',
                        'progress' => 100,
                    ]);
                }
                continue;
            }

            if ($d->status === 'downloading' || $d->status === 'pending') {
                $isJobActive = \Illuminate\Support\Facades\DB::table('jobs')
                    ->where('payload', 'like', '%DownloadModelJob%')
                    ->where('payload', 'like', "%{$d->id}%")
                    ->exists();

                // If no background worker job exists and record is not newly created (older than 60s), mark stalled download as failed
                if (!$isJobActive && $d->updated_at && $d->updated_at->diffInSeconds(now()) > 60) {
                    $d->update([
                        'status' => 'failed',
                        'error_message' => 'İndirme işlemi kesintiye uğradı. "Tekrar İndirmeyi Dene" butonuna basarak indirmeyi yeniden başlatabilirsiniz.',
                    ]);
                    continue;
                }

                $dir = base_path('data/models/' . $d->model_id);
                if (is_dir($dir)) {
                    $bytes = PythonVoiceService::getDirectorySize($dir);
                    $estimateMb = $targetModel['size_estimate_mb'] ?? 1000;
                    $estimatedTotalBytes = $estimateMb * 1024 * 1024;
                    $totalBytes = max($estimatedTotalBytes, (int)($bytes * 1.05));

                    $pct = min(99.0, max(5.0, round(($bytes / $totalBytes) * 100, 1)));

                    if ($bytes > $d->downloaded_bytes || $pct > $d->progress || $totalBytes !== $d->total_bytes) {
                        $d->update([
                            'progress' => $pct,
                            'downloaded_bytes' => $bytes,
                            'total_bytes' => $totalBytes,
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'availableModels' => $availableModels,
            'downloads' => $downloads->fresh(),
        ]);
    }
}
