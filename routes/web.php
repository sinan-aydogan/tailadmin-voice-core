<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TtsController;
use App\Http\Controllers\SttController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ModelManagerController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SystemApiController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// TTS
Route::get('/tts', [TtsController::class, 'index'])->name('tts.index');
Route::post('/tts/generate', [TtsController::class, 'generate'])->name('tts.generate');

// STT
Route::get('/stt', [SttController::class, 'index'])->name('stt.index');
Route::post('/stt/transcribe', [SttController::class, 'transcribe'])->name('stt.transcribe');

// Profiles
Route::get('/profiles', [ProfileController::class, 'index'])->name('profiles.index');
Route::post('/profiles', [ProfileController::class, 'store'])->name('profiles.store');
Route::delete('/profiles/{id}', [ProfileController::class, 'destroy'])->name('profiles.destroy');

// Models
Route::get('/models', [ModelManagerController::class, 'index'])->name('models.index');
Route::post('/models/download/{modelId}', [ModelManagerController::class, 'download'])->name('models.download');

// Queue
Route::get('/queue', [QueueController::class, 'index'])->name('queue.index');
Route::delete('/queue/{id}', [QueueController::class, 'destroy'])->name('queue.destroy');

// Playlists
Route::get('/playlists', [PlaylistController::class, 'index'])->name('playlists.index');
Route::post('/playlists', [PlaylistController::class, 'store'])->name('playlists.store');

// Settings
Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');

// System API
Route::get('/api/system/stats', [SystemApiController::class, 'stats'])->name('api.system.stats');
Route::get('/api/audio/{filename}', [SystemApiController::class, 'audio'])->name('api.audio');

