<?php

namespace Database\Factories;

use App\Enums\AcademicStatus;
use App\Models\Competency;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = str(fake()->unique()->sentence(3))->trim('.')->toString();
        $slugSuffix = fake()->unique()->bothify('###');

        return [
            'competency_id' => Competency::factory(),
            'name' => str($name)->title()->toString(),
            'slug' => str($name)->slug()->append('-', $slugSuffix)->toString(),
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(0, 20),
            'status' => AcademicStatus::Active,
        ];
    }
}
