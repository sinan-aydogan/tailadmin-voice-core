<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\ApiLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyManagementController extends Controller
{
    /**
     * List all API keys.
     */
    public function index(): JsonResponse
    {
        $keys = ApiKey::withCount('logs')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'api_keys' => $keys,
        ]);
    }

    /**
     * Store a newly created API key.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'key' => 'nullable|string|max:255|unique:api_keys,key',
        ]);

        $keyString = !empty($validated['key']) ? trim($validated['key']) : ApiKey::generateKey();

        $apiKey = ApiKey::create([
            'name' => trim($validated['name']),
            'key' => $keyString,
            'is_active' => true,
            'requests_count' => 0,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'API anahtarı başarıyla oluşturuldu.',
                'api_key' => $apiKey,
            ], 201);
        }

        return redirect()->back()->with('success', 'API anahtarı başarıyla oluşturuldu.');
    }

    /**
     * Toggle active / inactive status of an API key.
     */
    public function toggle($id)
    {
        $apiKey = ApiKey::findOrFail($id);
        $apiKey->update(['is_active' => !$apiKey->is_active]);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $apiKey->is_active ? 'API anahtarı etkinleştirildi.' : 'API anahtarı devre dışı bırakıldı.',
                'is_active' => $apiKey->is_active,
            ]);
        }

        return redirect()->back()->with('success', 'API anahtarı durumu güncellendi.');
    }

    /**
     * Delete an API key and its logs.
     */
    public function destroy($id)
    {
        $apiKey = ApiKey::findOrFail($id);
        $apiKey->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'API anahtarı başarıyla silindi.',
            ]);
        }

        return redirect()->back()->with('success', 'API anahtarı silindi.');
    }

    /**
     * Get operation history logs for a specific API key.
     */
    public function logs($id): JsonResponse
    {
        $apiKey = ApiKey::findOrFail($id);

        $logs = ApiLog::where('api_key_id', $apiKey->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $totalCount = ApiLog::where('api_key_id', $apiKey->id)->count();
        $successCount = ApiLog::where('api_key_id', $apiKey->id)
            ->where('status_code', '>=', 200)
            ->where('status_code', '<', 300)
            ->count();
        $errorCount = ApiLog::where('api_key_id', $apiKey->id)
            ->where('status_code', '>=', 400)
            ->count();

        return response()->json([
            'success' => true,
            'api_key' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'key' => $apiKey->key,
                'is_active' => $apiKey->is_active,
                'requests_count' => $apiKey->requests_count,
                'last_used_at' => $apiKey->last_used_at?->toIso8601String(),
                'created_at' => $apiKey->created_at?->toIso8601String(),
            ],
            'stats' => [
                'total' => $totalCount,
                'success' => $successCount,
                'error' => $errorCount,
            ],
            'logs' => $logs,
        ]);
    }

    /**
     * Clear all operation logs for a specific API key.
     */
    public function clearLogs($id): JsonResponse
    {
        $apiKey = ApiKey::findOrFail($id);
        ApiLog::where('api_key_id', $apiKey->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bu API anahtarına ait tüm işlem kayıtları temizlendi.',
        ]);
    }
}
