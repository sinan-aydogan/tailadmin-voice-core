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
        $this->assertArrayHasKey('/api/v1/flows/{slug}/run', $spec['paths']);
        $this->assertArrayHasKey('/api/v1/agents/{slug}/run', $spec['paths']);
    }

    public function test_in_app_documentation_page_loads_successfully(): void
    {
        $response = $this->get('/documentation');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Documentation/Index')
            ->has('apiUrl')
            ->has('swaggerUrl')
            ->has('docsJsonUrl')
        );
    }

    public function test_swagger_ui_page_references_static_bundle_not_dynamic_route(): void
    {
        // NativePHP's LivewireDispatcher listens on every RequestHandled response and
        // injects a script before the first "</head>"/"</html>" it finds. swagger-ui-bundle.js
        // contains that exact substring inside a minified string literal, so serving it through
        // Laravel's dynamic asset route corrupts the file and leaves the Swagger UI blank in
        // the desktop app. It must be served from the static public copy instead.
        $response = $this->get('/api/documentation');

        $response->assertStatus(200);
        $response->assertSee('/vendor/swagger-ui/swagger-ui-bundle.js', false);
        $response->assertDontSee('docs/asset/swagger-ui-bundle.js', false);
    }

    public function test_static_swagger_ui_bundle_matches_vendor_source_byte_for_byte(): void
    {
        $vendorFile = base_path('vendor/swagger-api/swagger-ui/dist/swagger-ui-bundle.js');
        $publicFile = public_path('vendor/swagger-ui/swagger-ui-bundle.js');

        $this->assertFileExists($publicFile);
        $this->assertSame(file_get_contents($vendorFile), file_get_contents($publicFile));
    }

    public function test_open_url_endpoint_supports_relative_documentation_path(): void
    {
        if (class_exists(\Native\Desktop\Facades\Shell::class)) {
            \Native\Desktop\Facades\Shell::fake();
        }

        $response = $this->postJson('/api/system/open-url', [
            'url' => '/api/documentation',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
