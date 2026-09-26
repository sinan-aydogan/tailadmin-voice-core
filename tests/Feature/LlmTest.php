<?php

namespace Tests\Feature;

use App\Models\PromptTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LlmTest extends TestCase
{
    use RefreshDatabase;

    public function test_llm_generate_validation(): void
    {
        $response = $this->postJson('/api/llm/generate', []);
        $response->assertStatus(422);
    }

    public function test_llm_generate_with_free_prompt(): void
    {
        $response = $this->postJson('/api/llm/generate', [
            'prompt' => '5-12 yaş için tavşan ve karga hikayesi',
            'provider' => 'mock',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $text = $response->json('text');
        $this->assertNotEmpty($text);
        $this->assertStringContainsString('tavşan', mb_strtolower($text, 'UTF-8'));
    }

    public function test_llm_generate_with_template_and_variables(): void
    {
        $template = PromptTemplate::create([
            'title' => 'Özel Çocuk Masalı',
            'category' => 'Hikaye',
            'content' => '$1 yaş çocuklar için $2 konulu ve $3 kahramanlı masal.',
        ]);

        $response = $this->postJson('/api/llm/generate', [
            'template_id' => $template->id,
            'variables' => [
                '$1' => '8',
                '$2' => 'paylaşımcı olmak',
                '$3' => 'tavşan ile karga',
            ],
            'provider' => 'mock',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'compiled_prompt' => '8 yaş çocuklar için paylaşımcı olmak konulu ve tavşan ile karga kahramanlı masal.',
            ]);

        $this->assertNotEmpty($response->json('text'));
    }

    public function test_llm_test_connection(): void
    {
        $response = $this->postJson('/api/llm/test-connection', [
            'provider' => 'mock',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_claude_api_generation_with_fake_http(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://api.anthropic.com/v1/messages' => \Illuminate\Support\Facades\Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Tavşan ile bilge karga derin bir dostluk kurmuştu.'],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/llm/generate', [
            'prompt' => 'Tavşan ve karga hikayesi yaz',
            'provider' => 'claude',
            'api_key' => 'sk-ant-fake-key-12345',
            'model' => 'claude-3-5-sonnet-20241022',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'provider' => 'claude',
                'model' => 'claude-3-5-sonnet-20241022',
            ]);

        $this->assertStringContainsString('Tavşan ile bilge karga', $response->json('text'));
    }

    public function test_claude_api_test_connection_with_fake_http(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://api.anthropic.com/v1/messages' => \Illuminate\Support\Facades\Http::response([
                'id' => 'msg_test_connection_id',
            ], 200),
        ]);

        $response = $this->postJson('/api/llm/test-connection', [
            'provider' => 'claude',
            'api_key' => 'sk-ant-fake-key-12345',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
        $this->assertStringContainsString('Anthropic Claude', $response->json('message'));
    }

    public function test_openai_compatible_providers_generation_with_fake_http(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => \Illuminate\Support\Facades\Http::response([
                'choices' => [
                    ['message' => ['content' => 'Groq Llama 3.3 tarafından üretilen seslendirme metni.']],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/llm/generate', [
            'prompt' => 'Yeni haber bülteni',
            'provider' => 'groq',
            'api_key' => 'gsk_fake_groq_key',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'provider' => 'groq',
            ]);
        $this->assertStringContainsString('Groq Llama 3.3', $response->json('text'));
    }

    public function test_llm_generate_with_tts_engine_freya_shortcodes(): void
    {
        $response = $this->postJson('/api/llm/generate', [
            'prompt' => 'Tavşan ile karga hikayesi',
            'provider' => 'mock',
            'tts_engine' => 'freya-adam',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'tts_engine' => 'freya-adam',
            ]);

        $text = $response->json('text');
        $this->assertNotEmpty($text);
        $this->assertTrue(
            str_contains($text, '[pause]') || 
            str_contains($text, '[sigh]') || 
            str_contains($text, '[laughter]') || 
            str_contains($text, '[deep breath]')
        );
    }

    public function test_llm_generate_with_tts_engine_bark_shortcodes(): void
    {
        $response = $this->postJson('/api/llm/generate', [
            'prompt' => 'Tavşan ile karga hikayesi',
            'provider' => 'mock',
            'tts_engine' => 'bark',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'tts_engine' => 'bark',
            ]);

        $text = $response->json('text');
        $this->assertNotEmpty($text);
        $this->assertTrue(
            str_contains($text, '[laughter]') || 
            str_contains($text, '[sigh]') || 
            str_contains($text, '[gasp]') || 
            str_contains($text, '[whisper]')
        );
    }

    public function test_tts_model_features_endpoint(): void
    {
        $response = $this->getJson('/api/tts/models-with-features');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $models = $response->json('models');
        $this->assertIsArray($models);
        $this->assertNotEmpty($models);

        $freya = collect($models)->firstWhere('id', 'freya-adam');
        $this->assertNotNull($freya);
        $this->assertNotEmpty($freya['shortcodes']);
        $this->assertContains('[pause]', array_column($freya['shortcodes'], 'code'));

        $bark = collect($models)->firstWhere('id', 'bark');
        $this->assertNotNull($bark);
        $this->assertNotEmpty($bark['shortcodes']);
        $this->assertContains('[sigh]', array_column($bark['shortcodes'], 'code'));
        $this->assertContains('[laughter]', array_column($bark['shortcodes'], 'code'));
    }
}
