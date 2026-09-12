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
use App\Http\Controllers\SupportController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\LlmController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/support', [SupportController::class, 'index'])->name('support.index');

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
Route::get('/api/models/downloads', [ModelManagerController::class, 'downloadsApi'])->name('api.models.downloads');

// Queue
Route::get('/queue', [QueueController::class, 'index'])->name('queue.index');
Route::post('/queue/repair', [QueueController::class, 'repair'])->name('queue.repair');
Route::post('/queue/{id}/retry', [QueueController::class, 'retry'])->name('queue.retry');
Route::delete('/queue/{id}', [QueueController::class, 'destroy'])->name('queue.destroy');
Route::get('/api/queue/tasks', [QueueController::class, 'tasksApi'])->name('api.queue.tasks');

// Playlists
Route::get('/playlists', [PlaylistController::class, 'index'])->name('playlists.index');
Route::post('/playlists', [PlaylistController::class, 'store'])->name('playlists.store');
Route::delete('/playlists/{id}', [PlaylistController::class, 'destroy'])->name('playlists.destroy');
Route::post('/playlists/{id}/items', [PlaylistController::class, 'addItem'])->name('playlists.items.add');
Route::delete('/playlists/{id}/items/{itemId}', [PlaylistController::class, 'removeItem'])->name('playlists.items.remove');
Route::post('/playlists/{id}/config', [PlaylistController::class, 'updateConfig'])->name('playlists.config.update');
Route::post('/playlists/{id}/process', [PlaylistController::class, 'process'])->name('playlists.process');
Route::post('/playlists/{id}/items/{itemId}/process', [PlaylistController::class, 'processItem'])->name('playlists.items.process');
Route::get('/playlists/{id}/download-zip', [PlaylistController::class, 'downloadZip'])->name('playlists.download_zip');
Route::get('/api/playlists/{id}', [PlaylistController::class, 'apiPlaylist'])->name('api.playlists.show');

// Settings
Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
Route::post('/settings/browse-folder', [SettingsController::class, 'browseFolder'])->name('settings.browse_folder');

// API Key Management & Logs
Route::get('/settings/api-keys', [\App\Http\Controllers\ApiKeyManagementController::class, 'index'])->name('settings.api_keys.index');
Route::post('/settings/api-keys', [\App\Http\Controllers\ApiKeyManagementController::class, 'store'])->name('settings.api_keys.store');
Route::post('/settings/api-keys/{id}/toggle', [\App\Http\Controllers\ApiKeyManagementController::class, 'toggle'])->name('settings.api_keys.toggle');
Route::delete('/settings/api-keys/{id}', [\App\Http\Controllers\ApiKeyManagementController::class, 'destroy'])->name('settings.api_keys.destroy');
Route::get('/settings/api-keys/{id}/logs', [\App\Http\Controllers\ApiKeyManagementController::class, 'logs'])->name('settings.api_keys.logs');
Route::delete('/settings/api-keys/{id}/logs', [\App\Http\Controllers\ApiKeyManagementController::class, 'clearLogs'])->name('settings.api_keys.clear_logs');

// System API
Route::get('/api/system/stats', [SystemApiController::class, 'stats'])->name('api.system.stats');
Route::get('/api/system/operations', [SystemApiController::class, 'operations'])->name('api.system.operations');
Route::delete('/api/system/operations/{type}/{id}', [SystemApiController::class, 'cancelOperation'])->name('api.system.operations.cancel');
Route::get('/api/audio/{filename}', [SystemApiController::class, 'audio'])->name('api.audio');
Route::get('/api/tasks/{type}', [SystemApiController::class, 'tasks'])->name('api.tasks');
Route::delete('/api/tasks/{id}', [SystemApiController::class, 'deleteTask'])->name('api.tasks.delete');
Route::post('/api/system/open-url', [SystemApiController::class, 'openUrl'])->name('api.system.open_url');

// Prompts Management
Route::get('/prompts', [PromptController::class, 'index'])->name('prompts.index');
Route::post('/prompts', [PromptController::class, 'store'])->name('prompts.store');
Route::put('/prompts/{id}', [PromptController::class, 'update'])->name('prompts.update');
Route::post('/prompts/{id}/favorite', [PromptController::class, 'toggleFavorite'])->name('prompts.favorite');
Route::delete('/prompts/{id}', [PromptController::class, 'destroy'])->name('prompts.destroy');
Route::get('/api/prompts', [PromptController::class, 'apiIndex'])->name('api.prompts.index');

// LLM Text Generation & Connection Testing
Route::post('/api/llm/generate', [LlmController::class, 'generate'])->name('api.llm.generate');
Route::post('/api/llm/test-connection', [LlmController::class, 'testConnection'])->name('api.llm.test_connection');

