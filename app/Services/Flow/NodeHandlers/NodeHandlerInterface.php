<?php

namespace App\Services\Flow\NodeHandlers;

interface NodeHandlerInterface
{
    /**
     * Execute the node with its (already variable-resolved) config and the
     * full run context (for handlers that need raw access, e.g. condition).
     *
     * @param array $data Resolved node "data" config.
     * @param array $context Full accumulated run context (trigger + prior node outputs).
     * @return array Output to store under $context[$nodeId].
     */
    public function handle(array $data, array $context): array;
}
