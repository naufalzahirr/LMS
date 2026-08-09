<?php

namespace Database\Factories;

use App\Enums\AcademicStatus;
use App\Models\Course;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = str(fake()->unique()->sentence(3))->trim('.')->toString();
        $slugSuffix = fake()->unique()->bothify('###');

        return [
            'program_id' => Program::factory(),
            'name' => str($name)->title()->toString(),
            'slug' => str($name)->slug()->append('-', $slugSuffix)->toString(),
            'code' => fake()->unique()->bothify('CRS-###'),
            'description' => fake()->sentence(),
            'status' => AcademicStatus::Active,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
