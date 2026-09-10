<?php

namespace App\Http\Controllers;

use App\Models\VoicePlaylist;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlaylistController extends Controller
{
    public function index(): Response
    {
        $playlists = VoicePlaylist::latest()->get();

        return Inertia::render('Playlists/Index', [
            'playlists' => $playlists,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
            'items' => 'nullable|array',
        ]);

        VoicePlaylist::create($validated);

        return redirect()->back()->with('success', 'İş listesi oluşturuldu.');
    }
}
