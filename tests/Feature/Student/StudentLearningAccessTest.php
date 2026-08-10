<?php

namespace Tests\Feature\Student;

use App\Enums\AcademicStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Enums\LessonType;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentLearningAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_student_sees_only_current_and_historical_enrolled_classes(): void
    {
        $student = $this->userWithRole('Student');
        [, $current] = $this->context($student);
        [, $history] = $this->context($student, EnrollmentStatus::Completed);
        $this->context($student, EnrollmentStatus::Withdrawn);
        LearningClass::factory()->create();

        $this->actingAs($student)->get(route('student.classes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/classes/Index')
                ->has('currentClasses', 1)
                ->where('currentClasses.0.id', $current->id)
                ->has('historyClasses', 1)
                ->where('historyClasses.0.id', $history->id));
    }

    public function test_active_student_can_access_active_class_and_its_lesson(): void
    {
        $student = $this->userWithRole('Student');
        [, $learningClass, $lesson] = $this->context($student);

        $this->actingAs($student)->get(route('student.classes.show', $learningClass))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/classes/Show')
                ->where('enrollment.read_only', false)
                ->where('competencies.0.modules.0.lessons.0.id', $lesson->id));

        $this->actingAs($student)->get(route('student.lessons.show', [$learningClass, $lesson]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/lessons/Show')
                ->where('lesson.id', $lesson->id)
                ->where('canMutate', true));
    }

    public function test_student_cannot_access_a_class_without_an_enrollment(): void
    {
        $student = $this->userWithRole('Student');
        [, $learningClass, $lesson] = $this->context($this->userWithRole('Student'));

        $this->actingAs($student)->get(route('student.classes.show', $learningClass))->assertForbidden();
        $this->actingAs($student)->get(route('student.lessons.show', [$learningClass, $lesson]))->assertForbidden();
    }

    public function test_withdrawn_student_cannot_access_learning_content(): void
    {
        $student = $this->userWithRole('Student');
        [, $learningClass, $lesson] = $this->context($student, EnrollmentStatus::Withdrawn);

        $this->actingAs($student)->get(route('student.classes.show', $learningClass))->assertForbidden();
        $this->actingAs($student)->get(route('student.lessons.show', [$learningClass, $lesson]))->assertForbidden();
    }

    public function test_completed_enrollment_has_read_only_access(): void
    {
        $student = $this->userWithRole('Student');
        [, $learningClass, $lesson] = $this->context($student, EnrollmentStatus::Completed);

        $this->actingAs($student)->get(route('student.classes.show', $learningClass))
            ->assertInertia(fn (Assert $page) => $page->where('enrollment.read_only', true));
        $this->actingAs($student)->get(route('student.lessons.show', [$learningClass, $lesson]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canMutate', false));
        $this->actingAs($student)->patch(route('student.lesson-progress.update', [$learningClass, $lesson]), [
            'status' => 'completed',
        ])->assertForbidden();
        $this->assertDatabaseCount('lesson_progress', 0);
    }

    public function test_completed_class_has_read_only_access_for_active_enrollment(): void
    {
        $student = $this->userWithRole('Student');
        [, $learningClass, $lesson] = $this->context(
            $student,
            EnrollmentStatus::Active,
            LearningClassStatus::Completed,
        );

        $this->actingAs($student)->get(route('student.lessons.show', [$learningClass, $lesson]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canMutate', false));
        $this->actingAs($student)->patch(route('student.lesson-progress.update', [$learningClass, $lesson]), [
            'status' => 'completed',
        ])->assertForbidden();
    }

    public function test_inactive_class_blocks_student_learning_access(): void
    {
        $student = $this->userWithRole('Student');
        [, $learningClass, $lesson] = $this->context(
            $student,
            EnrollmentStatus::Active,
            LearningClassStatus::Inactive,
        );

        $this->actingAs($student)->get(route('student.classes.show', $learningClass))->assertForbidden();
        $this->actingAs($student)->get(route('student.lessons.show', [$learningClass, $lesson]))->assertForbidden();
    }

    public function test_inactive_course_or_program_blocks_student_learning_access(): void
    {
        foreach (['course', 'program'] as $inactiveLevel) {
            $student = $this->userWithRole('Student');
            [, $learningClass, $lesson] = $this->context($student);

            if ($inactiveLevel === 'course') {
                $learningClass->course->update(['status' => AcademicStatus::Inactive]);
            } else {
                $learningClass->course->program->update(['status' => AcademicStatus::Inactive]);
            }

            $this->actingAs($student)->get(route('student.classes.show', $learningClass))->assertForbidden();
            $this->actingAs($student)->get(route('student.lessons.show', [$learningClass, $lesson]))->assertForbidden();
        }
    }

    public function test_parent_tutor_and_admin_cannot_use_student_learning_routes(): void
    {
        [$enrollment, $learningClass, $lesson] = $this->context($this->userWithRole('Student'));

        foreach (['Parent', 'Tutor', 'Admin'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('student.classes.index'))->assertForbidden();
            $this->actingAs($user)->get(route('student.classes.show', $learningClass))->assertForbidden();
            $this->actingAs($user)->patch(route('student.lesson-progress.update', [$learningClass, $lesson]), [
                'status' => 'completed',
            ])->assertForbidden();
        }

        $this->assertDatabaseMissing('lesson_progress', ['enrollment_id' => $enrollment->id]);
    }

    public function test_student_cannot_open_or_spoof_progress_for_another_courses_lesson(): void
    {
        $student = $this->userWithRole('Student');
        [, $learningClass] = $this->context($student);
        $otherLesson = Lesson::factory()->create();

        $this->actingAs($student)->get(route('student.lessons.show', [$learningClass, $otherLesson]))
            ->assertForbidden();
        $this->actingAs($student)->patch(route('student.lesson-progress.update', [$learningClass, $otherLesson]), [
            'status' => 'completed',
        ])->assertForbidden();
        $this->assertDatabaseMissing('lesson_progress', ['lesson_id' => $otherLesson->id]);
    }

    public function test_inactive_hierarchy_and_soft_deleted_content_are_not_accessible(): void
    {
        foreach (['lesson', 'module', 'competency'] as $inactiveLevel) {
            $student = $this->userWithRole('Student');
            [, $learningClass, $lesson, $module, $competency] = $this->context($student);
            match ($inactiveLevel) {
                'lesson' => $lesson->update(['status' => AcademicStatus::Inactive]),
                'module' => $module->update(['status' => AcademicStatus::Inactive]),
                'competency' => $competency->update(['status' => AcademicStatus::Inactive]),
            };

            $this->actingAs($student)->get(route('student.classes.show', $learningClass))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('progress.total_lessons', 0));
            $this->actingAs($student)->get(route('student.lessons.show', [$learningClass, $lesson]))
                ->assertForbidden();
        }

        $student = $this->userWithRole('Student');
        [, $learningClass, $lesson, $module] = $this->context($student);
        $module->delete();
        $this->actingAs($student)->get(route('student.lessons.show', [$learningClass, $lesson]))
            ->assertForbidden();

        $student = $this->userWithRole('Student');
        [, $learningClass, $lesson] = $this->context($student);
        $lesson->delete();
        $this->actingAs($student)->get("/student/classes/{$learningClass->id}/lessons/{$lesson->id}")
            ->assertNotFound();
    }

    public function test_authorized_student_can_view_pdf_and_image_files(): void
    {
        foreach ([LessonType::Document, LessonType::Image] as $type) {
            $student = $this->userWithRole('Student');
            [, $learningClass, $lesson] = $this->context($student, lessonType: $type);
            $path = $type === LessonType::Document
                ? "lesson-files/{$lesson->id}/guide.pdf"
                : "lesson-files/{$lesson->id}/diagram.png";
            $lesson->update(['file_path' => $path]);
            Storage::disk('local')->put($path, 'private lesson file');

            $this->actingAs($student)->get(route('student.lessons.file', [$learningClass, $lesson]))
                ->assertOk();
        }
    }

    public function test_student_file_route_cannot_serve_another_course_or_arbitrary_path(): void
    {
        $student = $this->userWithRole('Student');
        [, $learningClass, , $module] = $this->context($student);
        $otherLesson = Lesson::factory()->document()->create();
        $otherPath = $otherLesson->managedFilePath();
        $this->assertNotNull($otherPath);
        Storage::disk('local')->put($otherPath, 'other course secret');

        $this->actingAs($student)->get(route('student.lessons.file', [$learningClass, $otherLesson]))
            ->assertForbidden();

        $malformed = Lesson::factory()->create([
            'module_id' => $module->id,
            'lesson_type' => LessonType::Document,
            'file_path' => '../secrets.pdf',
        ]);
        $this->actingAs($student)->get(
            route('student.lessons.file', [$learningClass, $malformed]).'?path='.urlencode($otherPath),
        )->assertNotFound();
    }

    /**
     * @return array{Enrollment, LearningClass, Lesson, Module, Competency}
     */
    private function context(
        User $student,
        EnrollmentStatus $enrollmentStatus = EnrollmentStatus::Active,
        LearningClassStatus $classStatus = LearningClassStatus::Active,
        LessonType $lessonType = LessonType::Text,
    ): array {
        $course = Course::factory()->create();
        $competency = Competency::factory()->for($course)->create(['sort_order' => 1]);
        $module = Module::factory()->for($competency)->create(['sort_order' => 1]);
        $lesson = Lesson::factory()->for($module)->create([
            'lesson_type' => $lessonType,
            'sort_order' => 1,
        ]);
        $learningClass = LearningClass::factory()->for($course)->create(['status' => $classStatus]);
        $enrollment = Enrollment::factory()->for($learningClass)->create([
            'student_id' => $student->id,
            'status' => $enrollmentStatus,
            'completed_at' => $enrollmentStatus === EnrollmentStatus::Completed ? now() : null,
        ]);

        return [$enrollment, $learningClass, $lesson, $module, $competency];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
