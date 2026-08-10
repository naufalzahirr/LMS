<?php

namespace Tests\Feature;

use App\Enums\AcademicStatus;
use App\Enums\AssessmentFeedbackMode;
use App\Enums\AssessmentPurpose;
use App\Enums\ClassAssessmentStatus;
use App\Enums\QuestionType;
use App\Enums\RemedialAssignmentStatus;
use App\Enums\StudentCompetencyStatus;
use App\Models\AssessmentAttempt;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\MasteryRule;
use App\Models\Module;
use App\Models\RemedialAssignment;
use App\Models\StudentCompetencyProgress;
use App\Services\AssessmentAnswerService;
use App\Services\AssessmentAttemptService;
use App\Services\ClassAssessmentService;
use App\Services\CompetencyPrerequisiteService;
use App\Services\CompetencyService;
use App\Services\LessonProgressService;
use App\Services\MasteryEvaluationService;
use App\Services\RemedialAssignmentService;
use App\Services\StudentCompetencyProgressService;
use Closure;
use Database\Seeders\AcademicSeeder;
use Database\Seeders\AssessmentAttemptSeeder;
use Database\Seeders\AssessmentSeeder;
use Database\Seeders\DeliverySeeder;
use Database\Seeders\MasteryLearningSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsAssessmentAttemptContexts;
use Tests\TestCase;

class MasteryLearningWorkflowTest extends TestCase
{
    use BuildsAssessmentAttemptContexts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_prerequisites_must_be_same_course_unique_and_acyclic(): void
    {
        $course = Course::factory()->create();
        $a = Competency::factory()->for($course)->create();
        $b = Competency::factory()->for($course)->create();
        $c = Competency::factory()->for($course)->create();
        $other = Competency::factory()->create();
        $service = app(CompetencyPrerequisiteService::class);

        $service->add($a, $b);
        $service->add($b, $c);

        $this->expectValidation(fn () => $service->add($a, $a));
        $this->expectValidation(fn () => $service->add($a, $b));
        $this->expectValidation(fn () => $service->add($a, $other));
        $this->expectValidation(fn () => $service->add($c, $a));
        $this->expectValidation(fn () => app(CompetencyService::class)->update($b, [
            'course_id' => $other->course_id,
            'code' => $b->code,
            'name' => $b->name,
            'slug' => $b->slug,
            'description' => $b->description,
            'learning_objectives' => $b->learning_objectives,
            'sort_order' => $b->sort_order,
            'status' => $b->status,
        ]));
        $this->assertDatabaseCount('competency_prerequisites', 2);
    }

    public function test_lesson_completion_and_reopen_refresh_readiness_without_downgrading_mastery(): void
    {
        $context = $this->masteryContext();
        $progressService = app(LessonProgressService::class);
        $progressService->complete($context['student'], $context['enrollment'], $context['lesson']);

        $this->assertSame(
            StudentCompetencyStatus::ReadyForAssessment,
            $this->progress($context)->status,
        );

        $progressService->reopen($context['student'], $context['enrollment'], $context['lesson']);
        $this->assertSame(StudentCompetencyStatus::Learning, $this->progress($context)->status);

        $this->progress($context)->update([
            'status' => StudentCompetencyStatus::Mastered,
            'mastered_at' => now(),
        ]);
        $progressService->complete($context['student'], $context['enrollment'], $context['lesson']);
        $progressService->reopen($context['student'], $context['enrollment'], $context['lesson']);
        $this->assertSame(StudentCompetencyStatus::Mastered, $this->progress($context)->status);

        $empty = Competency::factory()->for($context['class']->course)->create();
        $emptyProgress = app(StudentCompetencyProgressService::class)->refresh($context['enrollment'], $empty);
        $this->assertSame(StudentCompetencyStatus::Learning, $emptyProgress->status);
    }

    public function test_mastery_start_requires_ready_and_revalidates_live_question_composition(): void
    {
        $context = $this->masteryContext();
        $attempts = app(AssessmentAttemptService::class);
        $prerequisite = Competency::factory()->for($context['class']->course)->create();
        app(CompetencyPrerequisiteService::class)->add($context['competency'], $prerequisite);

        $this->expectValidation(fn () => $attempts->startAttempt(
            $context['student'],
            $context['enrollment'],
            $context['assignment'],
        ));
        StudentCompetencyProgress::factory()->mastered()->create([
            'enrollment_id' => $context['enrollment']->id,
            'competency_id' => $prerequisite->id,
        ]);
        $this->progress($context)->update(['status' => StudentCompetencyStatus::Learning]);

        $this->expectValidation(fn () => $attempts->startAttempt(
            $context['student'],
            $context['enrollment'],
            $context['assignment'],
        ));

        $this->progress($context)->update(['status' => StudentCompetencyStatus::ReadyForAssessment]);
        $question = $context['questions'][QuestionType::MultipleChoice->value];
        $question->update(['status' => AcademicStatus::Inactive]);
        $this->expectValidation(fn () => $attempts->startAttempt(
            $context['student'],
            $context['enrollment'],
            $context['assignment'],
        ));

        $this->assertDatabaseCount('assessment_attempts', 0);
        $this->assertDatabaseCount('assessment_attempt_questions', 0);
    }

