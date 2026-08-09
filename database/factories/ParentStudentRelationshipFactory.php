<?php

namespace Database\Factories;

use App\Enums\ParentRelationshipType;
use App\Models\ParentStudentRelationship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParentStudentRelationship>
 */
class ParentStudentRelationshipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => User::factory(),
            'student_id' => User::factory(),
            'relationship_type' => ParentRelationshipType::Guardian,
        ];
    }
}
