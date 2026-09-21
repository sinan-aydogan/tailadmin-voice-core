<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\AgentSession;
use App\Models\Flow;
use App\Services\Agent\AgentExecutorService;
use App\Services\Flow\FlowExecutorService;
use App\Services\LlmService;
use App\Services\PythonVoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_creation_and_auto_slug(): void
    {
        $agent = Agent::create([
            'name' => 'Müşteri Hizmetleri Ajanı',
            'is_active' => true,
        ]);

        $this->assertNotEmpty($agent->trigger_slug);
        $this->assertEquals(24, strlen($agent->trigger_slug));
        $this->assertIsArray($agent->tools);
        $this->assertEmpty($agent->tools);
    }

    public function test_agent_api_key_encryption_and_fallback(): void
    {
        $agent = Agent::create([
            'name' => 'Güvenli Ajan',
            'api_key' => 'sk-secret-test-key-12345',
        ]);

        // Raw attribute stored in DB is encrypted (not equal to raw plaintext)
        $raw = $agent->getAttributes()['api_key'];
        $this->assertNotEquals('sk-secret-test-key-12345', $raw);

        // Accessor decrypts back to plaintext
        $this->assertEquals('sk-secret-test-key-12345', $agent->api_key);

        // Plaintext fallback if DB has unencrypted string
        $agent->setRawAttributes(array_merge($agent->getAttributes(), ['api_key' => 'plain-old-key']));
        $this->assertEquals('plain-old-key', $agent->api_key);
    }

    public function test_agent_duplicate(): void
    {
        $agent = Agent::create([
            'name' => 'Sipariş Asistanı',
            'system_prompt' => 'Sen bir sipariş asistanısın.',
            'provider' => 'mock',
            'model' => 'mock-v1',
            'tools' => [
                ['name' => 'check_order', 'flow_id' => 1, 'description' => 'Sipariş kontrol'],
            ],
        ]);

        $clone = $agent->duplicate();

        $this->assertNotEquals($agent->id, $clone->id);
        $this->assertEquals('Sipariş Asistanı (Kopya)', $clone->name);
        $this->assertNotEquals($agent->trigger_slug, $clone->trigger_slug);
        $this->assertEquals($agent->system_prompt, $clone->system_prompt);
        $this->assertEquals($agent->tools, $clone->tools);
    }

    public function test_agent_session_sliding_window_preserves_tool_turn_integrity(): void
    {
        $agent = Agent::create(['name' => 'Hafıza Ajanı']);
        $session = AgentSession::create([
            'agent_id' => $agent->id,
            'conversation_id' => 'sess_sliding_test',
            'messages' => [],
        ]);

        // Build 40 messages (exceeding window of 30)
        // Ensure pairs of user -> assistant(with tool_calls) -> tool
        $messages = [];
        for ($i = 1; $i <= 10; $i++) {
            $messages[] = ['role' => 'user', 'content' => "Kullanıcı sorusu {$i}"];
            $messages[] = [
                'role' => 'assistant',
                'content' => '',
                'tool_calls' => [
                    [
                        'id' => "call_{$i}",
                        'type' => 'function',
                        'function' => ['name' => 'search', 'arguments' => '{"q":"test"}'],
                    ],
                ],
            ];
            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => "call_{$i}",
                'name' => 'search',
                'content' => "Sonuç {$i}",
            ];
            $messages[] = ['role' => 'assistant', 'content' => "Cevap {$i}"];
        }

        $this->assertCount(40, $messages);

        // Appending to session should prune cleanly without orphaning tool calls
        $session->appendMessages($messages, 30);
        $pruned = $session->fresh()->messages;

        $this->assertLessThanOrEqual(30, count($pruned));
        // First message of pruned window must be a user turn
        $this->assertEquals('user', $pruned[0]['role']);

        // No tool response should exist without prior tool_calls
        $toolCallIds = [];
        foreach ($pruned as $msg) {
            if (!empty($msg['tool_calls'])) {
                foreach ($msg['tool_calls'] as $tc) {
                    $toolCallIds[$tc['id']] = true;
                }
            }
            if ($msg['role'] === 'tool') {
                $this->assertArrayHasKey($msg['tool_call_id'], $toolCallIds, 'Tool result exists without corresponding tool_call in window');
            }
        }
    }

    public function test_agent_session_clear(): void
    {
        $agent = Agent::create(['name' => 'Oturum Temizleme Ajanı']);
        $session = AgentSession::create([
            'agent_id' => $agent->id,
            'conversation_id' => 'sess_clear_test',
            'messages' => [
                ['role' => 'user', 'content' => 'Merhaba'],
                ['role' => 'assistant', 'content' => 'Selam!'],
            ],
        ]);

        $this->assertCount(2, $session->messages);
        $session->clearMessages();
        $this->assertEmpty($session->fresh()->messages);
    }

    public function test_claude_merges_consecutive_tool_results_into_single_user_turn(): void
    {
        $llmService = new LlmService();
        $reflection = new \ReflectionClass($llmService);
        $method = $reflection->getMethod('toAnthropicMessages');
        $method->setAccessible(true);

        $messages = [
            ['role' => 'user', 'content' => 'Hava ve döviz durumunu getir'],
            [
                'role' => 'assistant',
                'content' => '',
                'tool_calls' => [
                    ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'weather', 'arguments' => '{"city":"Ankara"}']],
                    ['id' => 'call_2', 'type' => 'function', 'function' => ['name' => 'currency', 'arguments' => '{"pair":"USDTRY"}']],
                ],
            ],
            ['role' => 'tool', 'tool_call_id' => 'call_1', 'name' => 'weather', 'content' => 'Güneşli 22C'],
            ['role' => 'tool', 'tool_call_id' => 'call_2', 'name' => 'currency', 'content' => '34.50'],
        ];

        $dummySystem = null;
        $args = [$messages, &$dummySystem];
        $converted = $method->invokeArgs($llmService, $args);

        // In Anthropic API, consecutive tool results must be merged into ONE 'user' turn with multiple tool_result blocks
        $this->assertCount(3, $converted); // user, assistant, user (merged tool results)
        $this->assertEquals('user', $converted[0]['role']);
        $this->assertEquals('assistant', $converted[1]['role']);
        $this->assertEquals('user', $converted[2]['role']);

        $contentBlocks = $converted[2]['content'];
        $this->assertCount(2, $contentBlocks);
        $this->assertEquals('tool_result', $contentBlocks[0]['type']);
        $this->assertEquals('call_1', $contentBlocks[0]['tool_use_id']);
        $this->assertEquals('tool_result', $contentBlocks[1]['type']);
        $this->assertEquals('call_2', $contentBlocks[1]['tool_use_id']);
    }

    public function test_gemini_merges_consecutive_tool_results_into_single_function_turn(): void
    {
        $llmService = new LlmService();
        $reflection = new \ReflectionClass($llmService);
        $method = $reflection->getMethod('toGeminiContents');
        $method->setAccessible(true);

        $messages = [
            ['role' => 'user', 'content' => 'İki işlem yap'],
            [
                'role' => 'assistant',
                'content' => '',
                'tool_calls' => [
                    ['id' => 'call_a', 'type' => 'function', 'function' => ['name' => 'tool_a', 'arguments' => '{}']],
                    ['id' => 'call_b', 'type' => 'function', 'function' => ['name' => 'tool_b', 'arguments' => '{}']],
                ],
            ],
            ['role' => 'tool', 'tool_call_id' => 'call_a', 'name' => 'tool_a', 'content' => 'Result A'],
            ['role' => 'tool', 'tool_call_id' => 'call_b', 'name' => 'tool_b', 'content' => 'Result B'],
        ];

        $dummySystem = null;
        $args = [$messages, &$dummySystem];
        $contents = $method->invokeArgs($llmService, $args);

        $this->assertCount(3, $contents); // user, model, function
        $this->assertEquals('user', $contents[0]['role']);
        $this->assertEquals('model', $contents[1]['role']);
        $this->assertEquals('function', $contents[2]['role']);

        $parts = $contents[2]['parts'];
        $this->assertCount(2, $parts);
        $this->assertArrayHasKey('functionResponse', $parts[0]);
        $this->assertEquals('tool_a', $parts[0]['functionResponse']['name']);
        $this->assertArrayHasKey('functionResponse', $parts[1]);
        $this->assertEquals('tool_b', $parts[1]['functionResponse']['name']);
    }

    public function test_agent_silence_handling_returns_polite_spoken_fallback(): void
    {
        $tempAudio = tempnam(sys_get_temp_dir(), 'test_audio_');
        file_put_contents($tempAudio, str_repeat('0', 200));

        $voiceMock = $this->createMock(PythonVoiceService::class);
        $voiceMock->method('transcribeStt')->willReturn(['text' => '   ']); // empty / silence transcription

        $this->instance(PythonVoiceService::class, $voiceMock);
        $executor = app(AgentExecutorService::class);

        $agent = Agent::create([
            'name' => 'Ses Ajanı',
            'stt_enabled' => true,
            'response_mode' => 'text',
        ]);

        $run = AgentRun::create([
            'agent_id' => $agent->id,
            'status' => 'running',
            'trigger_payload' => ['audio_path' => $tempAudio],
        ]);

        try {
            $result = $executor->run($agent, ['audio_path' => $tempAudio], $run);

            $this->assertNotEmpty($result['reply_text']);
            $this->assertStringContainsString('Sesiniz net anlaşılamadı', $result['reply_text']);
        } finally {
            @unlink($tempAudio);
        }
    }

    public function test_agent_flow_output_fallback_when_flow_has_no_output_node(): void
    {
        // Create a Flow that performs an action but doesn't have an output.response node
        $flow = Flow::create([
            'name' => 'Aksiyon Akışı',
            'definition' => [
                'nodes' => [
                    ['id' => 'trig_1', 'type' => 'trigger.webhook', 'data' => []],
                    ['id' => 'action_1', 'type' => 'tool.echo', 'data' => ['message' => 'Node Çıktısı']],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'trig_1', 'target' => 'action_1'],
                ],
            ],
        ]);

        $executor = app(AgentExecutorService::class);
        $reflection = new \ReflectionClass($executor);
        $method = $reflection->getMethod('executeTools');
        $method->setAccessible(true);

        $agent = Agent::create([
            'name' => 'Araçlı Ajan',
            'tools' => [
                [
                    'name' => 'do_action',
                    'flow_id' => $flow->id,
                    'description' => 'Aksiyon çalıştırır',
                    'parameters' => ['type' => 'object', 'properties' => []],
                ],
            ],
        ]);

        $toolCalls = [
            [
                'id' => 'call_action',
                'name' => 'do_action',
                'arguments' => [],
            ],
        ];

        $run = AgentRun::create([
            'agent_id' => $agent->id,
            'status' => 'running',
            'trigger_payload' => [],
        ]);

        $dummyMessages = [];
        $dummyNewTurn = [];
        $args = [$toolCalls, $agent, $run, &$dummyMessages, &$dummyNewTurn];
        $method->invokeArgs($executor, $args);

        $this->assertCount(1, $dummyMessages);
        $this->assertEquals('call_action', $dummyMessages[0]['tool_call_id']);
        // Output should not be "null" because fallback extracts context data
        $this->assertNotEquals('null', $dummyMessages[0]['content']);
    }

    public function test_agent_routes_duplicate_runs_and_clear_session(): void
    {
        $agent = Agent::create([
            'name' => 'Web Route Ajanı',
            'trigger_slug' => 'test-web-route-agent',
        ]);

        // Duplicate
        $response = $this->post("/agents/{$agent->id}/duplicate");
        $response->assertRedirect();
        $this->assertDatabaseHas('agents', ['name' => 'Web Route Ajanı (Kopya)']);

        // Runs endpoint
        $run = AgentRun::create([
            'agent_id' => $agent->id,
            'status' => 'completed',
            'final_reply' => 'İşlem tamam',
            'final_output' => ['mode' => 'text', 'value' => 'İşlem tamam'],
        ]);

        $runsResponse = $this->getJson("/agents/{$agent->id}/runs");
        $runsResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
        $this->assertCount(1, $runsResponse->json('runs.data'));

        // Clear Session endpoint
        $session = AgentSession::create([
            'agent_id' => $agent->id,
            'conversation_id' => 'web_sess_1',
            'messages' => [['role' => 'user', 'content' => 'Test']],
        ]);

        $clearResponse = $this->postJson("/agents/{$agent->id}/clear-session", [
            'conversation_id' => 'web_sess_1',
        ]);
        $clearResponse->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEmpty($session->fresh()->messages);
    }

    public function test_agent_trigger_and_status_api(): void
    {
        $agent = Agent::create([
            'name' => 'API Tetiklenen Ajan',
            'provider' => 'mock',
            'model' => 'mock-v1',
            'system_prompt' => 'Sen bir test asistanısın.',
            'is_active' => true,
        ]);

        // Trigger Run synchronously
        $runResponse = $this->postJson("/api/v1/agents/{$agent->trigger_slug}/run", [
            'message' => 'Merhaba nasılsın?',
        ]);

        $runResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $runId = $runResponse->json('run_id');
        $this->assertNotNull($runId);

        // Verify final_output stored on AgentRun
        $run = AgentRun::find($runId);
        $this->assertNotNull($run->final_output);
        $this->assertEquals('completed', $run->status);

        // Status API poll
        $statusResponse = $this->getJson("/api/v1/agents/runs/{$runId}");
        $statusResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'run_id' => $runId,
                'status' => 'completed',
            ]);
    }
}
