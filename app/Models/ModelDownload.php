<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelDownload extends Model
{
    protected $fillable = [
        'model_id',
        'status',
        'progress',
        'downloaded_bytes',
        'total_bytes',
        'error_message',
    ];

    protected $casts = [
        'progress' => 'float',
        'downloaded_bytes' => 'integer',
        'total_bytes' => 'integer',
    ];

    public function setErrorMessageAttribute($value): void
    {
        $this->attributes['error_message'] = $value !== null ? self::cleanUtf8($value) : null;
    }

    public function getErrorMessageAttribute($value): ?string
    {
        return $value !== null ? self::cleanUtf8($value) : null;
    }

    public static function cleanUtf8(?string $string): ?string
    {
        if ($string === null) {
            return null;
        }
        if (mb_check_encoding($string, 'UTF-8')) {
            return $string;
        }
        $converted = @iconv('WINDOWS-1254', 'UTF-8//IGNORE', $string);
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }
        return mb_convert_encoding($string, 'UTF-8', 'UTF-8');
    }
}
