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
    public function index(Request $request): Response
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

        return Inertia::render('Prompts/Index', [
            'templates' => $templates,
            'categories' => $categories,
            'filters' => [
                'search' => $request->input('search', ''),
                'category' => $request->input('category', 'all'),
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

        return response()->json([
            'success' => true,
            'templates' => $templates,
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
