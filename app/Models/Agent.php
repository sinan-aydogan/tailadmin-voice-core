<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Agent extends Model
{
    protected $fillable = [
        'name',
        'description',
        'system_prompt',
        'provider',
        'model',
        'api_key',
        'base_url',
        'tools',
        'max_tool_iterations',
        'use_knowledge',
        'knowledge_document_ids',
        'stt_enabled',
        'stt_language',
        'stt_model_size',
        'tts_engine',
        'tts_language',
        'tts_profile_id',
        'response_mode',
        'twiml_type',
        'is_active',
        'trigger_slug',
        'trigger_type',
    ];

    protected $casts = [
        'tools' => 'array',
        'knowledge_document_ids' => 'array',
        'max_tool_iterations' => 'integer',
        'use_knowledge' => 'boolean',
        'stt_enabled' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Agent $agent) {
            if (empty($agent->trigger_slug)) {
                $agent->trigger_slug = static::generateUniqueSlug();
            }
            if (empty($agent->tools)) {
                $agent->tools = [];
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

    public function sessions(): HasMany
    {
        return $this->hasMany(AgentSession::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AgentRun::class);
    }

    public function findTool(string $name): ?array
    {
        foreach ($this->tools ?? [] as $tool) {
            if (($tool['name'] ?? null) === $name) {
                return $tool;
            }
        }
        return null;
    }
}
