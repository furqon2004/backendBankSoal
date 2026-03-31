<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MaterialMedia extends Model
{
    protected $fillable = [
        'material_id',
        'type',
        'file_path',
        'file_url',
        'page_number',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'order' => 'integer',
        ];
    }

    /**
     * The material this media belongs to.
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Get the full public URL for this media file.
     */
    public function getPublicUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }

    /**
     * Check if this is an image.
     */
    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    /**
     * Check if this is audio.
     */
    public function isAudio(): bool
    {
        return $this->type === 'audio';
    }
}
