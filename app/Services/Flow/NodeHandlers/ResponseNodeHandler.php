<?php

namespace App\Services\Flow\NodeHandlers;

class ResponseNodeHandler implements NodeHandlerInterface
{
    public function handle(array $data, array $context): array
    {
        return [
            'mode' => $data['mode'] ?? 'json',
            'value' => $data['source'] ?? null,
            'twiml_type' => $data['twiml_type'] ?? 'play',
        ];
    }
}
