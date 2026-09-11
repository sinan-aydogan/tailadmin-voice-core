<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'key',
        'is_active',
        'requests_count',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requests_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(ApiLog::class);
    }

    /**
     * Generate a cryptographically secure random API key with vc_live_ prefix.
     */
    public static function generateKey(): string
    {
        return 'vc_live_' . Str::random(32);
    }
}
