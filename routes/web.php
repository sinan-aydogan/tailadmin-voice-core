<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentationController;
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
use App\Http\Controllers\FlowController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\AgentController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/documentation', [DocumentationController::class, 'index'])->name('documentation.index');
Route::get('/docs/asset/{asset}', [\App\Http\Controllers\SwaggerAssetSanitizerController::class, 'show'])->name('l5-swagger.default.asset');
Route::get('/support', [SupportController::class, 'index'])->name('support.index');

// TTS
Route::get('/tts', [TtsController::class, 'index'])->name('tts.index');
Route::post('/tts/generate', [TtsController::class, 'generate'])->name('tts.generate');

// STT
Route::get('/stt', [SttController::class, 'index'])->name('stt.index');
Route::post('/stt/transcribe', [SttController::class, 'transcribe'])->name('stt.transcribe');

// SFX Studio (Ses Efektleri)
Route::get('/sfx', [\App\Http\Controllers\SfxController::class, 'index'])->name('sfx.index');
Route::post('/api/sfx/generate', [\App\Http\Controllers\SfxController::class, 'generate'])->name('api.sfx.generate');
Route::post('/api/sfx/enhance-prompt', [\App\Http\Controllers\SfxController::class, 'enhancePrompt'])->name('api.sfx.enhance_prompt');
Route::get('/api/sfx/library', [\App\Http\Controllers\SfxController::class, 'getLibrary'])->name('api.sfx.library');
Route::get('/api/sfx/audio/{filename}', [\App\Http\Controllers\SfxController::class, 'streamAudio'])->name('api.sfx.audio');
Route::delete('/api/sfx/{filename}', [\App\Http\Controllers\SfxController::class, 'destroy'])->name('api.sfx.destroy');

// Music Studio (Müzik Üretimi & BGM)
Route::get('/music', [\App\Http\Controllers\MusicController::class, 'index'])->name('music.index');
Route::post('/api/music/generate', [\App\Http\Controllers\MusicController::class, 'generate'])->name('api.music.generate');
Route::post('/api/music/enhance-prompt', [\App\Http\Controllers\MusicController::class, 'enhancePrompt'])->name('api.music.enhance_prompt');
Route::get('/api/music/library', [\App\Http\Controllers\MusicController::class, 'getLibrary'])->name('api.music.library');
Route::get('/api/music/audio/{filename}', [\App\Http\Controllers\MusicController::class, 'streamAudio'])->name('api.music.audio');
Route::delete('/api/music/{filename}', [\App\Http\Controllers\MusicController::class, 'destroy'])->name('api.music.destroy');

// Profiles
Route::get('/profiles', [ProfileController::class, 'index'])->name('profiles.index');
Route::post('/profiles', [ProfileController::class, 'store'])->name('profiles.store');
Route::delete('/profiles/{id}', [ProfileController::class, 'destroy'])->name('profiles.destroy');

// Models
Route::get('/models', [ModelManagerController::class, 'index'])->name('models.index');
Route::post('/models/download/{modelId}', [ModelManagerController::class, 'download'])->name('models.download');
Route::get('/api/models/downloads', [ModelManagerController::class, 'downloadsApi'])->name('api.models.downloads');
Route::get('/api/models/cloud-keys', [ModelManagerController::class, 'getCloudKeys'])->name('api.models.cloud_keys.get');
Route::post('/api/models/cloud-keys', [ModelManagerController::class, 'saveCloudKey'])->name('api.models.cloud_keys.save');
Route::post('/api/models/cloud-key', [ModelManagerController::class, 'saveCloudKey'])->name('api.models.cloud_key.save');
Route::post('/api/models/test-cloud-connection', [ModelManagerController::class, 'testCloudConnection'])->name('api.models.test_cloud_connection');
Route::post('/api/models/test-cloud', [ModelManagerController::class, 'testCloudConnection'])->name('api.models.test_cloud');
Route::get('/api/models/freya-key', [ModelManagerController::class, 'getFreyaKey'])->name('api.models.freya_key.get');
Route::post('/api/models/freya-key', [ModelManagerController::class, 'saveFreyaKey'])->name('api.models.freya_key.save');

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
Route::post('/settings/check-models-directory', [SettingsController::class, 'checkModelsDirectory'])->name('settings.check_models_directory');
Route::post('/settings/test-freya-connection', [SettingsController::class, 'testFreyaConnection'])->name('settings.test_freya_connection');

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

