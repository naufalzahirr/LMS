<?php

namespace Tests\Feature;

use App\Models\Competency;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClassProgressVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_view_class_roster_lesson_progress(): void
    {
        [$learningClass, $enrollment] = $this->classWithProgress();

        $this->actingAs($this->userWithRole('Admin'))->get(route('admin.classes.show', $learningClass))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/classes/Show')
                ->where('enrollments.0.id', $enrollment->id)
                ->where('enrollments.0.completed_lessons', 1)
                ->where('enrollments.0.total_lessons', 2)
                ->where('enrollments.0.progress_percentage', 50));
    }

    public function test_assigned_tutor_can_view_progress_but_unassigned_tutor_cannot(): void
    {
        [$learningClass] = $this->classWithProgress();
        $assigned = $this->userWithRole('Tutor');
        $unassigned = $this->userWithRole('Tutor');
        $learningClass->tutors()->attach($assigned);

        $this->actingAs($assigned)->get(route('tutor.classes.show', $learningClass))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tutor/classes/Show')
                ->where('enrollments.0.completed_lessons', 1)
                ->where('enrollments.0.total_lessons', 2)
                ->where('enrollments.0.progress_percentage', 50));
        $this->actingAs($unassigned)->get(route('tutor.classes.show', $learningClass))
            ->assertForbidden();
    }

    public function test_tutor_cannot_mutate_student_progress(): void
    {
        [$learningClass, , $lesson] = $this->classWithProgress();
        $tutor = $this->userWithRole('Tutor');
        $learningClass->tutors()->attach($tutor);

        $this->actingAs($tutor)->patch(route('student.lesson-progress.update', [$learningClass, $lesson]), [
            'status' => 'in_progress',
        ])->assertForbidden();
        $this->assertDatabaseHas('lesson_progress', [
            'lesson_id' => $lesson->id,
            'status' => 'completed',
        ]);
    }

    /** @return array{LearningClass, Enrollment, Lesson} */
    private function classWithProgress(): array
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for(Competency::factory()->for($course))->create();
        $completed = Lesson::factory()->for($module)->create();
        Lesson::factory()->for($module)->create();
        $learningClass = LearningClass::factory()->for($course)->create();
        $student = $this->userWithRole('Student');
        $enrollment = Enrollment::factory()->for($learningClass)->create(['student_id' => $student->id]);
        LessonProgress::factory()->for($enrollment)->for($completed)->completed()->create();

        return [$learningClass, $enrollment, $completed];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
