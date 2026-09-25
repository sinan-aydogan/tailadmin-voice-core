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

    protected ?string $savedSettingsBackup = null;

    protected function setUp(): void
    {
        parent::setUp();
        $settingsFile = base_path('data/settings.json');
        if (file_exists($settingsFile)) {
            $this->savedSettingsBackup = file_get_contents($settingsFile);
        }
    }

    protected function tearDown(): void
    {
        $settingsFile = base_path('data/settings.json');
        if ($this->savedSettingsBackup !== null) {
            file_put_contents($settingsFile, $this->savedSettingsBackup);
        }
        parent::tearDown();
    }

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
    }

    public function test_tts_text_sanitizer_removes_markdown_and_voiceover_notes(): void
    {
        $raw = '**(Seslendirme Notu: Konuşma temposu orta hızlı, ritmik ve vurgular net olmalıdır.)** *** ### 🎤 Haber Spikeri Anons Metni **(Giriş – Enerjik ve Selamlayıcı)** Değerli izleyiciler, iyi akşamlar. **(Bülten – Dinamik ve Tarafsız Ton)** Bugün yapay zeka alanında kritik bir gelişmeye yer veriyoruz.';
        
        $clean = \App\Services\PythonVoiceService::sanitizeTextForTts($raw);

        $this->assertStringNotContainsString('**', $clean);
        $this->assertStringNotContainsString('###', $clean);
        $this->assertStringNotContainsString('Seslendirme Notu', $clean);
        $this->assertStringNotContainsString('Giriş – Enerjik', $clean);
        $this->assertStringNotContainsString('🎤', $clean);
        $this->assertStringContainsString('Değerli izleyiciler, iyi akşamlar.', $clean);
        $this->assertStringContainsString('Bugün yapay zeka alanında kritik bir gelişmeye yer veriyoruz.', $clean);
    }

    public function test_freya_cloud_model_download_and_api_key_handling(): void
    {
        // 1. Without API key, requesting download of cloud model should return helpful error and never divide by zero
        $res = $this->postJson('/models/download/freya-adam');
        $this->assertTrue(in_array($res->status(), [422, 200, 302]));

        // Check model download table doesn't have "Division by zero"
        $download = \App\Models\ModelDownload::where('model_id', 'freya-adam')->first();
        if ($download) {
            $this->assertStringNotContainsString('Division by zero', $download->error_message ?? '');
        }

        // 2. Save Freya API key via API endpoint
        $saveRes = $this->postJson('/api/models/freya-key', [
            'key' => 'freya_test_sample_key_12345'
        ]);
        $saveRes->assertStatus(200);
        $saveRes->assertJsonFragment(['success' => true]);

        // 3. Verify key is retrieved
        $getRes = $this->getJson('/api/models/freya-key');
        $getRes->assertStatus(200);
        $getRes->assertJsonFragment([
            'key' => 'freya_test_sample_key_12345',
            'is_configured' => true
        ]);

        // 4. Once key is saved, cloud models should be marked completed
        $cloudRecord = \App\Models\ModelDownload::where('model_id', 'freya-adam')->first();
        $this->assertNotNull($cloudRecord);
        $this->assertEquals('completed', $cloudRecord->status);
        $this->assertEquals(100.0, $cloudRecord->progress);

        // 5. Cleanup test key
        $this->postJson('/api/models/freya-key', ['key' => '']);
    }

    public function test_cloud_providers_keys_and_downloads_handling(): void
    {
        // 1. Without API key, requesting download of openai-tts-1 should return 422
        $res = $this->postJson('/models/download/openai-tts-1');
        $this->assertEquals(422, $res->status());
        $this->assertStringContainsString('OpenAI', $res->json()['message'] ?? '');

        // 2. Fetch all cloud keys
        $keysRes = $this->getJson('/api/models/cloud-keys');
        $keysRes->assertStatus(200);
        $this->assertTrue(isset($keysRes->json()['openai']));
        $this->assertTrue(isset($keysRes->json()['elevenlabs']));
        $this->assertTrue(isset($keysRes->json()['google']));
        $this->assertTrue(isset($keysRes->json()['groq']));

        // 3. Save OpenAI Key
        $saveRes = $this->postJson('/api/models/cloud-keys', [
            'provider' => 'openai',
            'key' => 'sk-test-openai-sample-key-123456789'
        ]);
        $saveRes->assertStatus(200);
        $saveRes->assertJsonFragment(['success' => true]);

        // 4. Verify openai models are marked completed
        $record = \App\Models\ModelDownload::where('model_id', 'openai-tts-1')->first();
        $this->assertNotNull($record);
        $this->assertEquals('completed', $record->status);

        // 5. Save ElevenLabs Key
        $saveRes11 = $this->postJson('/api/models/cloud-keys', [
            'provider' => 'elevenlabs',
            'key' => 'eleven-test-key-sample-123456'
        ]);
        $saveRes11->assertStatus(200);
        $record11 = \App\Models\ModelDownload::where('model_id', 'elevenlabs-multilingual')->first();
        $this->assertNotNull($record11);
        $this->assertEquals('completed', $record11->status);

        // Cleanup
        $this->postJson('/api/models/cloud-keys', ['provider' => 'openai', 'key' => '']);
        $this->postJson('/api/models/cloud-keys', ['provider' => 'elevenlabs', 'key' => '']);
    }

    public function test_models_directory_check_and_transfer_actions(): void
    {
        $checkRes = $this->postJson('/settings/check-models-directory');
        $checkRes->assertStatus(200);
        $checkRes->assertJsonStructure([
            'exists',
            'has_models',
            'models_count',
            'model_names',
            'total_size_bytes',
        ]);

        // Test update with move action
        $tempDirA = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'voice_test_models_a';
        $tempDirB = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'voice_test_models_b';

        @mkdir($tempDirA . DIRECTORY_SEPARATOR . 'mock-model', 0755, true);
        file_put_contents($tempDirA . DIRECTORY_SEPARATOR . 'mock-model' . DIRECTORY_SEPARATOR . 'model.bin', 'test-weights');

        // First set to tempDirA
        $this->postJson('/settings', [
            'models_dir' => $tempDirA,
            'model_transfer_action' => 'none',
        ])->assertStatus(200);

        // Move from tempDirA to tempDirB
        $moveRes = $this->postJson('/settings', [
            'models_dir' => $tempDirB,
            'model_transfer_action' => 'move',
        ]);
        $moveRes->assertStatus(200);
        $this->assertTrue(is_file($tempDirB . DIRECTORY_SEPARATOR . 'mock-model' . DIRECTORY_SEPARATOR . 'model.bin'));

        // Delete test
        $delRes = $this->postJson('/settings', [
            'models_dir' => base_path('data/models'),
            'model_transfer_action' => 'delete',
        ]);
        $delRes->assertStatus(200);
        $this->assertFalse(is_file($tempDirB . DIRECTORY_SEPARATOR . 'mock-model' . DIRECTORY_SEPARATOR . 'model.bin'));

        // Cleanup temp dirs
        \Illuminate\Support\Facades\File::deleteDirectory($tempDirA);
        \Illuminate\Support\Facades\File::deleteDirectory($tempDirB);
    }
}


