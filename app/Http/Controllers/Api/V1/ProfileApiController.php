<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\VoiceProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProfileApiController extends Controller
{
    /**
     * List all voice cloning profiles.
     */
    public function index(): JsonResponse
    {
        $profiles = VoiceProfile::latest()->get()->map(function ($profile) {
            $filename = $profile->sample_path ? basename($profile->sample_path) : null;
            return [
                'id' => $profile->id,
                'name' => $profile->name,
                'description' => $profile->description,
                'sample_path' => $profile->sample_path,
                'audio_url' => $filename ? url("/api/v1/audio/{$filename}") : null,
                'created_at' => $profile->created_at?->toIso8601String(),
                'updated_at' => $profile->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'count' => $profiles->count(),
            'profiles' => $profiles,
        ]);
    }

    /**
     * Create a new voice profile with reference audio for zero-shot cloning.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'sample' => 'nullable|file|mimes:wav,mp3,ogg,m4a,webm|max:20480',
        ]);

        $samplePath = null;
        $filename = null;
        if ($request->hasFile('sample')) {
            $file = $request->file('sample');
            $dir = base_path('data/profiles');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $ext = $file->getClientOriginalExtension() ?: 'wav';
            $filename = 'profile_' . Str::random(12) . '.' . $ext;
            $file->move($dir, $filename);
            $samplePath = $dir . DIRECTORY_SEPARATOR . $filename;
        }

        $profile = VoiceProfile::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'sample_path' => $samplePath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Voice profile created successfully.',
            'profile' => [
                'id' => $profile->id,
                'name' => $profile->name,
                'description' => $profile->description,
                'sample_path' => $profile->sample_path,
                'audio_url' => $filename ? url("/api/v1/audio/{$filename}") : null,
                'created_at' => $profile->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Delete a voice profile.
     */
    public function destroy(int $id): JsonResponse
    {
        $profile = VoiceProfile::find($id);
        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => "Profile not found with ID: {$id}",
            ], 404);
        }

        if ($profile->sample_path && file_exists($profile->sample_path)) {
            @unlink($profile->sample_path);
        }

        $profile->delete();

        return response()->json([
            'success' => true,
            'message' => "Voice profile #{$id} deleted successfully.",
        ]);
    }
}
