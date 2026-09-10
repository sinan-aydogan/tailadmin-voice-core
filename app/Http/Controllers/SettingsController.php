<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Index', [
            'settings' => [
                'use_gpu' => env('USE_GPU', 'auto'),
                'max_cpu_threads' => env('MAX_CPU_THREADS', 4),
                'default_tts_engine' => env('DEFAULT_TTS_ENGINE', 'piper-tr'),
                'default_language' => env('DEFAULT_TTS_LANGUAGE', 'tr'),
                'api_port' => env('API_PORT', 5001),
            ]
        ]);
    }

    public function update(Request $request)
    {
        return redirect()->back()->with('success', 'Ayarlar kaydedildi.');
    }
}
