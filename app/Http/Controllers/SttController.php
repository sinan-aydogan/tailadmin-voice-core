<?php

namespace App\Http\Controllers;

use App\Models\VoiceTask;
use App\Jobs\TranscribeSttJob;
use App\Services\PythonVoiceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

class SttController extends Controller
{
    public function index(PythonVoiceService $voiceService): Response
    {
        $tasks = VoiceTask::where('type', 'stt')->latest()->limit(15)->get();

        return Inertia::render('Stt/Index', [
            'tasks' => $tasks,
            'default_model' => $voiceService->getDefaultSttModel(),
            'models' => $voiceService->getAvailableModels(),
        ]);
    }

    public function transcribe(Request $request, PythonVoiceService $voiceService)
    {
        $allowedExtensions = ['wav', 'mp3', 'ogg', 'm4a', 'flac', 'webm', 'weba', 'mp4', 'aac'];
        $validated = $request->validate([
            'audio' => [
                'required',
                'file',
                'max:51200', // max 50MB
                function ($attribute, $value, $fail) use ($allowedExtensions) {
                    if (!$value instanceof \Illuminate\Http\UploadedFile) {
                        $fail('Geçerli bir ses dosyası yüklenmelidir.');
                        return;
                    }
                    $ext = strtolower($value->getClientOriginalExtension() ?: pathinfo($value->getClientOriginalName(), PATHINFO_EXTENSION) ?: '');
                    if (!empty($ext) && !in_array($ext, $allowedExtensions)) {
                        $fail("Desteklenmeyen ses formatı (.{$ext}). Desteklenen formatlar: " . implode(', ', $allowedExtensions));
                    }
                }
            ],
            'language' => 'nullable|string|max:10',
            'model_size' => 'nullable|string|max:50',
            'engine' => 'nullable|string|max:50',
        ]);

        $file = $request->file('audio');
        $uploadDir = base_path('data/uploads');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'wav');
        $filename = 'stt_' . Str::random(12) . '.' . $ext;
        $filePath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        $file->move($uploadDir, $filename);

        $modelSize = $validated['model_size'] ?? $voiceService->getDefaultSttModel();
        $engine = $validated['engine'] ?? null;

        $task = VoiceTask::create([
            'type' => 'stt',
            'status' => 'pending',
            'payload' => [
                'audio_path' => $filePath,
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'language' => $validated['language'] ?? 'tr',
                'model_size' => $modelSize,
                'engine' => $engine,
            ],
        ]);

        TranscribeSttJob::dispatch($task->id)->onQueue('default');
        \App\Services\QueueWorkerService::ensureRunning();

        return redirect()->back()->with('success', 'Deşifre işlemi kuyruğa eklendi.');
    }
}
