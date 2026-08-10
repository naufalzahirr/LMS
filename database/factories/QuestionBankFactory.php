<?php

namespace Database\Factories;

use App\Enums\AcademicStatus;
use App\Models\Course;
use App\Models\QuestionBank;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuestionBank> */
class QuestionBankFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'name' => str(fake()->unique()->sentence(3))->trim('.')->title()->toString().' Bank',
            'code' => fake()->unique()->bothify('QB-###'),
            'description' => fake()->sentence(),
            'status' => AcademicStatus::Active,
        ];
    }
}
