<?php

namespace Tests\Feature\Student;

use App\Enums\AcademicStatus;
use App\Enums\LessonProgressStatus;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Services\LearningProgressQueryService;
use App\Services\LessonService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LessonProgressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_opening_lesson_starts_progress_and_repeated_views_preserve_started_at(): void
    {
        [$student, $enrollment, $learningClass, $lesson] = $this->context();
        $this->travelTo(now()->startOfSecond());

        $this->actingAs($student)->get(route('student.lessons.show', [$learningClass, $lesson]))->assertOk();
        $progress = LessonProgress::query()->firstOrFail();
        $startedAt = $progress->started_at;
        $firstViewedAt = $progress->last_viewed_at;
        $this->assertSame(LessonProgressStatus::InProgress, $progress->status);
        $this->assertNotNull($startedAt);

        $this->travel(10)->minutes();
        $this->actingAs($student)->get(route('student.lessons.show', [$learningClass, $lesson]))->assertOk();
        $progress->refresh();

        $this->assertTrue($progress->enrollment->is($enrollment));
        $this->assertTrue($progress->lesson->is($lesson));
        $this->assertTrue($enrollment->lessonProgress->contains($progress));
        $this->assertTrue($lesson->progressRecords->contains($progress));
        $this->assertTrue($progress->started_at?->equalTo($startedAt) ?? false);
        $this->assertTrue($progress->last_viewed_at?->greaterThan($firstViewedAt) ?? false);
        $this->assertDatabaseCount('lesson_progress', 1);
    }

    public function test_completed_lesson_stays_completed_when_opened_again(): void
    {
        [$student, $enrollment, $learningClass, $lesson] = $this->context();
        $progress = LessonProgress::factory()->for($enrollment)->for($lesson)->completed()->create();
        $completedAt = $progress->completed_at;

        $this->travel(5)->minutes();
        $this->actingAs($student)->get(route('student.lessons.show', [$learningClass, $lesson]))->assertOk();
        $progress->refresh();

        $this->assertSame(LessonProgressStatus::Completed, $progress->status);
        $this->assertTrue($progress->completed_at?->equalTo($completedAt) ?? false);
    }

    public function test_student_can_complete_directly_and_reopen_lesson(): void
    {
        [$student, , $learningClass, $lesson] = $this->context();

        $this->actingAs($student)->patch(route('student.lesson-progress.update', [$learningClass, $lesson]), [
            'status' => LessonProgressStatus::Completed->value,
        ])->assertRedirect();
        $progress = LessonProgress::query()->firstOrFail();
        $startedAt = $progress->started_at;
        $this->assertSame(LessonProgressStatus::Completed, $progress->status);
        $this->assertNotNull($progress->started_at);
        $this->assertNotNull($progress->completed_at);
        $this->assertNotNull($progress->last_viewed_at);

        $this->travel(5)->minutes();
        $this->actingAs($student)->patch(route('student.lesson-progress.update', [$learningClass, $lesson]), [
            'status' => LessonProgressStatus::InProgress->value,
        ])->assertRedirect();
        $progress->refresh();
        $this->assertSame(LessonProgressStatus::InProgress, $progress->status);
        $this->assertNull($progress->completed_at);
        $this->assertTrue($progress->started_at?->equalTo($startedAt) ?? false);
    }

    public function test_same_student_has_separate_progress_for_different_enrollments(): void
    {
        [$student, $firstEnrollment, $firstClass, $lesson] = $this->context();
        $secondClass = LearningClass::factory()->for($firstClass->course)->create();
        $secondEnrollment = Enrollment::factory()->for($secondClass)->create(['student_id' => $student->id]);

        $this->actingAs($student)->patch(route('student.lesson-progress.update', [$firstClass, $lesson]), [
            'status' => 'completed',
        ])->assertRedirect();
        $this->actingAs($student)->get(route('student.lessons.show', [$secondClass, $lesson]))->assertOk();

        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $firstEnrollment->id,
            'lesson_id' => $lesson->id,
            'status' => LessonProgressStatus::Completed->value,
        ]);
        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $secondEnrollment->id,
            'lesson_id' => $lesson->id,
            'status' => LessonProgressStatus::InProgress->value,
        ]);
    }

    public function test_cross_class_and_cross_course_progress_spoofing_is_rejected(): void
    {
        [$student, , $learningClass, $lesson] = $this->context();
        $sameCourseClass = LearningClass::factory()->for($learningClass->course)->create();
        $otherCourseLesson = Lesson::factory()->create();

        $this->actingAs($student)->patch(route('student.lesson-progress.update', [$sameCourseClass, $lesson]), [
            'status' => 'completed',
        ])->assertForbidden();
        $this->actingAs($student)->patch(route('student.lesson-progress.update', [$learningClass, $otherCourseLesson]), [
            'status' => 'completed',
        ])->assertForbidden();
        $this->assertDatabaseCount('lesson_progress', 0);
    }

    public function test_progress_calculation_counts_only_active_visible_lessons_for_enrollment(): void
    {
        [$student, $enrollment, $learningClass, $activeLesson, $module, $competency] = $this->context();
        $secondActive = Lesson::factory()->for($module)->create();
        Lesson::factory()->for($module)->create(['status' => AcademicStatus::Inactive]);
        $deleted = Lesson::factory()->for($module)->create();
        $deleted->delete();
        $inactiveModule = Module::factory()->for($competency)->create(['status' => AcademicStatus::Inactive]);
        Lesson::factory()->for($inactiveModule)->create();
        $inactiveCompetency = Competency::factory()->for($learningClass->course)->create([
            'status' => AcademicStatus::Inactive,
        ]);
        Lesson::factory()->for(Module::factory()->for($inactiveCompetency))->create();
        LessonProgress::factory()->for($enrollment)->for($activeLesson)->completed()->create();

        $summaries = app(LearningProgressQueryService::class)->summariesForEnrollments(
            new Collection([$enrollment]),
        );

        $this->assertSame(2, $summaries[$enrollment->id]['total_lessons']);
        $this->assertSame(1, $summaries[$enrollment->id]['completed_lessons']);
        $this->assertSame(50, $summaries[$enrollment->id]['percentage']);
        $this->assertSame($secondActive->id, $summaries[$enrollment->id]['continue_lesson_id']);
        $this->actingAs($student)->get(route('student.classes.show', $learningClass))
            ->assertInertia(fn (Assert $page) => $page->where('progress.percentage', 50));
    }

    public function test_zero_lesson_course_returns_zero_percent_and_counts_are_enrollment_specific(): void
    {
        [$student, $enrollment, $learningClass, $lesson] = $this->context();
        $otherStudent = $this->userWithRole('Student');
        $otherEnrollment = Enrollment::factory()->for($learningClass)->create(['student_id' => $otherStudent->id]);
        LessonProgress::factory()->for($enrollment)->for($lesson)->completed()->create();

        $summaries = app(LearningProgressQueryService::class)->summariesForEnrollments(
            new Collection([$enrollment, $otherEnrollment]),
        );
        $this->assertSame(1, $summaries[$enrollment->id]['completed_lessons']);
        $this->assertSame(0, $summaries[$otherEnrollment->id]['completed_lessons']);

        $emptyCourse = Course::factory()->create();
        $emptyClass = LearningClass::factory()->for($emptyCourse)->create();
        $emptyEnrollment = Enrollment::factory()->for($emptyClass)->create(['student_id' => $student->id]);
        $emptySummary = app(LearningProgressQueryService::class)->summariesForEnrollments(
            new Collection([$emptyEnrollment]),
        );
        $this->assertSame(0, $emptySummary[$emptyEnrollment->id]['total_lessons']);
        $this->assertSame(0, $emptySummary[$emptyEnrollment->id]['percentage']);
    }

    public function test_continue_learning_prefers_recent_incomplete_then_first_ordered_incomplete(): void
    {
        [, $enrollment, , $firstLesson, $module] = $this->context();
        $secondLesson = Lesson::factory()->for($module)->create(['sort_order' => 2]);
        $thirdLesson = Lesson::factory()->for($module)->create(['sort_order' => 3]);
        LessonProgress::factory()->for($enrollment)->for($secondLesson)->inProgress()->create([
            'last_viewed_at' => now()->subHour(),
        ]);
        LessonProgress::factory()->for($enrollment)->for($thirdLesson)->inProgress()->create([
            'last_viewed_at' => now(),
        ]);

        $service = app(LearningProgressQueryService::class);
        $summary = $service->summariesForEnrollments(new Collection([$enrollment]));
        $this->assertSame($thirdLesson->id, $summary[$enrollment->id]['continue_lesson_id']);

        LessonProgress::query()->delete();
        $summary = $service->summariesForEnrollments(new Collection([$enrollment]));
        $this->assertSame($firstLesson->id, $summary[$enrollment->id]['continue_lesson_id']);
    }

    public function test_database_enforces_unique_progress_and_restricts_historical_deletion(): void
    {
        [, $enrollment, , $lesson] = $this->context();
        LessonProgress::factory()->for($enrollment)->for($lesson)->create();

        try {
            LessonProgress::factory()->for($enrollment)->for($lesson)->create();
            $this->fail('Duplicate lesson progress should have been rejected.');
        } catch (QueryException) {
            $this->assertDatabaseCount('lesson_progress', 1);
        }

        try {
            $lesson->forceDelete();
            $this->fail('A lesson with progress should not be force deleted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('lessons', ['id' => $lesson->id]);
        }

        $this->expectException(QueryException::class);
        $enrollment->delete();
    }

    public function test_default_progress_factory_builds_a_lesson_in_the_enrollments_course(): void
    {
        $progress = LessonProgress::factory()->create();

        $this->assertSame(
            $progress->enrollment->learningClass->course_id,
            $progress->lesson->module->competency->course_id,
        );
    }

    public function test_normal_lesson_delete_soft_deletes_content_without_deleting_history(): void
    {
        [, $enrollment, , $lesson] = $this->context();
        $progress = LessonProgress::factory()->for($enrollment)->for($lesson)->create();

        app(LessonService::class)->delete($lesson);

        $this->assertSoftDeleted($lesson);
        $this->assertDatabaseHas('lesson_progress', ['id' => $progress->id]);
    }

    /** @return array{User, Enrollment, LearningClass, Lesson, Module, Competency} */
    private function context(): array
    {
        $student = $this->userWithRole('Student');
        $course = Course::factory()->create();
        $competency = Competency::factory()->for($course)->create(['sort_order' => 1]);
        $module = Module::factory()->for($competency)->create(['sort_order' => 1]);
        $lesson = Lesson::factory()->for($module)->create(['sort_order' => 1]);
        $learningClass = LearningClass::factory()->for($course)->create();
        $enrollment = Enrollment::factory()->for($learningClass)->create(['student_id' => $student->id]);

        return [$student, $enrollment, $learningClass, $lesson, $module, $competency];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
