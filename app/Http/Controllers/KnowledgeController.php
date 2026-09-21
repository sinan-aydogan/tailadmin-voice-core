<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeDocument;
use App\Services\Knowledge\KnowledgeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeController extends Controller
{
    public function index(): Response
    {
        $documents = KnowledgeDocument::withCount('chunks')
            ->latest()
            ->get()
            ->map(function (KnowledgeDocument $doc) {
                $doc->char_count = mb_strlen($doc->content);
                $doc->makeHidden('content');
                return $doc;
            });

        return Inertia::render('Knowledge/Index', [
            'documents' => $documents,
        ]);
    }

    /**
     * Full document detail (including content) for the detail modal.
     */
    public function show($id)
    {
        $document = KnowledgeDocument::withCount('chunks')->findOrFail($id);
        $document->char_count = mb_strlen($document->content);

        return response()->json(['success' => true, 'document' => $document]);
    }

    public function store(Request $request, KnowledgeService $service)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:190',
            'content' => 'required|string|min:1',
            'source_filename' => 'nullable|string|max:190',
        ]);

        $service->addDocument($validated['title'], $validated['content'], $validated['source_filename'] ?? null);

        return redirect()->back()->with('success', 'Bilgi kaynağı eklendi.');
    }

    public function destroy($id, KnowledgeService $service)
    {
        $document = KnowledgeDocument::findOrFail($id);
        $service->deleteDocument($document);

        return redirect()->back()->with('success', 'Bilgi kaynağı silindi.');
    }
}
