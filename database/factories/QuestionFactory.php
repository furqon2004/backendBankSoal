<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        $correctAnswer = fake()->randomElement(['a', 'b', 'c', 'd']);

        return [
            'material_id' => Material::factory(),
            'question_text' => fake()->sentence(10) . '?',
            'option_a' => fake()->sentence(3),
            'option_b' => fake()->sentence(3),
            'option_c' => fake()->sentence(3),
            'option_d' => fake()->sentence(3),
            'correct_answer' => $correctAnswer,
            'explanation' => fake()->sentence(15),
        ];
    }
}