// Story Studio & Director (AI Hikaye & Ses Tiyatrosu Makinesi)
Route::post('/api/story/generate-script', [\App\Http\Controllers\StoryStudioController::class, 'generateScript'])->name('api.story.generate_script');
Route::post('/api/story/produce/{id}', [\App\Http\Controllers\StoryStudioController::class, 'produce'])->name('api.story.produce');
Route::get('/api/story/projects', [\App\Http\Controllers\StoryStudioController::class, 'getProjects'])->name('api.story.projects');
Route::get('/api/story/projects/{id}', [\App\Http\Controllers\StoryStudioController::class, 'getProject'])->name('api.story.project');
Route::get('/api/story/audio/{id}/{type?}', [\App\Http\Controllers\StoryStudioController::class, 'getAudio'])->name('api.story.audio');
Route::get('/api/story/sfx-catalog', [\App\Http\Controllers\StoryStudioController::class, 'getSfxCatalog'])->name('api.story.sfx_catalog');
Route::get('/api/story/bgm-catalog', [\App\Http\Controllers\StoryStudioController::class, 'getBgmCatalog'])->name('api.story.bgm_catalog');
Route::delete('/api/story/projects/{id}', [\App\Http\Controllers\StoryStudioController::class, 'destroy'])->name('api.story.destroy');

// LLM Text Generation & Connection Testing
Route::post('/api/llm/generate', [LlmController::class, 'generate'])->name('api.llm.generate');
Route::post('/api/llm/test-connection', [LlmController::class, 'testConnection'])->name('api.llm.test_connection');

// Flows (Automation Builder)
Route::get('/flows', [FlowController::class, 'index'])->name('flows.index');
Route::post('/flows', [FlowController::class, 'store'])->name('flows.store');
Route::get('/flows/{id}/edit', [FlowController::class, 'edit'])->name('flows.edit');
Route::put('/flows/{id}', [FlowController::class, 'update'])->name('flows.update');
Route::post('/flows/{id}/toggle', [FlowController::class, 'toggle'])->name('flows.toggle');
Route::delete('/flows/{id}', [FlowController::class, 'destroy'])->name('flows.destroy');
Route::post('/flows/{id}/test-run', [FlowController::class, 'testRun'])->name('flows.test_run');

// Knowledge Base (RAG documents)
Route::get('/knowledge', [KnowledgeController::class, 'index'])->name('knowledge.index');
Route::post('/knowledge', [KnowledgeController::class, 'store'])->name('knowledge.store');
Route::get('/knowledge/{id}', [KnowledgeController::class, 'show'])->name('knowledge.show');
Route::delete('/knowledge/{id}', [KnowledgeController::class, 'destroy'])->name('knowledge.destroy');

// Agents (Flows-as-tools, LLM tool-calling loop)
Route::get('/agents', [AgentController::class, 'index'])->name('agents.index');
Route::post('/agents', [AgentController::class, 'store'])->name('agents.store');
Route::get('/agents/{id}/edit', [AgentController::class, 'edit'])->name('agents.edit');
Route::get('/agents/{id}/voice', [AgentController::class, 'voice'])->name('agents.voice');
Route::put('/agents/{id}', [AgentController::class, 'update'])->name('agents.update');
Route::post('/agents/{id}/toggle', [AgentController::class, 'toggle'])->name('agents.toggle');
Route::delete('/agents/{id}', [AgentController::class, 'destroy'])->name('agents.destroy');
Route::post('/agents/{id}/duplicate', [AgentController::class, 'duplicate'])->name('agents.duplicate');
Route::get('/agents/{id}/runs', [AgentController::class, 'runs'])->name('agents.runs');
Route::post('/agents/{id}/clear-session', [AgentController::class, 'clearSession'])->name('agents.clear_session');
Route::post('/agents/{id}/test-run', [AgentController::class, 'testRun'])->name('agents.test_run');

