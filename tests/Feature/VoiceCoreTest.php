<?php

namespace Tests\Feature;

use App\Jobs\GenerateTtsJob;
use App\Models\VoiceTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VoiceCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_tts_page_loads_successfully(): void
    {
        $response = $this->get('/tts');
        $response->assertStatus(200);
    }

    public function test_tts_generation_creates_task_and_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->post('/tts/generate', [
            'text' => 'Merhaba dünya bu bir testtir.',
            'engine' => 'piper-tr',
            'language' => 'tr',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('voice_tasks', [
            'type' => 'tts',
            'status' => 'pending',
        ]);

        $task = VoiceTask::first();
        Queue::assertPushed(GenerateTtsJob::class, function ($job) use ($task) {
            return $job->taskId === $task->id;
        });
    }

    public function test_queue_page_loads_successfully(): void
    {
        VoiceTask::create([
            'type' => 'tts',
            'status' => 'completed',
            'payload' => ['text' => 'Test', 'engine' => 'piper-tr'],
        ]);

        $response = $this->get('/queue');
        $response->assertStatus(200);
    }

    public function test_system_stats_api_returns_json(): void
    {
        $response = $this->get('/api/system/stats');
        $response->assertStatus(200);
        $response->assertJsonStructure(['cpu_pct', 'ram', 'disk', 'gpu']);
    }

    public function test_tts_job_execution_with_piper_engine(): void
    {
        $outputPath = base_path('data/outputs/test_job_audio.wav');
        if (file_exists($outputPath)) {
            @unlink($outputPath);
        }

        $task = VoiceTask::create([
            'type' => 'tts',
            'status' => 'pending',
            'payload' => [
                'text' => 'Bu bir test sesidir.',
                'engine' => 'piper-tr',
                'language' => 'tr',
                'output_path' => $outputPath,
            ],
        ]);

        $job = new GenerateTtsJob($task->id);
        $job->handle(app(\App\Services\PythonVoiceService::class));

        $task->refresh();
        $this->assertEquals('completed', $task->status);
        $this->assertEquals(100, $task->progress);
        $this->assertFileExists($outputPath);

        if (file_exists($outputPath)) {
            @unlink($outputPath);
        }
    }

    public function test_settings_can_update_api_key(): void
    {
        $response = $this->post('/settings', [
            'models_dir' => base_path('data/models'),
            'voice_core_api_key' => 'panel-generated-secret-key-999',
        ]);

        $response->assertRedirect();

        $settingsFile = base_path('data/settings.json');
        $this->assertFileExists($settingsFile);
        $content = json_decode(file_get_contents($settingsFile), true);
        $this->assertEquals('panel-generated-secret-key-999', $content['voice_core_api_key']);

        if (file_exists($settingsFile)) {
            @unlink($settingsFile);
        }
    }
}

