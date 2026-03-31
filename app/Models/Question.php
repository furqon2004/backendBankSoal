<?php

namespace App\Models;

use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    protected $fillable = [
        'material_id',
        'section',
        'section_label',
        'question_text',
        'option_a',
        'option_b',
        'option_c',
        'option_d',
        'correct_answer',
        'explanation',
        'image_url',
        'audio_url',
        'item_number',
    ];

    protected function casts(): array
    {
        return [
            'section' => 'integer',
            'item_number' => 'integer',
        ];
    }

    /**
     * The material this question belongs to.
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Answers given to this question.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }
}