    public function test_failed_mastery_snapshots_remedial_and_completion_allows_a_passing_retry(): void
    {
        $context = $this->masteryContext();
        $failed = $this->submitMultipleChoice($context, false);
        $progress = $this->progress($context);
        $remedial = RemedialAssignment::query()->firstOrFail();

        $this->assertSame('0.00', $failed->percentage);
        $this->assertSame(StudentCompetencyStatus::NeedsRemedial, $progress->status);
        $this->assertSame(1, $progress->total_mastery_attempts);
        $this->assertSame(RemedialAssignmentStatus::Assigned, $remedial->status);
        $this->assertDatabaseHas('remedial_assignment_lessons', [
            'remedial_assignment_id' => $remedial->id,
            'lesson_id' => $context['lesson']->id,
        ]);
        $this->expectValidation(fn () => app(AssessmentAttemptService::class)->startAttempt(
            $context['student'],
            $context['enrollment'],
            $context['assignment'],
        ));

        $item = $remedial->lessons()->firstOrFail();
        app(RemedialAssignmentService::class)->completeLesson($context['student'], $remedial, $item);
        $this->assertSame(StudentCompetencyStatus::ReadyForAssessment, $this->progress($context)->status);

        $passed = $this->submitMultipleChoice($context, true);
        $progress = $this->progress($context);
        $this->assertSame('100.00', $passed->percentage);
        $this->assertSame(StudentCompetencyStatus::Mastered, $progress->status);
        $this->assertSame('100.00', $progress->best_score);
        $this->assertSame(2, $progress->total_mastery_attempts);
    }

    public function test_regrade_recalculates_best_score_and_supersedes_an_open_remedial(): void
    {
        $context = $this->masteryContext();
        $attempt = $this->submitMultipleChoice($context, false);
        $attempt->update([
            'earned_points' => $attempt->max_points,
            'percentage' => '100.00',
        ]);

        app(MasteryEvaluationService::class)->evaluate($attempt->refresh());

        $this->assertSame(StudentCompetencyStatus::Mastered, $this->progress($context)->status);
        $this->assertDatabaseHas('remedial_assignments', [
            'enrollment_id' => $context['enrollment']->id,
            'status' => RemedialAssignmentStatus::Superseded->value,
        ]);
    }

    public function test_attempt_limit_cannot_drop_below_usage_and_an_increase_refreshes_completed_remedial(): void
    {
        $context = $this->masteryContext(['max_attempts' => 1]);
        $this->submitMultipleChoice($context, false);
        $remedial = RemedialAssignment::query()->firstOrFail();
        app(RemedialAssignmentService::class)->completeLesson(
            $context['student'],
            $remedial,
            $remedial->lessons()->firstOrFail(),
        );
        $this->assertSame(StudentCompetencyStatus::NeedsRemedial, $this->progress($context)->status);

        $service = app(ClassAssessmentService::class);
        $this->expectValidation(fn () => $service->update($context['assignment'], $this->assignmentData(0)));
        $service->update($context['assignment'], $this->assignmentData(2));

        $this->assertSame(StudentCompetencyStatus::ReadyForAssessment, $this->progress($context)->status);
    }

