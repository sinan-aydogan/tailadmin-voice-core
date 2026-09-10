<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceProfile extends Model
{
    protected $fillable = [
        'name',
        'sample_path',
        'description',
    ];
}
