<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SystemUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_check_update_endpoint_detects_newer_version(): void
    {
        Http::fake([
            'api.github.com/repos/sinan-aydogan/tailadmin-voice-core/releases/latest' => Http::response([
                'tag_name' => 'v99.0.0',
                'name' => 'Voice Core Release v99.0.0',
                'html_url' => 'https://github.com/sinan-aydogan/tailadmin-voice-core/releases/tag/v99.0.0',
                'published_at' => '2026-10-01T12:00:00Z',
                'body' => 'Brand new major release with incredible features',
            ], 200),
        ]);

        $response = $this->getJson('/api/system/check-update?force=1');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'latest_version' => '99.0.0',
            'latest_tag' => 'v99.0.0',
            'has_update' => true,
            'release_name' => 'Voice Core Release v99.0.0',
        ]);
    }

    public function test_check_update_endpoint_handles_same_or_older_version(): void
    {
        Http::fake([
            'api.github.com/repos/sinan-aydogan/tailadmin-voice-core/releases/latest' => Http::response([
                'tag_name' => 'v1.0.0',
                'name' => 'Voice Core Release v1.0.0',
                'html_url' => 'https://github.com/sinan-aydogan/tailadmin-voice-core/releases/tag/v1.0.0',
                'published_at' => '2026-01-01T12:00:00Z',
                'body' => 'Initial release',
            ], 200),
        ]);

        $response = $this->getJson('/api/system/check-update?force=1');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'latest_version' => '1.0.0',
            'has_update' => false,
        ]);
    }

    public function test_check_update_endpoint_handles_github_failure_gracefully(): void
    {
        Http::fake([
            'api.github.com/repos/sinan-aydogan/tailadmin-voice-core/releases/latest' => Http::response(null, 500),
        ]);

        $response = $this->getJson('/api/system/check-update?force=1');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'has_update' => false,
        ]);
    }
}