    public function test_remedial_visibility_is_limited_to_owner_admin_and_exact_class_tutor(): void
    {
        $context = $this->masteryContext();
        $this->submitMultipleChoice($context, false);
        $remedial = RemedialAssignment::query()->firstOrFail();
        $assignedTutor = $this->userWithAssessmentRole('Tutor');
        $otherTutor = $this->userWithAssessmentRole('Tutor');
        $otherStudent = $this->userWithAssessmentRole('Student');
        $context['class']->tutors()->attach($assignedTutor);

        $this->actingAs($context['student'])
            ->get(route('student.remedials.show', $remedial))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('student/remedials/Show'));
        $this->actingAs($otherStudent)->get(route('student.remedials.show', $remedial))->assertForbidden();
        $this->actingAs($assignedTutor)
            ->get(route('tutor.remedials.show', $remedial))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('remedials/Manage'));
        $this->actingAs($otherTutor)->get(route('tutor.remedials.show', $remedial))->assertForbidden();
    }

    public function test_only_admin_can_change_a_mastery_rule(): void
    {
        $context = $this->masteryContext();
        $admin = $this->userWithAssessmentRole('Admin');
        $tutor = $this->userWithAssessmentRole('Tutor');
        $context['class']->tutors()->attach($tutor);
        $payload = [
            'learning_class_assessment_id' => $context['assignment']->id,
            'mastery_score' => '80.00',
            'require_remedial' => true,
            'status' => 'active',
            'remedial_lesson_ids' => [$context['lesson']->id],
        ];
        $url = route('admin.classes.mastery-rules.update', [$context['class'], $context['competency']]);

        $this->actingAs($tutor)->put($url, $payload)->assertForbidden();
        $this->actingAs($admin)->put($url, $payload)->assertRedirect();
        $this->assertDatabaseHas('mastery_rules', [
            'learning_class_id' => $context['class']->id,
            'competency_id' => $context['competency']->id,
            'mastery_score' => '80.00',
        ]);
    }

    public function test_mastery_demo_seeder_is_idempotent_and_shows_pass_and_remedial_states(): void
    {
        $this->seed([
            AcademicSeeder::class,
            DeliverySeeder::class,
            AssessmentSeeder::class,
            AssessmentAttemptSeeder::class,
            MasteryLearningSeeder::class,
            MasteryLearningSeeder::class,
        ]);

        $this->assertDatabaseCount('mastery_rules', 1);
        $this->assertSame(2, AssessmentAttempt::query()
            ->whereHas('classAssessment.assessment', fn ($query) => $query->where('code', 'HTML-MASTERY-01'))
            ->count());
        $this->assertDatabaseCount('remedial_assignments', 1);
        $this->assertSame(1, StudentCompetencyProgress::query()
            ->where('status', StudentCompetencyStatus::Mastered->value)
            ->count());
        $this->assertSame(1, StudentCompetencyProgress::query()
            ->where('status', StudentCompetencyStatus::NeedsRemedial->value)
            ->count());
    }

    /** @param array<string, mixed> $assignmentOverrides
     * @return array<string, mixed>
     */
    private function masteryContext(array $assignmentOverrides = []): array
    {
        $context = $this->makeAssessmentContext(
            [QuestionType::MultipleChoice],
            $assignmentOverrides,
        );
        $competency = $context['assessment']->competency;
        $context['assessment']->update(['purpose' => AssessmentPurpose::Mastery]);
        $module = Module::factory()->for($competency)->create();
        $lesson = Lesson::factory()->for($module)->create();
        $rule = MasteryRule::factory()->create([
            'learning_class_id' => $context['class']->id,
            'competency_id' => $competency->id,
            'learning_class_assessment_id' => $context['assignment']->id,
            'mastery_score' => '80.00',
            'require_remedial' => true,
        ]);
        $rule->remedialLessons()->attach($lesson->id, ['sort_order' => 0]);
        StudentCompetencyProgress::factory()->ready()->create([
            'enrollment_id' => $context['enrollment']->id,
            'competency_id' => $competency->id,
        ]);

        return $context + compact('competency', 'module', 'lesson', 'rule');
    }

    /** @param array<string, mixed> $context */
    private function submitMultipleChoice(array $context, bool $correct): AssessmentAttempt
    {
        $attempts = app(AssessmentAttemptService::class);
        $attempt = $attempts->startAttempt($context['student'], $context['enrollment'], $context['assignment']);
        $question = $attempt->attemptQuestions()->with('options')->firstOrFail();
        $option = $question->options->firstWhere('is_correct', $correct);
        app(AssessmentAnswerService::class)->save($context['student'], $attempt, $question, [
            'answer_text' => null,
            'answer_boolean' => null,
            'selected_option_ids' => [$option->id],
        ]);

        return $attempts->submit($context['student'], $attempt);
    }

    /** @param array<string, mixed> $context */
    private function progress(array $context): StudentCompetencyProgress
    {
        return StudentCompetencyProgress::query()
            ->where('enrollment_id', $context['enrollment']->id)
            ->where('competency_id', $context['competency']->id)
            ->firstOrFail();
    }

    /** @return array{opens_at: null, closes_at: null, max_attempts: int, status: ClassAssessmentStatus, feedback_mode: AssessmentFeedbackMode} */
    private function assignmentData(int $maxAttempts): array
    {
        return [
            'opens_at' => null,
            'closes_at' => null,
            'max_attempts' => $maxAttempts,
            'status' => ClassAssessmentStatus::Active,
            'feedback_mode' => AssessmentFeedbackMode::AfterFinalAttempt,
        ];
    }

    private function expectValidation(Closure $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a validation exception.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }
}
