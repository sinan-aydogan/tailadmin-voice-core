<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Flow extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'trigger_slug',
        'trigger_type',
        'definition',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'definition' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Flow $flow) {
            if (empty($flow->trigger_slug)) {
                $flow->trigger_slug = static::generateUniqueSlug();
            }
            if (empty($flow->definition)) {
                $flow->definition = ['nodes' => [], 'edges' => []];
            }
        });
    }

    public static function generateUniqueSlug(): string
    {
        do {
            $slug = Str::lower(Str::random(24));
        } while (static::where('trigger_slug', $slug)->exists());

        return $slug;
    }

    public function runs(): HasMany
    {
        return $this->hasMany(FlowRun::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(FlowSession::class);
    }

    public function findNode(string $nodeId): ?array
    {
        foreach ($this->definition['nodes'] ?? [] as $node) {
            if (($node['id'] ?? null) === $nodeId) {
                return $node;
            }
        }
        return null;
    }

    public function findTriggerNode(): ?array
    {
        foreach ($this->definition['nodes'] ?? [] as $node) {
            if (str_starts_with($node['type'] ?? '', 'trigger.')) {
                return $node;
            }
        }
        return null;
    }

    /**
     * Outgoing edges from a node, optionally filtered by sourceHandle.
     */
    public function edgesFrom(string $nodeId, ?string $handle = null): array
    {
        $edges = array_values(array_filter($this->definition['edges'] ?? [], function ($edge) use ($nodeId, $handle) {
            if (($edge['source'] ?? null) !== $nodeId) {
                return false;
            }
            if ($handle !== null && ($edge['sourceHandle'] ?? null) !== $handle) {
                return false;
            }
            return true;
        }));

        return $edges;
    }
}
