<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    public function test_swagger_ui_page_loads_successfully(): void
    {
        $response = $this->get('/api/documentation');

        $response->assertStatus(200);
    }

    public function test_openapi_json_document_lists_v1_endpoints(): void
    {
        $response = $this->get('/docs');

        $response->assertStatus(200);

        $spec = $response->json();

        $this->assertSame('TailAdmin Voice Core API', $spec['info']['title']);
        $this->assertArrayHasKey('/api/v1/health', $spec['paths']);
        $this->assertArrayHasKey('/api/v1/tts/generate', $spec['paths']);
        $this->assertArrayHasKey('/api/v1/stt/transcribe', $spec['paths']);
        $this->assertArrayHasKey('/api/v1/tasks', $spec['paths']);
        $this->assertArrayHasKey('/api/v1/models', $spec['paths']);
        $this->assertArrayHasKey('/api/v1/profiles', $spec['paths']);
    }
}
