<?php

namespace App\Services\Flow\NodeHandlers;

use Illuminate\Support\Facades\Http;

class HttpNodeHandler implements NodeHandlerInterface
{
    public function handle(array $data, array $context): array
    {
        $url = $data['url'] ?? null;
        if (empty($url)) {
            throw new \RuntimeException('HTTP İsteği düğümü için URL belirtilmedi.');
        }

        $method = strtolower($data['method'] ?? 'get');
        $headers = [];
        foreach ($data['headers'] ?? [] as $header) {
            if (!empty($header['key'])) {
                $headers[$header['key']] = $header['value'] ?? '';
            }
        }

        $body = [];
        foreach ($data['body'] ?? [] as $field) {
            if (!empty($field['key'])) {
                $body[$field['key']] = $this->coerceBodyValue($field['value'] ?? '');
            }
        }

        $timeout = (int) ($data['timeout'] ?? 30);
        $request = Http::timeout($timeout)->withHeaders($headers);

        $response = match ($method) {
            'get' => $request->get($url, $body),
            'delete' => $request->delete($url, $body),
            'put' => $request->put($url, $body),
            'patch' => $request->patch($url, $body),
            default => $request->post($url, $body),
        };

        $jsonBody = null;
        try {
            $jsonBody = $response->json();
        } catch (\Throwable) {
            $jsonBody = null;
        }

        return [
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body' => $jsonBody ?? $response->body(),
            'headers' => $response->headers(),
        ];
    }

    /**
     * A body field value that happens to be a JSON object/array (e.g.
     * `{"link":"{{tts.audio_url}}"}` after variable substitution) is sent
     * as nested JSON instead of a flat string — needed for APIs like
     * WhatsApp Cloud that expect nested objects in the outbound payload.
     */
    protected function coerceBodyValue(mixed $value): mixed
    {
        if (!is_string($value) || !preg_match('/^[\[{]/', trim($value))) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
