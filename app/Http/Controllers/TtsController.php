<?php

namespace App\Http\Controllers;

use App\Models\VoiceTask;
use App\Models\VoiceProfile;
use App\Jobs\GenerateTtsJob;
use App\Services\PythonVoiceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

class TtsController extends Controller
{
    public function index(PythonVoiceService $service): Response
    {
        $profiles = VoiceProfile::all();
        $tasks = VoiceTask::where('type', 'tts')->latest()->limit(15)->get();
        $models = $service->getAvailableModels();

        return Inertia::render('Tts/Index', [
            'profiles' => $profiles,
            'tasks' => $tasks,
            'models' => $models,
        ]);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'text' => 'required|string|min:1|max:5000',
            'engine' => 'required|string',
            'language' => 'required|string|max:10',
            'profile_id' => 'nullable|integer',
        ]);

        $profilePath = null;
        if (!empty($validated['profile_id'])) {
            $profile = VoiceProfile::find($validated['profile_id']);
            $profilePath = $profile?->sample_path;
        }

        $outputDir = base_path('data/outputs');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        $filename = 'tts_' . Str::random(12) . '.wav';
        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $filename;

        $task = VoiceTask::create([
            'type' => 'tts',
            'status' => 'pending',
            'payload' => [
                'text' => $validated['text'],
                'engine' => $validated['engine'],
                'language' => $validated['language'],
                'profile_id' => $validated['profile_id'] ?? null,
                'profile_path' => $profilePath,
                'output_path' => $outputPath,
                'filename' => $filename,
            ],
            'output_path' => $outputPath,
        ]);

        GenerateTtsJob::dispatch($task->id)->onQueue('default');
        \App\Services\QueueWorkerService::ensureRunning();

        return redirect()->back()->with('success', 'Ses üretimi kuyruğa eklendi.');
    }
}
