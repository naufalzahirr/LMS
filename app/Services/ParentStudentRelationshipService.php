<?php

namespace App\Services;

use App\Enums\ParentRelationshipType;
use App\Models\ParentStudentRelationship;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParentStudentRelationshipService
{
    public function create(
        User $parent,
        User $student,
        ParentRelationshipType $relationshipType,
    ): ParentStudentRelationship {
        $this->validateUsers($parent, $student);

        return DB::transaction(function () use ($parent, $student, $relationshipType): ParentStudentRelationship {
            if (ParentStudentRelationship::query()
                ->where('parent_id', $parent->id)
                ->where('student_id', $student->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'student_id' => __('This parent and student are already linked.'),
                ]);
            }

            return ParentStudentRelationship::query()->create([
                'parent_id' => $parent->id,
                'student_id' => $student->id,
                'relationship_type' => $relationshipType,
            ]);
        });
    }

    public function delete(ParentStudentRelationship $relationship): void
    {
        DB::transaction(fn () => $relationship->delete());
    }

    private function validateUsers(User $parent, User $student): void
    {
        if ($parent->is($student)) {
            throw ValidationException::withMessages([
                'student_id' => __('A user cannot be related to themselves.'),
            ]);
        }

        if (! $parent->hasRole('Parent')) {
            throw ValidationException::withMessages([
                'parent_id' => __('The selected user must have the Parent role.'),
            ]);
        }

        if (! $student->hasRole('Student')) {
            throw ValidationException::withMessages([
                'student_id' => __('The selected user must have the Student role.'),
            ]);
        }
    }
}
