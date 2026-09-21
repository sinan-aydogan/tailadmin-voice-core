<?php

namespace App\Services\Flow\NodeHandlers;

class ConditionNodeHandler implements NodeHandlerInterface
{
    public function handle(array $data, array $context): array
    {
        $left = $data['left'] ?? null;
        $operator = $data['operator'] ?? 'equals';
        $right = $data['right'] ?? null;

        $result = match ($operator) {
            'equals' => (string) $left === (string) $right,
            'not_equals' => (string) $left !== (string) $right,
            'contains' => is_string($left) && str_contains($left, (string) $right),
            'not_contains' => is_string($left) && !str_contains($left, (string) $right),
            'regex' => is_string($left) && @preg_match((string) $right, $left) === 1,
            'exists' => $left !== null && $left !== '',
            'not_exists' => $left === null || $left === '',
            'gt' => is_numeric($left) && is_numeric($right) && (float) $left > (float) $right,
            'gte' => is_numeric($left) && is_numeric($right) && (float) $left >= (float) $right,
            'lt' => is_numeric($left) && is_numeric($right) && (float) $left < (float) $right,
            'lte' => is_numeric($left) && is_numeric($right) && (float) $left <= (float) $right,
            default => false,
        };

        return [
            'result' => $result,
            'matched_handle' => $result ? 'true' : 'false',
            'left' => $left,
            'operator' => $operator,
            'right' => $right,
        ];
    }
}
