<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DocumentationController extends Controller
{
    public function index(): Response
    {
        $activeKeysCount = 0;
        if (class_exists(\App\Models\ApiKey::class) && \Illuminate\Support\Facades\Schema::hasTable('api_keys')) {
            $activeKeysCount = \App\Models\ApiKey::where('is_active', true)->count();
        }

        return Inertia::render('Documentation/Index', [
            'apiUrl' => url('/api/v1'),
            'swaggerUrl' => url('/api/documentation'),
            'docsJsonUrl' => url('/docs'),
            'hasApiKeyAuth' => ! empty(config('services.voice_core.api_key')),
            'activeKeysCount' => $activeKeysCount,
        ]);
    }
}
