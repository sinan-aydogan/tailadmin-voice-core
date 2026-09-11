<?php

namespace Tests\Feature;

use App\Jobs\GenerateTtsJob;
use App\Models\VoiceProfile;
use App\Models\VoiceTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'app' => 'TailAdmin Voice Core',
            'version' => '1.0.0',
        ]);
        $response->assertJsonStructure([
            'status',
            'app',
            'version',
            'timestamp',
            'services' => ['api', 'python_engine', 'queue_worker'],
        ]);
    }

    public function test_api_models_endpoint_returns_json(): void
    {
        $response = $this->getJson('/api/v1/models');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'models',
        ]);
    }

    public function test_api_tts_generate_validation_errors(): void
    {
        $response = $this->postJson('/api/v1/tts/generate', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['text']);
    }

    public function test_api_tts_generate_queues_task(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/tts/generate', [
            'text' => 'Bu bir API test sesidir.',
            'engine' => 'piper-tr',
            'language' => 'tr',
        ]);

        $response->assertStatus(202);
        $response->assertJson([
            'success' => true,
            'status' => 'pending',
        ]);
        $response->assertJsonStructure([
            'success',
            'message',
            'task_id',
            'status',
            'poll_url',
            'audio_url',
        ]);

        $this->assertDatabaseHas('voice_tasks', [
            'type' => 'tts',
            'status' => 'pending',
        ]);

        $task = VoiceTask::first();
        Queue::assertPushed(GenerateTtsJob::class, function ($job) use ($task) {
            return $job->taskId === $task->id;
        });
    }

    public function test_api_tasks_index_and_show(): void
    {
        $task = VoiceTask::create([
            'type' => 'tts',
            'status' => 'completed',
            'payload' => [
                'text' => 'Hello World',
                'engine' => 'piper-en',
                'filename' => 'tts_test123.wav',
            ],
            'output_path' => base_path('data/outputs/tts_test123.wav'),
            'progress' => 100,
        ]);

        // Test Index
        $listResponse = $this->getJson('/api/v1/tasks?type=tts');
        $listResponse->assertStatus(200);
        $listResponse->assertJson([
            'success' => true,
            'count' => 1,
        ]);

        // Test Show
        $showResponse = $this->getJson("/api/v1/tasks/{$task->id}");
        $showResponse->assertStatus(200);
        $showResponse->assertJson([
            'success' => true,
            'task' => [
                'id' => $task->id,
                'type' => 'tts',
                'status' => 'completed',
                'progress' => 100,
            ],
        ]);
    }

    public function test_api_profiles_management(): void
    {
        // 1. Create Profile
        $createResponse = $this->postJson('/api/v1/profiles', [
            'name' => 'Spiker Ahmet',
            'description' => 'Haber spikeri ses tonu',
        ]);

        $createResponse->assertStatus(201);
        $createResponse->assertJson([
            'success' => true,
            'profile' => [
                'name' => 'Spiker Ahmet',
                'description' => 'Haber spikeri ses tonu',
            ],
        ]);

        $profileId = $createResponse->json('profile.id');

        // 2. List Profiles
        $listResponse = $this->getJson('/api/v1/profiles');
        $listResponse->assertStatus(200);
        $listResponse->assertJson([
            'success' => true,
            'count' => 1,
        ]);

        // 3. Delete Profile
        $deleteResponse = $this->deleteJson("/api/v1/profiles/{$profileId}");
        $deleteResponse->assertStatus(200);
        $deleteResponse->assertJson(['success' => true]);

        $this->assertDatabaseMissing('voice_profiles', ['id' => $profileId]);
    }

    public function test_api_key_authentication_when_configured(): void
    {
        Config::set('services.voice_core.api_key', 'super-secret-token-12345');

        // 1. Request without API key should be rejected with 401
        $rejectedResponse = $this->getJson('/api/v1/tasks');
        $rejectedResponse->assertStatus(401);
        $rejectedResponse->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Invalid or missing API key.',
        ]);

        // 2. Request with valid X-API-Key header should succeed
        $validHeaderResponse = $this->withHeader('X-API-Key', 'super-secret-token-12345')
            ->getJson('/api/v1/tasks');
        $validHeaderResponse->assertStatus(200);

        // 3. Request with valid Bearer token should succeed
        $validBearerResponse = $this->withToken('super-secret-token-12345')
            ->getJson('/api/v1/tasks');
        $validBearerResponse->assertStatus(200);

        // 4. Request with valid query parameter should succeed
        $validQueryResponse = $this->getJson('/api/v1/tasks?api_key=super-secret-token-12345');
        $validQueryResponse->assertStatus(200);

        // 5. Health check remains public even when API key is enabled
        $healthResponse = $this->getJson('/api/v1/health');
        $healthResponse->assertStatus(200);
    }
}
