<?php

namespace Tests\Feature;

use App\Enums\AssessmentAttemptStatus;
use App\Enums\AssessmentFeedbackMode;
use App\Enums\LearningClassStatus;
use App\Enums\QuestionType;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptQuestion;
use App\Models\Question;
use App\Services\AssessmentAnswerService;
use App\Services\AssessmentAttemptService;
use App\Services\AssessmentGradingService;
use App\Services\StudentAssessmentPayloadService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsAssessmentAttemptContexts;
use Tests\TestCase;

class AssessmentGradingAndFeedbackTest extends TestCase
{
    use BuildsAssessmentAttemptContexts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_assigned_tutor_can_partially_grade_complete_and_regrade_essays_after_class_completion(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice, QuestionType::Essay]);
        $firstEssay = $context['questions'][QuestionType::Essay->value];
        $secondEssay = Question::factory()
            ->for($firstEssay->questionBank)
            ->for($firstEssay->competency)
            ->essay()
            ->create(['prompt' => 'Second essay prompt']);
        $context['assessment']->assessmentQuestions()->create([
            'question_id' => $secondEssay->id,
            'points' => '2.00',
            'sort_order' => 2,
        ]);
        $attempt = $this->submitPendingAttempt($context);
        $tutor = $this->userWithAssessmentRole('Tutor');
        $context['class']->tutors()->attach($tutor);
        $context['class']->update(['status' => LearningClassStatus::Completed]);
        $essays = $attempt->attemptQuestions()->where('question_type', QuestionType::Essay->value)->get();
        $grading = app(AssessmentGradingService::class);

        $partial = $grading->grade($attempt, $tutor, [[
            'attempt_question_id' => $essays[0]->id,
            'manual_score' => '3.00',
            'feedback' => 'Good reasoning.',
        ]]);
        $this->assertSame(AssessmentAttemptStatus::PendingGrading, $partial->status);
        $this->assertNull($partial->earned_points);
        $this->assertDatabaseHas('assessment_answers', [
            'assessment_attempt_question_id' => $essays[0]->id,
            'manual_score' => '3.00',
            'feedback' => 'Good reasoning.',
            'graded_by' => $tutor->id,
        ]);

        $graded = $grading->grade($attempt, $tutor, [[
            'attempt_question_id' => $essays[1]->id,
            'manual_score' => '1.00',
            'feedback' => 'Add an example.',
        ]]);
        $this->assertSame(AssessmentAttemptStatus::Graded, $graded->status);
        $this->assertSame('4.00', $graded->manual_points);
        $this->assertSame('6.00', $graded->earned_points);
        $this->assertSame('75.00', $graded->percentage);
        $this->assertNotNull($graded->graded_at);

        $regraded = $grading->grade($graded, $tutor, [[
            'attempt_question_id' => $essays[0]->id,
            'manual_score' => '4.00',
            'feedback' => 'Regraded after review.',
        ]]);
        $this->assertSame('5.00', $regraded->manual_points);
        $this->assertSame('7.00', $regraded->earned_points);
        $this->assertSame('87.50', $regraded->percentage);
    }

    public function test_only_admin_or_exact_class_tutor_can_grade_and_scores_stay_within_question_points(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::Essay]);
        $attempt = $this->submitPendingAttempt($context);
        $essay = $attempt->attemptQuestions()->firstOrFail();
        $assignedTutor = $this->userWithAssessmentRole('Tutor');
        $context['class']->tutors()->attach($assignedTutor);
        $grading = app(AssessmentGradingService::class);

        foreach (['Tutor', 'Student', 'Parent'] as $role) {
            $unauthorized = $role === 'Student' ? $context['student'] : $this->userWithAssessmentRole($role);

            try {
                $grading->grade($attempt, $unauthorized, [$this->grade($essay, '1.00')]);
                $this->fail("{$role} unexpectedly graded the attempt.");
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        foreach (['-0.01', '4.01'] as $score) {
            try {
                $grading->grade($attempt, $assignedTutor, [$this->grade($essay, $score)]);
                $this->fail('An out-of-range score was accepted.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $admin = $this->userWithAssessmentRole('Admin');
        $this->assertSame(
            AssessmentAttemptStatus::Graded,
            $grading->grade($attempt, $admin, [$this->grade($essay, '4.00')])->status,
        );
    }

    public function test_grading_routes_are_class_scoped_for_tutors_and_allow_completed_classes(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::Essay]);
        $attempt = $this->submitPendingAttempt($context);
        $essay = $attempt->attemptQuestions()->firstOrFail();
        $tutor = $this->userWithAssessmentRole('Tutor');
        $otherTutor = $this->userWithAssessmentRole('Tutor');
        $context['class']->tutors()->attach($tutor);
        $context['class']->update(['status' => LearningClassStatus::Completed]);

        $this->actingAs($otherTutor)
            ->get(route('tutor.class-assessment-attempts.edit', [$context['class'], $context['assignment'], $attempt]))
            ->assertForbidden();

        $this->actingAs($tutor)
            ->patch(route('tutor.class-assessment-attempts.update', [$context['class'], $context['assignment'], $attempt]), [
                'grades' => [$this->grade($essay, '3.50')],
            ])
            ->assertRedirect();
        $this->assertSame(AssessmentAttemptStatus::Graded, $attempt->refresh()->status);
    }

    public function test_grading_edit_route_renders_the_correct_assessment_and_student_scoped_to_the_class(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::Essay]);
        $attempt = $this->submitPendingAttempt($context);
        $tutor = $this->userWithAssessmentRole('Tutor');
        $context['class']->tutors()->attach($tutor);

        $this->actingAs($tutor)
            ->get(route('tutor.class-assessment-attempts.edit', [$context['class'], $context['assignment'], $attempt]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('assessment-attempts/Grade')
                ->where('attempt.id', $attempt->id)
                ->where('attempt.assessment_title', $context['assessment']->title)
                ->where('attempt.student', $context['student']->name));
    }

    public function test_grading_edit_route_returns_a_controlled_404_when_the_assignment_no_longer_references_a_valid_assessment(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::Essay]);
        $attempt = $this->submitPendingAttempt($context);
        $tutor = $this->userWithAssessmentRole('Tutor');
        $context['class']->tutors()->attach($tutor);

        // Simulates data corruption that bypasses AssessmentService::delete()'s
        // "cannot delete an assessment already assigned/used" guard (e.g. a
        // future code path or manual database edit) — the grading page must
        // fail with a controlled 404, never a null-property crash.
        $context['assessment']->delete();

        $this->actingAs($tutor)
            ->get(route('tutor.class-assessment-attempts.edit', [$context['class'], $context['assignment'], $attempt]))
            ->assertNotFound();
    }

    public function test_http_grading_update_accepts_a_partial_grades_array(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::Essay, QuestionType::Essay]);
        $attempt = $this->submitPendingAttempt($context);
        $essays = $attempt->attemptQuestions()->where('question_type', QuestionType::Essay->value)->get();
        $tutor = $this->userWithAssessmentRole('Tutor');
        $context['class']->tutors()->attach($tutor);

        $this->actingAs($tutor)
            ->patch(route('tutor.class-assessment-attempts.update', [$context['class'], $context['assignment'], $attempt]), [
                'grades' => [$this->grade($essays[0], '2.00')],
            ])
            ->assertRedirect();

        $this->assertSame(AssessmentAttemptStatus::PendingGrading, $attempt->refresh()->status);
        $this->assertDatabaseHas('assessment_answers', [
            'assessment_attempt_question_id' => $essays[0]->id,
            'manual_score' => '2.00',
        ]);
        $this->assertDatabaseHas('assessment_answers', [
            'assessment_attempt_question_id' => $essays[1]->id,
            'manual_score' => null,
        ]);
    }

    public function test_grading_payload_exposes_auto_graded_questions_alongside_essays(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice, QuestionType::Essay]);
        $attempt = $this->submitPendingAttempt($context);
        $tutor = $this->userWithAssessmentRole('Tutor');
        $context['class']->tutors()->attach($tutor);

        $this->actingAs($tutor)
            ->get(route('tutor.class-assessment-attempts.edit', [$context['class'], $context['assignment'], $attempt]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('assessment-attempts/Grade')
                ->has('essays', 1)
                ->has('auto_graded', 1)
                ->where('auto_graded.0.question_type', 'multiple_choice')
                ->where('auto_graded.0.is_correct', true));
    }

    public function test_queue_pending_count_is_independent_of_the_active_status_filter(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::Essay]);
        $tutor = $this->userWithAssessmentRole('Tutor');
        $context['class']->tutors()->attach($tutor);
        $grading = app(AssessmentGradingService::class);

        $firstAttempt = $this->submitPendingAttempt($context);
        $essay = $firstAttempt->attemptQuestions()->firstOrFail();
        $grading->grade($firstAttempt, $tutor, [$this->grade($essay, '3.00')]);
        $this->assertSame(AssessmentAttemptStatus::Graded, $firstAttempt->refresh()->status);

        $this->submitPendingAttempt($context);

        $this->actingAs($tutor)
            ->get(route('tutor.class-assessment-attempts.index', [$context['class'], $context['assignment'], 'status' => 'graded']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('assessment-attempts/Index')
                ->where('pending_count', 1)
                ->has('attempts.data', 1));
    }

    public function test_queue_student_search_filters_by_name_or_email_without_leaking_across_assignments(): void
    {
        $matching = $this->makeAssessmentContext([QuestionType::Essay]);
        $matching['student']->update(['name' => 'Ada Lovelace', 'email' => 'ada@example.test']);
        $matchingAttempt = $this->submitPendingAttempt($matching);
        $tutor = $this->userWithAssessmentRole('Tutor');
        $matching['class']->tutors()->attach($tutor);

        $other = $this->makeAssessmentContext([QuestionType::Essay]);
        $other['student']->update(['name' => 'Ada Byron', 'email' => 'byron@example.test']);
        $this->submitPendingAttempt($other);

        $this->actingAs($tutor)
            ->get(route('tutor.class-assessment-attempts.index', [$matching['class'], $matching['assignment'], 'search' => 'Lovelace']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('assessment-attempts/Index')
                ->has('attempts.data', 1)
                ->where('attempts.data.0.id', $matchingAttempt->id)
                ->where('filters.search', 'Lovelace'));
    }

    public function test_previous_and_next_navigation_respects_the_active_status_filter_and_stays_within_the_assignment(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::Essay], ['max_attempts' => 5]);
        $tutor = $this->userWithAssessmentRole('Tutor');
        $context['class']->tutors()->attach($tutor);
        $grading = app(AssessmentGradingService::class);

        $pendingOne = $this->submitPendingAttempt($context);
        $graded = $this->submitPendingAttempt($context);
        $grading->grade($graded, $tutor, [$this->grade($graded->attemptQuestions()->firstOrFail(), '3.00')]);
        $pendingTwo = $this->submitPendingAttempt($context);

        // Filtered to pending_grading only, the Graded attempt in between must
        // be skipped entirely — Next/Previous stay within the filtered subset.
        // pendingTwo is the most recently submitted, so there is nothing newer
        // (previous) but pendingOne (older) is reachable via next.
        $this->actingAs($tutor)
            ->get(route('tutor.class-assessment-attempts.edit', [
                $context['class'], $context['assignment'], $pendingTwo, 'status' => 'pending_grading',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('assessment-attempts/Grade')
                ->where('previousUrl', null)
                ->where('nextUrl', route('tutor.class-assessment-attempts.edit', [
                    $context['class'], $context['assignment'], $pendingOne, 'status' => 'pending_grading',
                ])));
    }

    public function test_feedback_modes_release_only_the_allowed_result_detail(): void
    {
        $payloads = app(StudentAssessmentPayloadService::class);

        $scoreOnly = $this->makeAssessmentContext(
            [QuestionType::MultipleChoice],
            ['feedback_mode' => AssessmentFeedbackMode::ScoreOnly, 'max_attempts' => 1],
        );
        $scoreOnlyAttempt = $this->submitCorrectObjectiveAttempt($scoreOnly);
        $this->assertFalse($payloads->detailedFeedbackAllowed($scoreOnlyAttempt));

        $each = $this->makeAssessmentContext(
            [QuestionType::MultipleChoice],
            ['feedback_mode' => AssessmentFeedbackMode::AfterEachAttempt, 'max_attempts' => 2],
        );
        $eachAttempt = $this->submitCorrectObjectiveAttempt($each);
        $this->assertTrue($payloads->detailedFeedbackAllowed($eachAttempt));

        $final = $this->makeAssessmentContext(
            [QuestionType::MultipleChoice],
            ['feedback_mode' => AssessmentFeedbackMode::AfterFinalAttempt, 'max_attempts' => 2],
        );
        $first = $this->submitCorrectObjectiveAttempt($final);
        $this->assertFalse($payloads->detailedFeedbackAllowed($first));
        app(AssessmentAttemptService::class)->startAttempt(
            $final['student'],
            $final['enrollment'],
            $final['assignment'],
        );
        $this->assertFalse($payloads->detailedFeedbackAllowed($first->refresh()));
        $second = $this->submitCorrectObjectiveAttempt($final);
        $this->assertTrue($payloads->detailedFeedbackAllowed($second));

        $closed = $this->makeAssessmentContext(
            [QuestionType::MultipleChoice],
            ['feedback_mode' => AssessmentFeedbackMode::AfterFinalAttempt, 'max_attempts' => 2],
        );
        $closedAttempt = $this->submitCorrectObjectiveAttempt($closed);
        $closed['assignment']->update(['closes_at' => now()->subMinute()]);
        $this->assertTrue($payloads->detailedFeedbackAllowed($closedAttempt->refresh()));

        $pending = $this->makeAssessmentContext(
            [QuestionType::Essay],
            ['feedback_mode' => AssessmentFeedbackMode::AfterEachAttempt],
        );
        $this->assertFalse($payloads->detailedFeedbackAllowed($this->submitPendingAttempt($pending)));
    }

    public function test_hidden_feedback_result_payload_omits_questions_and_answer_keys(): void
    {
        $context = $this->makeAssessmentContext(
            [QuestionType::MultipleChoice],
            ['feedback_mode' => AssessmentFeedbackMode::ScoreOnly, 'max_attempts' => 1],
        );
        $attempt = $this->submitCorrectObjectiveAttempt($context);

        $this->actingAs($context['student'])
            ->get(route('student.assessment-attempts.result', $attempt))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/assessments/Result')
                ->where('result.detailed_feedback', false)
                ->missing('result.questions')
                ->missing('result.correct_boolean')
                ->missing('result.accepted_answers'));
    }

    /** @param array<string, mixed> $context */
    private function submitPendingAttempt(array $context): AssessmentAttempt
    {
        $attemptService = app(AssessmentAttemptService::class);
        $answerService = app(AssessmentAnswerService::class);
        $attempt = $attemptService->startAttempt($context['student'], $context['enrollment'], $context['assignment']);
        $attempt->load('attemptQuestions.options');

        foreach ($attempt->attemptQuestions as $question) {
            if ($question->question_type === QuestionType::MultipleChoice) {
                $answerService->save($context['student'], $attempt, $question, [
                    'answer_text' => null,
                    'answer_boolean' => null,
                    'selected_option_ids' => [$question->options->firstWhere('is_correct', true)->id],
                ]);
            } elseif ($question->question_type === QuestionType::Essay) {
                $answerService->save($context['student'], $attempt, $question, [
                    'answer_text' => 'Essay response',
                    'answer_boolean' => null,
                    'selected_option_ids' => [],
                ]);
            }
        }

        return $attemptService->submit($context['student'], $attempt);
    }

    /** @param array<string, mixed> $context */
    private function submitCorrectObjectiveAttempt(array $context): AssessmentAttempt
    {
        $attemptService = app(AssessmentAttemptService::class);
        $answerService = app(AssessmentAnswerService::class);
        $attempt = $attemptService->startAttempt($context['student'], $context['enrollment'], $context['assignment']);
        $question = $attempt->attemptQuestions()->with('options')->firstOrFail();
        $answerService->save($context['student'], $attempt, $question, [
            'answer_text' => null,
            'answer_boolean' => null,
            'selected_option_ids' => [$question->options->firstWhere('is_correct', true)->id],
        ]);

        return $attemptService->submit($context['student'], $attempt);
    }

    /** @return array{attempt_question_id: int, manual_score: string, feedback: string|null} */
    private function grade(AssessmentAttemptQuestion $question, string $score): array
    {
        return [
            'attempt_question_id' => $question->id,
            'manual_score' => $score,
            'feedback' => 'Written feedback.',
        ];
    }
}
