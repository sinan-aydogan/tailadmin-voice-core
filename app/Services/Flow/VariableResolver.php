<?php

namespace App\Services\Flow;

class VariableResolver
{
    /**
     * Resolve {{node_id.path.to.field}} tokens against the run context.
     *
     * If the whole value is exactly one token, the resolved value keeps its
     * original type (e.g. a file path string, a number, an array). If the
     * token is embedded inside a larger string, the resolved value is cast
     * to a string and substituted in place.
     */
    public function resolve(mixed $value, array $context): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->resolve($item, $context), $value);
        }

        if (!is_string($value) || !str_contains($value, '{{')) {
            return $value;
        }

        $wholeTokenPattern = '/^\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}$/';
        if (preg_match($wholeTokenPattern, trim($value), $matches)) {
            return data_get($context, $matches[1]);
        }

        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', function ($matches) use ($context) {
            $resolved = data_get($context, $matches[1]);

            if (is_array($resolved)) {
                return json_encode($resolved, JSON_UNESCAPED_UNICODE);
            }

            return is_scalar($resolved) ? (string) $resolved : '';
        }, $value);
    }

    /**
     * Resolve every value inside a node's "data" config array.
     */
    public function resolveConfig(array $data, array $context): array
    {
        return $this->resolve($data, $context);
    }

    /**
     * List every {{...}} variable path referenced inside a node config,
     * useful for validation / the frontend variable picker.
     */
    public function extractTokens(array $data): array
    {
        $tokens = [];
        array_walk_recursive($data, function ($value) use (&$tokens) {
            if (is_string($value) && preg_match_all('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', $value, $matches)) {
                foreach ($matches[1] as $path) {
                    $tokens[$path] = true;
                }
            }
        });

        return array_keys($tokens);
    }
}
