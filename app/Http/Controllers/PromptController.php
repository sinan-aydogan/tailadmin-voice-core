<?php

namespace App\Http\Controllers;

use App\Models\PromptTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromptController extends Controller
{
    /**
     * Display a listing of prompt templates.
     */
    public function index(Request $request, \App\Services\PythonVoiceService $voiceService): Response
    {
        PromptTemplate::seedDefaultsIfEmpty();

        $query = PromptTemplate::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            if ($category !== 'all') {
                $query->where('category', $category);
            }
        }

        $templates = $query->orderByDesc('is_favorite')
            ->orderByDesc('id')
            ->get()
            ->map(function ($t) {
                $t->extracted_variables = $t->extractVariables();
                return $t;
            });

        $categories = PromptTemplate::distinct()
            ->pluck('category')
            ->filter()
            ->values()
            ->toArray();

        $storyProjects = \App\Models\StoryProject::orderByDesc('id')->take(10)->get();
        $sfxCatalog = app(\App\Services\StoryDirectorService::class)->getAvailableSfx();
        $llmSettings = (new \App\Services\LlmService())->getSettings();

        $settingsFile = base_path('data' . DIRECTORY_SEPARATOR . 'settings.json');
        $st = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) ?: [] : [];
        $cloudKeys = [
            'freya' => !empty($st['freya_api_key'] ?? env('FREYA_API_KEY', '')),
            'openai' => !empty($st['openai_api_key'] ?? env('OPENAI_API_KEY', '')),
            'elevenlabs' => !empty($st['elevenlabs_api_key'] ?? env('ELEVENLABS_API_KEY', '')),
            'google' => !empty($st['google_cloud_api_key'] ?? env('GOOGLE_CLOUD_API_KEY', '')),
            'gemini' => !empty($st['gemini_api_key'] ?? $st['google_cloud_api_key'] ?? env('GEMINI_API_KEY') ?: env('GOOGLE_CLOUD_API_KEY', '')),
            'groq' => !empty($st['groq_api_key'] ?? env('GROQ_API_KEY', '')),
            'anthropic' => !empty($st['anthropic_api_key'] ?? env('ANTHROPIC_API_KEY', '')),
            'claude' => !empty($st['anthropic_api_key'] ?? env('ANTHROPIC_API_KEY', '')),
            'deepseek' => !empty($st['deepseek_api_key'] ?? env('DEEPSEEK_API_KEY', '')),
            'openrouter' => !empty($st['openrouter_api_key'] ?? env('OPENROUTER_API_KEY', '')),
        ];
        $enrichedModels = collect($voiceService->getAvailableModels())->map(function ($m) use ($cloudKeys) {
            $isCloud = !empty($m['is_cloud']);
            $provider = $m['cloud_provider'] ?? '';
            $isDownloaded = !empty($m['is_downloaded']);
            $isConfigured = $isCloud ? ($cloudKeys[$provider] ?? false) : $isDownloaded;
            $m['is_configured'] = $isConfigured;
            return $m;
        })->all();

        return Inertia::render('Prompts/Index', [
            'templates' => $templates,
            'categories' => $categories,
            'filters' => [
                'search' => $request->input('search', ''),
                'category' => $request->input('category', 'all'),
            ],
            'initial_projects' => $storyProjects,
            'available_models' => $enrichedModels,
            'sfx_catalog' => $sfxCatalog,
            'llm_info' => [
                'provider' => $llmSettings['llm_provider'],
                'model' => $llmSettings['llm_model'],
                'base_url' => $llmSettings['llm_base_url'],
                'has_api_key' => !empty($llmSettings['llm_api_key']),
                'api_key' => $llmSettings['llm_api_key'],
                'providers_config' => $llmSettings['llm_providers_config'] ?? [],
            ],
        ]);
    }

    /**
     * API endpoint to list templates for TTS modal and sidebar.
     */
    public function apiIndex(): JsonResponse
    {
        PromptTemplate::seedDefaultsIfEmpty();

        $templates = PromptTemplate::orderByDesc('is_favorite')
            ->orderBy('title')
            ->get()
            ->map(function ($t) {
                $t->extracted_variables = $t->extractVariables();
                return $t;
            });

        $llmSettings = (new \App\Services\LlmService())->getSettings();
        $ttsModels = (new \App\Services\TtsModelFeatures())->getAll();

        return response()->json([
            'success' => true,
            'templates' => $templates,
            'tts_models' => $ttsModels,
            'llm_info' => [
                'provider' => $llmSettings['llm_provider'],
                'model' => $llmSettings['llm_model'],
                'base_url' => $llmSettings['llm_base_url'],
            ],
        ]);
    }

    /**
     * API endpoint to list TTS models with their acoustic features and shortcodes.
     */
    public function ttsModelFeatures(): JsonResponse
    {
        $features = (new \App\Services\TtsModelFeatures())->getAll();
        return response()->json([
            'success' => true,
            'models' => $features,
        ]);
    }

    /**
     * Store a newly created template in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:190',
            'category' => 'nullable|string|max:100',
            'content' => 'required|string',
            'description' => 'nullable|string',
            'system_prompt' => 'nullable|string',
            'variables_schema' => 'nullable|array',
            'is_favorite' => 'nullable|boolean',
        ]);

        $validated['category'] = !empty($validated['category']) ? trim($validated['category']) : 'Genel';
        $validated['is_favorite'] = (bool) ($validated['is_favorite'] ?? false);

        PromptTemplate::create($validated);

        return redirect()->back()->with('success', 'Prompt şablonu başarıyla oluşturuldu.');
    }

    /**
     * Update the specified template in storage.
     */
    public function update(Request $request, $id)
    {
        $template = PromptTemplate::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:190',
            'category' => 'nullable|string|max:100',
            'content' => 'required|string',
            'description' => 'nullable|string',
            'system_prompt' => 'nullable|string',
            'variables_schema' => 'nullable|array',
            'is_favorite' => 'nullable|boolean',
        ]);

        $validated['category'] = !empty($validated['category']) ? trim($validated['category']) : 'Genel';
        $validated['is_favorite'] = (bool) ($validated['is_favorite'] ?? false);

        $template->update($validated);

        return redirect()->back()->with('success', 'Prompt şablonu güncellendi.');
    }

    /**
     * Toggle favorite status.
     */
    public function toggleFavorite($id)
    {
        $template = PromptTemplate::findOrFail($id);
        $template->is_favorite = !$template->is_favorite;
        $template->save();

        return redirect()->back()->with('success', 'Favori durumu güncellendi.');
    }

    /**
     * Remove the specified template from storage.
     */
    public function destroy($id)
    {
        $template = PromptTemplate::findOrFail($id);
        $template->delete();

        return redirect()->back()->with('success', 'Prompt şablonu silindi.');
    }
}
