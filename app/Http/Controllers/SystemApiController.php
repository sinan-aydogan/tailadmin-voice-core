<?php

namespace App\Http\Controllers;

use App\Services\PythonVoiceService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SystemApiController extends Controller
{
    public function stats(PythonVoiceService $service): JsonResponse
    {
        return response()->json($service->getSystemStats());
    }

    public function audio(string $filename): BinaryFileResponse
    {
        $path = base_path('data/outputs/' . $filename);
        if (!file_exists($path)) {
            abort(404, 'Audio file not found');
        }

        return response()->file($path, [
            'Content-Type' => 'audio/wav',
            'Accept-Ranges' => 'bytes',
        ]);
    }
}
