<?php

namespace Tests\Feature;

use App\Models\PromptTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_seed_and_list_prompt_templates(): void
    {
        $response = $this->get('/prompts');
        $response->assertStatus(200);

        $this->assertDatabaseCount('prompt_templates', 5);
    }

    public function test_api_prompts_returns_json_with_extracted_variables(): void
    {
        $response = $this->getJson('/api/prompts');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'templates' => [
                    '*' => [
                        'id',
                        'title',
                        'content',
                        'category',
                        'extracted_variables',
                    ]
                ]
            ]);

        $templates = $response->json('templates');
        $this->assertNotEmpty($templates);
        $first = $templates[0];
        $this->assertIsArray($first['extracted_variables']);
    }

    public function test_can_create_prompt_template_with_dynamic_variables(): void
    {
        $data = [
            'title' => 'Tavşan ve Karga Hikayesi',
            'category' => 'Hikaye',
            'content' => '$1 yaşındaki çocuklar için $2 temalı bir hikaye yaz. Karakterler $3 olsun.',
            'description' => 'Eğitici hayvan masalı',
            'system_prompt' => 'Sen bir masal yazarısın.',
            'is_favorite' => true,
        ];

        $response = $this->post('/prompts', $data);
        $response->assertStatus(302);

        $this->assertDatabaseHas('prompt_templates', [
            'title' => 'Tavşan ve Karga Hikayesi',
            'is_favorite' => 1,
        ]);

        $template = PromptTemplate::where('title', 'Tavşan ve Karga Hikayesi')->first();
        $this->assertEquals(['$1', '$2', '$3'], $template->extractVariables());

        $rendered = $template->renderPrompt([
            '$1' => '7',
            '$2' => 'paylaşmak',
            '$3' => 'tavşan ile karga',
        ]);

        $this->assertEquals('7 yaşındaki çocuklar için paylaşmak temalı bir hikaye yaz. Karakterler tavşan ile karga olsun.', $rendered);
    }

    public function test_can_update_prompt_template(): void
    {
        $template = PromptTemplate::create([
            'title' => 'Eski Başlık',
            'category' => 'Test',
            'content' => 'Test metni $1',
        ]);

        $response = $this->put("/prompts/{$template->id}", [
            'title' => 'Yeni Başlık',
            'category' => 'Güncel',
            'content' => 'Güncel metin $1 ve $2',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('prompt_templates', [
            'id' => $template->id,
            'title' => 'Yeni Başlık',
            'category' => 'Güncel',
        ]);
    }

    public function test_can_delete_prompt_template(): void
    {
        $template = PromptTemplate::create([
            'title' => 'Silinecek Şablon',
            'category' => 'Test',
            'content' => 'Silinecek $1',
        ]);

        $response = $this->delete("/prompts/{$template->id}");
        $response->assertStatus(302);

        $this->assertDatabaseMissing('prompt_templates', [
            'id' => $template->id,
        ]);
    }
}
