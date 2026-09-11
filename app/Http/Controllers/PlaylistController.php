<?php

namespace App\Http\Controllers;

use App\Models\VoicePlaylist;
use App\Models\VoiceProfile;
use App\Models\VoiceTask;
use App\Jobs\GenerateTtsJob;
use App\Services\PythonVoiceService;
use App\Services\QueueWorkerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use ZipArchive;

class PlaylistController extends Controller
{
    public function index(PythonVoiceService $voiceService): Response
    {
        $playlists = VoicePlaylist::with('profile')->latest()->get();
        foreach ($playlists as $playlist) {
            $playlist->syncItemStatuses();
        }

        $models = $voiceService->getAvailableModels();
        $profiles = VoiceProfile::all();

        return Inertia::render('Playlists/Index', [
            'playlists' => $playlists,
            'models' => $models,
            'profiles' => $profiles,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
            'engine' => 'nullable|string',
            'language' => 'nullable|string|max:10',
            'profile_id' => 'nullable|integer',
        ]);

        $playlist = VoicePlaylist::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'engine' => $validated['engine'] ?? 'piper-tr',
            'language' => $validated['language'] ?? 'tr',
            'profile_id' => $validated['profile_id'] ?? null,
            'status' => 'pending',
            'items' => [],
        ]);

        return redirect()->back()->with('success', 'İş listesi başarıyla oluşturuldu.');
    }

    public function destroy($id)
    {
        $playlist = VoicePlaylist::findOrFail($id);
        $playlist->delete();

        return redirect()->route('playlists.index')->with('success', 'İş listesi silindi.');
    }

    public function addItem(Request $request, $id)
    {
        $validated = $request->validate([
            'text' => 'required|string|min:1',
        ]);

        $playlist = VoicePlaylist::findOrFail($id);
        $items = $playlist->items ?? [];

        // Split by lines to allow pasting bulk dialogues/lines
        $rawLines = preg_split('/\r\n|\r|\n/', $validated['text']);
        $addedCount = 0;

        foreach ($rawLines as $line) {
            $trimmed = trim($line);
            if (!empty($trimmed)) {
                $items[] = [
                    'id' => 'item_' . Str::random(10),
                    'text' => $trimmed,
                    'status' => 'ready',
                    'task_id' => null,
                    'filename' => null,
                    'error_message' => null,
                    'created_at' => now()->toIso8601String(),
                ];
                $addedCount++;
            }
        }

        $playlist->items = $items;
        if ($playlist->status === 'completed') {
            $playlist->status = 'ready';
        }
        $playlist->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'playlist' => $playlist,
                'added_count' => $addedCount,
            ]);
        }

        return redirect()->back()->with('success', "{$addedCount} adet iş parçası listeye eklendi.");
    }

    public function processItem(Request $request, $id, $itemId)
    {
        $playlist = VoicePlaylist::findOrFail($id);
        $items = $playlist->items ?? [];

        $itemIndex = null;
        foreach ($items as $idx => $it) {
            if (($it['id'] ?? '') === $itemId) {
                $itemIndex = $idx;
                break;
            }
        }

        if ($itemIndex === null) {
            return response()->json(['success' => false, 'message' => 'Öğe bulunamadı.'], 404);
        }

        $item = &$items[$itemIndex];

        $outputDir = base_path('data/outputs');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $profile = $playlist->profile_id ? VoiceProfile::find($playlist->profile_id) : null;
        $profilePath = $profile?->sample_path;

        $filename = 'tts_pl_' . Str::random(10) . '.wav';
        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $filename;

        $task = VoiceTask::create([
            'type' => 'tts',
            'status' => 'pending',
            'payload' => [
                'text' => $item['text'],
                'engine' => $playlist->engine,
                'language' => $playlist->language,
                'profile_id' => $playlist->profile_id,
                'profile_path' => $profilePath,
                'output_path' => $outputPath,
                'filename' => $filename,
                'playlist_id' => $playlist->id,
                'playlist_item_id' => $item['id'],
            ],
            'output_path' => $outputPath,
        ]);

        $item['status'] = 'pending';
        $item['task_id'] = $task->id;
        $item['filename'] = $filename;
        $item['error_message'] = null;

        GenerateTtsJob::dispatch($task->id)->onQueue('default');

        $playlist->items = $items;
        $playlist->status = 'processing';
        $playlist->save();

        QueueWorkerService::ensureRunning();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'playlist' => $playlist,
                'item' => $item,
                'message' => 'İş parçası seslendirme kuyruğuna alındı.',
            ]);
        }

        return redirect()->back()->with('success', 'İş parçası seslendirme kuyruğuna alındı.');
    }

    public function removeItem(Request $request, $id, $itemId)
    {
        $playlist = VoicePlaylist::findOrFail($id);
        $items = $playlist->items ?? [];

        $filtered = array_values(array_filter($items, fn($i) => ($i['id'] ?? '') !== $itemId));
        $playlist->items = $filtered;
        $playlist->save();
        $playlist->syncItemStatuses();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'playlist' => $playlist,
            ]);
        }

        return redirect()->back()->with('success', 'Öğe listeden silindi.');
    }

    public function updateConfig(Request $request, $id)
    {
        $validated = $request->validate([
            'engine' => 'required|string',
            'language' => 'required|string|max:10',
            'profile_id' => 'nullable|integer',
        ]);

        $playlist = VoicePlaylist::findOrFail($id);
        $playlist->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'playlist' => $playlist,
            ]);
        }

        return redirect()->back()->with('success', 'Liste yapılandırması güncellendi.');
    }

    public function process(Request $request, $id)
    {
        $validated = $request->validate([
            'engine' => 'nullable|string',
            'language' => 'nullable|string|max:10',
            'profile_id' => 'nullable|integer',
            'mode' => 'nullable|string|in:all,pending_failed',
        ]);

        $playlist = VoicePlaylist::findOrFail($id);

        if (!empty($validated['engine'])) {
            $playlist->engine = $validated['engine'];
        }
        if (!empty($validated['language'])) {
            $playlist->language = $validated['language'];
        }
        if (array_key_exists('profile_id', $validated)) {
            $playlist->profile_id = $validated['profile_id'];
        }

        $playlist->save();

        $items = $playlist->items ?? [];
        if (empty($items)) {
            return redirect()->back()->with('error', 'Listede işlenecek hiçbir metin öğesi bulunmuyor.');
        }

        $restartAll = $request->boolean('restart_all', false);
        $mode = $request->input('mode', $restartAll ? 'all' : 'pending_failed');
        $outputDir = base_path('data/outputs');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $profile = $playlist->profile_id ? VoiceProfile::find($playlist->profile_id) : null;
        $profilePath = $profile?->sample_path;

        $dispatchedCount = 0;

        foreach ($items as &$item) {
            $currentStatus = $item['status'] ?? 'ready';

            if ($mode === 'all' || $currentStatus === 'ready' || $currentStatus === 'pending' || $currentStatus === 'failed') {
                $filename = 'tts_pl_' . Str::random(10) . '.wav';
                $outputPath = $outputDir . DIRECTORY_SEPARATOR . $filename;

                $task = VoiceTask::create([
                    'type' => 'tts',
                    'status' => 'pending',
                    'payload' => [
                        'text' => $item['text'],
                        'engine' => $playlist->engine,
                        'language' => $playlist->language,
                        'profile_id' => $playlist->profile_id,
                        'profile_path' => $profilePath,
                        'output_path' => $outputPath,
                        'filename' => $filename,
                        'playlist_id' => $playlist->id,
                        'playlist_item_id' => $item['id'],
                    ],
                    'output_path' => $outputPath,
                ]);

                $item['status'] = 'pending';
                $item['task_id'] = $task->id;
                $item['filename'] = $filename;
                $item['error_message'] = null;

                GenerateTtsJob::dispatch($task->id)->onQueue('default');
                $dispatchedCount++;
            }
        }
        unset($item);

        $playlist->items = $items;
        $playlist->status = 'processing';
        $playlist->save();

        QueueWorkerService::ensureRunning();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'playlist' => $playlist,
                'dispatched_count' => $dispatchedCount,
                'message' => "{$dispatchedCount} seslendirme görevi kuyruğa eklendi.",
            ]);
        }

        return redirect()->back()->with('success', "{$dispatchedCount} adet seslendirme görevi kuyruğa alındı.");
    }

    public function downloadZip($id)
    {
        $playlist = VoicePlaylist::findOrFail($id);
        $playlist->syncItemStatuses();

        $items = $playlist->items ?? [];
        $completedItems = array_filter($items, fn($i) => ($i['status'] ?? '') === 'completed' && !empty($i['filename']));

        if (empty($completedItems)) {
            return redirect()->back()->with('error', 'Bu listede henüz tamamlanmış ve indirilmeye hazır ses dosyası bulunmuyor.');
        }

        $zipFileName = Str::slug($playlist->title, '_') . '_Audio_' . date('Ymd_His') . '.zip';
        $tempDir = base_path('data/outputs');
        $zipFilePath = $tempDir . DIRECTORY_SEPARATOR . $zipFileName;

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return redirect()->back()->with('error', 'ZIP arşivi oluşturulamadı.');
        }

        $index = 1;
        foreach ($completedItems as $item) {
            $filePath = $tempDir . DIRECTORY_SEPARATOR . $item['filename'];
            if (file_exists($filePath)) {
                $cleanText = Str::limit(Str::slug($item['text'], '_'), 35, '');
                $entryName = sprintf('%02d_%s.wav', $index, $cleanText ?: 'ses');
                $zip->addFile($filePath, $entryName);
                $index++;
            }
        }

        $zip->close();

        if (!file_exists($zipFilePath) || filesize($zipFilePath) === 0) {
            return redirect()->back()->with('error', 'İndirilecek geçerli ses dosyası bulunamadı.');
        }

        return response()->download($zipFilePath, $zipFileName)->deleteFileAfterSend(true);
    }

    public function apiPlaylist($id): JsonResponse
    {
        $playlist = VoicePlaylist::with('profile')->findOrFail($id);
        $playlist->syncItemStatuses();

        return response()->json([
            'success' => true,
            'playlist' => $playlist,
            'id' => $playlist->id,
            'title' => $playlist->title,
            'description' => $playlist->description,
            'status' => $playlist->status,
            'items' => $playlist->items ?? [],
            'total_items' => $playlist->total_items,
            'completed_items' => $playlist->completed_items,
            'progress_percentage' => $playlist->progress_percentage,
            'engine' => $playlist->engine,
            'language' => $playlist->language,
            'profile_id' => $playlist->profile_id,
        ]);
    }
}
