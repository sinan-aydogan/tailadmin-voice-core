<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\ApiLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ApiKeyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_named_api_key(): void
    {
        $response = $this->postJson('/settings/api-keys', [
            'name' => 'Sureler Mobil Uygulama',
            'key' => 'vc_live_custom_test_key_123',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'api_key' => [
                'name' => 'Sureler Mobil Uygulama',
                'key' => 'vc_live_custom_test_key_123',
                'is_active' => true,
            ],
        ]);

        $this->assertDatabaseHas('api_keys', [
            'name' => 'Sureler Mobil Uygulama',
            'key' => 'vc_live_custom_test_key_123',
            'is_active' => true,
        ]);
    }

    public function test_can_create_api_key_with_auto_generated_key(): void
    {
        $response = $this->postJson('/settings/api-keys', [
            'name' => 'Otomasyon Botu',
        ]);

        $response->assertStatus(201);
        $apiKey = ApiKey::where('name', 'Otomasyon Botu')->first();
        $this->assertNotNull($apiKey);
        $this->assertStringStartsWith('vc_live_', $apiKey->key);
    }

    public function test_can_toggle_api_key_status(): void
    {
        $apiKey = ApiKey::create([
            'name' => 'Test Key',
            'key' => 'vc_live_test_toggle_key',
            'is_active' => true,
        ]);

        $response = $this->postJson("/settings/api-keys/{$apiKey->id}/toggle");
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'is_active' => false,
        ]);

        $this->assertFalse($apiKey->fresh()->is_active);

        // Toggle back to active
        $response = $this->postJson("/settings/api-keys/{$apiKey->id}/toggle");
        $response->assertStatus(200);
        $this->assertTrue($apiKey->fresh()->is_active);
    }

    public function test_api_requests_are_authenticated_and_logged(): void
    {
        Queue::fake();

        $apiKey = ApiKey::create([
            'name' => 'Mobil İstemci',
            'key' => 'vc_live_auth_and_log_key_777',
            'is_active' => true,
            'requests_count' => 0,
        ]);

        // Request with valid X-API-Key
        $response = $this->withHeaders([
            'X-API-Key' => 'vc_live_auth_and_log_key_777',
        ])->postJson('/api/v1/tts/generate', [
            'text' => 'İşlem geçmişi loglama testi.',
            'engine' => 'piper-tr',
            'language' => 'tr',
        ]);

        $response->assertStatus(202);
        $response->assertJson(['success' => true]);

        // Assert request count incremented
        $apiKey->refresh();
        $this->assertEquals(1, $apiKey->requests_count);
        $this->assertNotNull($apiKey->last_used_at);

        // Assert log was created
        $this->assertDatabaseHas('api_logs', [
            'api_key_id' => $apiKey->id,
            'action_type' => 'tts_generate',
            'endpoint' => '/api/v1/tts/generate',
            'method' => 'POST',
            'status_code' => 202,
        ]);

        $log = ApiLog::where('api_key_id', $apiKey->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('piper-tr', $log->request_summary['engine']);
        $this->assertEquals('İşlem geçmişi loglama testi.', $log->request_summary['text_preview']);
    }

    public function test_inactive_or_invalid_api_key_is_rejected(): void
    {
        ApiKey::create([
            'name' => 'Pasif Key',
            'key' => 'vc_live_inactive_key_999',
            'is_active' => false,
        ]);

        // Try with inactive key
        $response = $this->withHeaders([
            'X-API-Key' => 'vc_live_inactive_key_999',
        ])->postJson('/api/v1/tts/generate', [
            'text' => 'Bu istek reddedilmeli.',
        ]);

        $response->assertStatus(401);

        // Try with non-existent key
        $response = $this->withHeaders([
            'X-API-Key' => 'vc_live_completely_fake_key',
        ])->postJson('/api/v1/tts/generate', [
            'text' => 'Bu istek de reddedilmeli.',
        ]);

        $response->assertStatus(401);
    }

    public function test_can_fetch_and_clear_api_key_logs(): void
    {
        $apiKey = ApiKey::create([
            'name' => 'Log Test Key',
            'key' => 'vc_live_test_key_logs',
            'is_active' => true,
        ]);

        // Create some sample logs
        ApiLog::create([
            'api_key_id' => $apiKey->id,
            'action_type' => 'tts_generate',
            'endpoint' => '/api/v1/tts/generate',
            'method' => 'POST',
            'status_code' => 202,
            'request_summary' => ['engine' => 'piper-tr', 'text_preview' => 'Deneme'],
            'response_time_ms' => 45,
            'created_at' => now(),
        ]);

        ApiLog::create([
            'api_key_id' => $apiKey->id,
            'action_type' => 'tasks_index',
            'endpoint' => '/api/v1/tasks',
            'method' => 'GET',
            'status_code' => 200,
            'response_time_ms' => 12,
            'created_at' => now(),
        ]);

        // Fetch logs endpoint
        $response = $this->getJson("/settings/api-keys/{$apiKey->id}/logs");
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'api_key' => [
                'id' => $apiKey->id,
                'name' => 'Log Test Key',
            ],
            'stats' => [
                'total' => 2,
                'success' => 2,
                'error' => 0,
            ],
        ]);
        $response->assertJsonCount(2, 'logs');

        // Clear logs endpoint
        $clearResponse = $this->deleteJson("/settings/api-keys/{$apiKey->id}/logs");
        $clearResponse->assertStatus(200);
        $clearResponse->assertJson(['success' => true]);

        $this->assertEquals(0, ApiLog::where('api_key_id', $apiKey->id)->count());
    }

    public function test_can_delete_api_key(): void
    {
        $apiKey = ApiKey::create([
            'name' => 'Silinecek Key',
            'key' => 'vc_live_delete_me_soon',
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/settings/api-keys/{$apiKey->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('api_keys', [
            'id' => $apiKey->id,
        ]);
    }
}
