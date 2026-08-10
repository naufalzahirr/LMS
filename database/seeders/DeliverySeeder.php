<?php

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\ParentRelationshipType;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\LessonProgress;
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

            $studentAEnrollment = null;

            foreach ([$studentA, $studentB] as $student) {
                $enrollment = Enrollment::query()->updateOrCreate(
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

                if ($student->is($studentA)) {
                    $studentAEnrollment = $enrollment;
                }
            }

            $lessons = Lesson::query()
                ->whereHas('module.competency', fn ($query) => $query->where('course_id', $course->id))
                ->orderBy('module_id')
                ->orderBy('sort_order')
                ->limit(3)
                ->get();

            if ($studentAEnrollment instanceof Enrollment) {
                foreach ($lessons as $index => $lesson) {
                    $completed = $index < 2;
                    LessonProgress::query()->updateOrCreate(
                        [
                            'enrollment_id' => $studentAEnrollment->id,
                            'lesson_id' => $lesson->id,
                        ],
                        [
                            'status' => $completed ? LessonProgressStatus::Completed : LessonProgressStatus::InProgress,
                            'started_at' => now()->subDays(3 - $index),
                            'completed_at' => $completed ? now()->subDays(2 - $index) : null,
                            'last_viewed_at' => now()->subHours(3 - $index),
                        ],
                    );
                }
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
