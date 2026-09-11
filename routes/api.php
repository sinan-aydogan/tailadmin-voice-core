<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SystemApiController;
use App\Http\Controllers\Api\V1\TtsApiController;
use App\Http\Controllers\Api\V1\SttApiController;
use App\Http\Controllers\Api\V1\TaskApiController;
use App\Http\Controllers\Api\V1\ModelApiController;
use App\Http\Controllers\Api\V1\ProfileApiController;

/*
|--------------------------------------------------------------------------
| TailAdmin Voice Core - Public / Unauthenticated API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    Route::get('/health', [SystemApiController::class, 'health'])->name('api.v1.health');
    Route::get('/audio/{filename}', [SystemApiController::class, 'audio'])->name('api.v1.audio');
});

/*
|--------------------------------------------------------------------------
| TailAdmin Voice Core - REST API v1 (Protected by api.key middleware)
|--------------------------------------------------------------------------
| If VOICE_CORE_API_KEY is defined in .env, requires X-API-Key or Bearer token.
| If VOICE_CORE_API_KEY is empty, allows unrestricted access (local/desktop mode).
*/

Route::prefix('v1')->middleware(['api.key'])->group(function () {
    // System
    Route::get('/system/stats', [SystemApiController::class, 'stats'])->name('api.v1.system.stats');

    // Text to Speech (TTS)
    Route::post('/tts/generate', [TtsApiController::class, 'generate'])->name('api.v1.tts.generate');

    // Speech to Text (STT)
    Route::post('/stt/transcribe', [SttApiController::class, 'transcribe'])->name('api.v1.stt.transcribe');

    // Voice Tasks
    Route::get('/tasks', [TaskApiController::class, 'index'])->name('api.v1.tasks.index');
    Route::get('/tasks/{id}', [TaskApiController::class, 'show'])->name('api.v1.tasks.show');
    Route::delete('/tasks/{id}', [TaskApiController::class, 'destroy'])->name('api.v1.tasks.destroy');

    // AI Models
    Route::get('/models', [ModelApiController::class, 'index'])->name('api.v1.models.index');
    Route::post('/models/download/{modelId}', [ModelApiController::class, 'download'])->name('api.v1.models.download');
    Route::get('/models/downloads', [ModelApiController::class, 'downloads'])->name('api.v1.models.downloads');

    // Voice Profiles
    Route::get('/profiles', [ProfileApiController::class, 'index'])->name('api.v1.profiles.index');
    Route::post('/profiles', [ProfileApiController::class, 'store'])->name('api.v1.profiles.store');
    Route::delete('/profiles/{id}', [ProfileApiController::class, 'destroy'])->name('api.v1.profiles.destroy');
});
