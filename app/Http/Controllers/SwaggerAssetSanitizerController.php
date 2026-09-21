<?php

namespace App\Http\Controllers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use L5Swagger\Exceptions\L5SwaggerException;

class SwaggerAssetSanitizerController extends BaseController
{
    /**
     * Serves Swagger UI assets safely with proper UTF-8 charset,
     * protection against NativePHP LivewireDispatcher injection, and controlled caching.
     */
    public function show(Request $request, ?string $asset = null): Response
    {
        $asset = $asset ?: $request->route('asset') ?: $request->query('asset');
        $documentation = $request->query('documentation', 'default');

        if (! $asset) {
            abort(404, 'Asset not specified');
        }

        try {
            $path = swagger_ui_dist_path($documentation, $asset);
        } catch (L5SwaggerException $e) {
            abort(404, $e->getMessage());
        }

        if (! file_exists($path)) {
            abort(404, 'Asset file does not exist');
        }

        $extension = strtolower(pathinfo($asset, PATHINFO_EXTENSION));
        $filesystem = new Filesystem();
        $content = $filesystem->get($path);

        $contentType = match ($extension) {
            'js' => 'application/javascript; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'png' => 'image/png',
            'html' => 'text/html; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            default => 'application/octet-stream',
        };

        if ($extension === 'js') {
            // 1. Normalize line endings to standard Unix LF
            $content = str_replace(["\r\n", "\r"], "\n", $content);

            // 2. Escape non-printable ASCII control characters (bell and escape)
            $content = str_replace(["\x07", "\x1b"], ['\\u0007', '\\u001b'], $content);

            // 3. Prevent NativePHP LivewireDispatcher from falsely injecting HTML/scripts
            // by marking the identifier in a harmless JS comment at the top of the file.
            $content = "/*<!--[NativePHP Livewire Dispatcher]-->*/\n" . $content;
        }

        $etag = '"' . md5($content) . '"';

        if ($request->header('If-None-Match') === $etag) {
            return new Response('', 304, [
                'Content-Type' => $contentType,
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=3600, must-revalidate',
            ]);
        }

        return new Response($content, 200, [
            'Content-Type' => $contentType,
            'Content-Length' => strlen($content),
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=3600, must-revalidate',
        ]);
    }
}
