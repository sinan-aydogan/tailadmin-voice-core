<?php

namespace App\Jobs;

use App\Models\StoryProject;
use App\Services\StoryDirectorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProduceStoryJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(
        public int $projectId
    ) {}

    public function handle(StoryDirectorService $director): void
    {
        $project = StoryProject::find($this->projectId);
        if (!$project) {
            Log::warning("ProduceStoryJob: Project #{$this->projectId} not found.");
            return;
        }

        Log::info("ProduceStoryJob #{$project->id} started for story '{$project->title}'.");

        try {
            $director->produceProjectAudio($project);
            Log::info("ProduceStoryJob #{$project->id} finished successfully. Status: {$project->status}");
        } catch (\Throwable $e) {
            Log::error("ProduceStoryJob #{$project->id} failed: " . $e->getMessage());
            $project->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'progress_step' => 'Hata: ' . $e->getMessage(),
            ]);
        }
    }
}
