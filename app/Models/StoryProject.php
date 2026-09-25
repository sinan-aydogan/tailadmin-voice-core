<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoryProject extends Model
{
    protected $fillable = [
        'title',
        'theme',
        'target_audience',
        'options',
        'script_data',
        'status',
        'progress_step',
        'master_audio_path',
        'stems',
        'duration_sec',
        'error_message',
    ];

    protected $casts = [
        'options' => 'array',
        'script_data' => 'array',
        'stems' => 'array',
        'duration_sec' => 'float',
    ];

    /**
     * Get accessible URL for the master audio file.
     */
    public function getMasterAudioUrlAttribute(): ?string
    {
        if (empty($this->master_audio_path)) {
            return null;
        }

        if (str_starts_with($this->master_audio_path, 'http://') || str_starts_with($this->master_audio_path, 'https://')) {
            return $this->master_audio_path;
        }

        return url("/api/story/audio/{$this->id}/master");
    }
}
