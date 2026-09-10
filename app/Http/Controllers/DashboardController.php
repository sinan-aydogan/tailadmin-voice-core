<?php

namespace App\Http\Controllers;

use App\Models\VoiceTask;
use App\Models\VoiceProfile;
use App\Models\ModelDownload;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $totalTasks = VoiceTask::count();
        $completedTasks = VoiceTask::where('status', 'completed')->count();
        $pendingTasks = VoiceTask::whereIn('status', ['pending', 'running'])->count();
        $totalProfiles = VoiceProfile::count();
        $completedDownloads = ModelDownload::where('status', 'completed')->count();

        $recentTasks = VoiceTask::latest()->limit(5)->get();

        return Inertia::render('Dashboard', [
            'metrics' => [
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'pending_tasks' => $pendingTasks,
                'total_profiles' => $totalProfiles,
                'downloaded_models' => $completedDownloads,
            ],
            'recentTasks' => $recentTasks,
        ]);
    }
}
