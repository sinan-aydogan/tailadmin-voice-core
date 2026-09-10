<?php

namespace App\Http\Controllers;

use App\Models\VoiceTask;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QueueController extends Controller
{
    public function index(): Response
    {
        $tasks = VoiceTask::latest()->paginate(25);

        return Inertia::render('Queue/Index', [
            'tasks' => $tasks,
        ]);
    }

    public function destroy($id)
    {
        $task = VoiceTask::findOrFail($id);
        if ($task->output_path && file_exists($task->output_path)) {
            @unlink($task->output_path);
        }
        $task->delete();

        return redirect()->back()->with('success', 'İşlem kuyruktan silindi.');
    }
}
