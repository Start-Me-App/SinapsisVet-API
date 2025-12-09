<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use function env;

/**
 * @property string $path
 */
class PdfFile extends Model
{
    use HasFactory;

    protected $table = 'pdf_files';

    protected $fillable = [
        'original_name',
        'stored_name',
        'path',
        'size',
        'mime_type',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        $relativeUrl = Storage::disk('public')->url($this->path);
        $base = rtrim(env('STATIC_URL', ''), '/');

        return $base ? $base . $relativeUrl : $relativeUrl;
    }
}

