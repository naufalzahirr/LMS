<?php

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Enums\ParentRelationshipType;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\ParentStudentRelationship;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeliverySeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('Development class-delivery sample was not seeded in production.');

            return;
        }

        DB::transaction(function (): void {
            $course = Course::query()->where('slug', 'frontend-fundamentals')->firstOrFail();
            $learningClass = LearningClass::withTrashed()->updateOrCreate(
                ['code' => 'FE-BATCH-A'],
                [
                    'course_id' => $course->id,
                    'name' => 'Frontend Fundamentals - Batch A',
                    'description' => 'Development sample class for the delivery foundation.',
                    'start_date' => now()->startOfMonth()->toDateString(),
                    'end_date' => now()->addMonths(3)->endOfMonth()->toDateString(),
                    'status' => LearningClassStatus::Active,
                ],
            );
            $learningClass->restore();

            $tutor = $this->user('tutor@mlc.test', 'Development Tutor', 'Tutor');
            $studentA = $this->user('student.one@mlc.test', 'Development Student One', 'Student');
            $studentB = $this->user('student.two@mlc.test', 'Development Student Two', 'Student');
            $parent = $this->user('parent@mlc.test', 'Development Parent', 'Parent');

            $learningClass->tutors()->syncWithoutDetaching([$tutor->id]);

            foreach ([$studentA, $studentB] as $student) {
                Enrollment::query()->updateOrCreate(
                    [
                        'learning_class_id' => $learningClass->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'status' => EnrollmentStatus::Active,
                        'enrolled_at' => now(),
                        'completed_at' => null,
                    ],
                );
            }

            ParentStudentRelationship::query()->updateOrCreate(
                ['parent_id' => $parent->id, 'student_id' => $studentA->id],
                ['relationship_type' => ParentRelationshipType::Guardian],
            );
        });
    }

    private function user(string $email, string $name, string $role): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'email_verified_at' => now(),
                'password' => 'password',
            ],
        );
        $user->syncRoles([$role]);

        return $user;
    }
}
