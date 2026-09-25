<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryStudioTest extends TestCase
{
    use RefreshDatabase;
    public function test_story_generation_reports_error_when_llm_is_unreachable(): void
    {
        $response = $this->postJson('/api/story/generate-script', [
            'theme' => 'Uzayda kaybolan astronot ve yapay zeka robotu',
            'options' => [
                'llm_provider' => 'ollama',
                'llm_base_url' => 'http://127.0.0.1:54321', // definitely unreachable port
                'llm_model' => 'llama3:latest',
                'allow_simulation' => false,
            ],
        ]);

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertFalse($data['success']);
        $this->assertNotEmpty($data['error']);
        $this->assertEquals('ollama', $data['provider']);
        $this->assertStringContainsString('Ollama', $data['error']);
    }

    public function test_story_generation_succeeds_with_dynamic_contextual_simulation(): void
    {
        $theme = 'Baharın gelişiyle neşeli kuş cıvıltılarıyla uyanan masalsı orman';
        $response = $this->postJson('/api/story/generate-script', [
            'theme' => $theme,
            'options' => [
                'llm_provider' => 'ollama',
                'llm_base_url' => 'http://127.0.0.1:54321',
                'llm_model' => 'llama3:latest',
                'allow_simulation' => true,
            ],
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertNotNull($data['project']);
        $this->assertTrue($data['project']['script_data']['is_simulation'] ?? false);

        // Verify it dynamically adapted to forest / birds, NOT the hardcoded coughing cat cigarette story!
        $title = $data['project']['title'];
        $this->assertStringNotContainsString('Dumanlı Park', $title);
        $this->assertStringContainsString('Bahar', $title);
        $this->assertEquals('birds_chirping', $data['project']['script_data']['scenes'][0]['sfx'][0]['id']);
    }
}
