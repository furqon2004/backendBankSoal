<?php

namespace App\Models;

use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'created_by',
        'attempt_limit',
        'is_active',
        'pdf_path',
        'has_audio',
        'media_extracted',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'attempt_limit' => 'integer',
            'has_audio' => 'boolean',
            'media_extracted' => 'boolean',
        ];
    }

    /**
     * Scope: only active materials.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The admin who created this material.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Questions belonging to this material.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /**
     * Quiz attempts for this material.
     */
    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * Media (images & audio) extracted from the material's PDF.
     */
    public function media(): HasMany
    {
        return $this->hasMany(MaterialMedia::class);
    }

    /**
     * Scope: only images media.
     */
    public function images(): HasMany
    {
        return $this->hasMany(MaterialMedia::class)->where('type', 'image')->orderBy('order');
    }

    /**
     * Scope: only audio media.
     */
    public function audios(): HasMany
    {
        return $this->hasMany(MaterialMedia::class)->where('type', 'audio')->orderBy('order');
    }
}
