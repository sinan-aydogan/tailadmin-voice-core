<?php

// Patch NativePHP LivewireDispatcher to ensure it only intercepts text/html responses
$livewireDispatcherPath = __DIR__ . '/../vendor/nativephp/desktop/src/Events/LivewireDispatcher.php';
if (file_exists($livewireDispatcherPath)) {
    $code = file_get_contents($livewireDispatcherPath);

    $target = "        // Skip if request doesn't return a full page\n        if (! str_contains(\$html, '</html>')) {";
    $replacement = "        // Skip if response is not HTML\n        \$contentType = \$handled->response->headers->get('Content-Type') ?? '';\n        if (! str_contains(\$contentType, 'text/html')) {\n            return;\n        }\n\n        // Skip if request doesn't return a full page\n        if (! str_contains(\$html, '</html>')) {";

    if (str_contains($code, $target) && ! str_contains($code, "Skip if response is not HTML")) {
        $code = str_replace($target, $replacement, $code);
        file_put_contents($livewireDispatcherPath, $code);
    }
}

// Ensure swagger-ui dist assets are copied to public/vendor/swagger-ui
$swaggerSource = __DIR__ . '/../vendor/swagger-api/swagger-ui/dist';
$swaggerDest = __DIR__ . '/../public/vendor/swagger-ui';
if (is_dir($swaggerSource)) {
    if (! is_dir($swaggerDest)) {
        @mkdir($swaggerDest, 0777, true);
    }
    foreach (new DirectoryIterator($swaggerSource) as $file) {
        if ($file->isDot() || ! $file->isFile()) {
            continue;
        }
        $targetFile = $swaggerDest . '/' . $file->getFilename();
        if (! file_exists($targetFile) || filemtime($file->getPathname()) > filemtime($targetFile)) {
            @copy($file->getPathname(), $targetFile);
        }
    }
}
