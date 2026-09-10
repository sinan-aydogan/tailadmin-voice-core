<?php

namespace App\Http\Controllers;

use App\Models\VoiceProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function index(): Response
    {
        $profiles = VoiceProfile::latest()->get();

        return Inertia::render('Profiles/Index', [
            'profiles' => $profiles,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'sample' => 'nullable|file|mimes:wav,mp3,ogg,m4a|max:20480',
        ]);

        $samplePath = null;
        if ($request->hasFile('sample')) {
            $file = $request->file('sample');
            $dir = base_path('data/profiles');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $filename = 'profile_' . Str::random(12) . '.' . $file->getClientOriginalExtension();
            $file->move($dir, $filename);
            $samplePath = $dir . DIRECTORY_SEPARATOR . $filename;
        }

        VoiceProfile::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'sample_path' => $samplePath,
        ]);

        return redirect()->back()->with('success', 'Ses profili başarıyla oluşturuldu.');
    }

    public function destroy($id)
    {
        $profile = VoiceProfile::findOrFail($id);
        if ($profile->sample_path && file_exists($profile->sample_path)) {
            @unlink($profile->sample_path);
        }
        $profile->delete();

        return redirect()->back()->with('success', 'Ses profili silindi.');
    }
}
