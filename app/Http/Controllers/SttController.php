<?php

namespace App\Http\Controllers;

use App\Models\VoiceTask;
use App\Jobs\TranscribeSttJob;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

class SttController extends Controller
{
    public function index(): Response
    {
        $tasks = VoiceTask::where('type', 'stt')->latest()->limit(15)->get();

        return Inertia::render('Stt/Index', [
            'tasks' => $tasks,
        ]);
    }

    public function transcribe(Request $request)
    {
        $validated = $request->validate([
            'audio' => 'required|file|mimes:wav,mp3,ogg,m4a,flac|max:51200', // max 50MB
            'language' => 'nullable|string|max:10',
        ]);

        $file = $request->file('audio');
        $uploadDir = base_path('data/uploads');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = 'stt_' . Str::random(12) . '.' . $file->getClientOriginalExtension();
        $filePath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        $file->move($uploadDir, $filename);

        $task = VoiceTask::create([
            'type' => 'stt',
            'status' => 'pending',
            'payload' => [
                'audio_path' => $filePath,
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'language' => $validated['language'] ?? 'tr',
            ],
        ]);

        TranscribeSttJob::dispatch($task->id);

        return redirect()->back()->with('success', 'Deşifre işlemi kuyruğa eklendi.');
    }
}
