<?php

namespace App\Http\Controllers;

use App\Models\ModelDownload;
use App\Jobs\DownloadModelJob;
use App\Services\PythonVoiceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

        DownloadModelJob::dispatch($download->id);

        return redirect()->back()->with('success', "{$modelId} modeli indirme kuyruğuna alındı.");
    }
}
