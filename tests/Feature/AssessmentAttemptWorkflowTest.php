<?php

namespace Tests\Feature;

use App\Enums\AssessmentAttemptStatus;
use App\Enums\AssessmentStatus;
use App\Enums\ClassAssessmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Enums\QuestionType;
use App\Models\AssessmentAttemptQuestion;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Services\AssessmentAnswerService;
use App\Services\AssessmentAttemptService;
use App\Services\AssessmentService;
use App\Services\ClassAssessmentService;
use App\Services\QuestionService;
use Closure;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsAssessmentAttemptContexts;
use Tests\TestCase;

class AssessmentAttemptWorkflowTest extends TestCase
{
    use BuildsAssessmentAttemptContexts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_start_creates_an_immutable_internal_snapshot_without_leaking_answer_keys(): void
    {
        $context = $this->makeAssessmentContext();
        $attempt = app(AssessmentAttemptService::class)->startAttempt(
            $context['student'],
            $context['enrollment'],
            $context['assignment'],
        );
        $attempt->load('attemptQuestions.options', 'attemptQuestions.acceptedAnswers');

        $this->assertSame(AssessmentAttemptStatus::InProgress, $attempt->status);
        $this->assertSame('12.00', $attempt->max_points);
        $this->assertCount(5, $attempt->attemptQuestions);
        $this->assertSame(range(0, 4), $attempt->attemptQuestions->pluck('sort_order')->all());

        $source = $context['questions'][QuestionType::MultipleChoice->value];
        $snapshot = $attempt->attemptQuestions->firstWhere('source_question_id', $source->id);
        $this->assertInstanceOf(AssessmentAttemptQuestion::class, $snapshot);
        $this->assertSame($source->prompt, $snapshot->prompt);
        $this->assertSame('2.00', $snapshot->points);
        $this->assertCount(2, $snapshot->options);
        $this->assertSame(1, $snapshot->options->where('is_correct', true)->count());
        $this->assertCount(
            2,
            $attempt->attemptQuestions
                ->firstWhere('question_type', QuestionType::ShortAnswer)
                ->acceptedAnswers,
        );

        $source->update(['prompt' => 'Changed source prompt']);
        $context['assessment']->assessmentQuestions()
            ->where('question_id', $source->id)
            ->update(['points' => '99.00']);
        $this->assertNotSame($source->refresh()->prompt, $snapshot->refresh()->prompt);
        $this->assertSame('2.00', $snapshot->points);

        $this->assertValidationException(fn () => app(QuestionService::class)->delete($source));
        $composition = $context['assessment']->assessmentQuestions()->firstWhere('question_id', $source->id);
        $this->assertValidationException(
            fn () => app(AssessmentService::class)->updateQuestionPoints($context['assessment'], $composition, '3.00'),
        );

        $this->actingAs($context['student'])
            ->get(route('student.assessment-attempts.show', $attempt))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/assessments/Attempt')
                ->has('attempt.questions', 5)
                ->missing('attempt.questions.0.correct_boolean')
                ->missing('attempt.questions.0.explanation')
                ->missing('attempt.questions.0.options.0.is_correct')
                ->missing('attempt.questions.3.accepted_answers')
                ->missing('attempt.questions.0.answer.auto_score')
                ->missing('attempt.questions.0.answer.manual_score')
                ->missing('attempt.questions.0.answer.is_correct'));
    }

    public function test_attempt_player_payload_surfaces_the_assignment_deadline(): void
    {
        $withDeadline = $this->makeAssessmentContext([QuestionType::MultipleChoice], [
            'closes_at' => now()->addDay(),
        ]);
        $attempt = app(AssessmentAttemptService::class)->startAttempt(
            $withDeadline['student'],
            $withDeadline['enrollment'],
            $withDeadline['assignment'],
        );

        $this->actingAs($withDeadline['student'])
            ->get(route('student.assessment-attempts.show', $attempt))
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/assessments/Attempt')
                ->where('attempt.closes_at', $withDeadline['assignment']->closes_at->toDateTimeString()));

        $withoutDeadline = $this->makeAssessmentContext([QuestionType::MultipleChoice], [
            'closes_at' => null,
        ]);
        $openAttempt = app(AssessmentAttemptService::class)->startAttempt(
            $withoutDeadline['student'],
            $withoutDeadline['enrollment'],
            $withoutDeadline['assignment'],
        );

        $this->actingAs($withoutDeadline['student'])
            ->get(route('student.assessment-attempts.show', $openAttempt))
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/assessments/Attempt')
                ->where('attempt.closes_at', null));
    }

    public function test_submission_invalidates_editable_history_and_submitted_attempt_url_redirects_to_result(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::Essay]);
        $attempt = app(AssessmentAttemptService::class)->startAttempt(
            $context['student'],
            $context['enrollment'],
            $context['assignment'],
        );

        $editableResponse = $this->actingAs($context['student'])
            ->get(route('student.assessment-attempts.show', $attempt))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/assessments/Attempt'));
        $this->assertTrue($editableResponse->inertiaPage()['encryptHistory'] ?? false);

        $this->post(route('student.assessment-attempts.submit', $attempt))
            ->assertRedirect(route('student.assessment-attempts.result', $attempt));
        $this->assertSame(AssessmentAttemptStatus::PendingGrading, $attempt->refresh()->status);

        $resultResponse = $this->get(route('student.assessment-attempts.result', $attempt))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/assessments/Result'));
        $this->assertTrue($resultResponse->inertiaPage()['clearHistory'] ?? false);

        // This is the server request Inertia performs when Back/popstate or a
        // persisted pageshow can no longer decrypt the stale attempt entry.
        $this->get(route('student.assessment-attempts.show', $attempt))
            ->assertRedirect(route('student.assessment-attempts.result', $attempt));
    }

    public function test_submitted_attempt_cannot_be_resubmitted_through_the_http_endpoint(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice]);
        $attempt = app(AssessmentAttemptService::class)->startAttempt(
            $context['student'],
            $context['enrollment'],
            $context['assignment'],
        );

        $this->actingAs($context['student'])
            ->post(route('student.assessment-attempts.submit', $attempt))
            ->assertRedirect(route('student.assessment-attempts.result', $attempt));
        $submittedAt = $attempt->refresh()->submitted_at?->toISOString();

        $this->postJson(route('student.assessment-attempts.submit', $attempt))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attempt');

        $attempt->refresh();
        $this->assertSame(AssessmentAttemptStatus::Graded, $attempt->status);
        $this->assertSame($submittedAt, $attempt->submitted_at?->toISOString());
        $this->get(route('student.assessment-attempts.show', $attempt))
            ->assertRedirect(route('student.assessment-attempts.result', $attempt));
    }

    public function test_start_requires_an_active_matching_delivery_and_open_window(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice]);
        $attempts = app(AssessmentAttemptService::class);

        foreach ([EnrollmentStatus::Withdrawn, EnrollmentStatus::Completed] as $status) {
            $context['enrollment']->update(['status' => $status]);
            $this->assertValidationException(fn () => $attempts->startAttempt(
                $context['student'],
                $context['enrollment']->refresh(),
                $context['assignment']->refresh(),
            ));
        }
        $context['enrollment']->update(['status' => EnrollmentStatus::Active]);

        $context['class']->update(['status' => LearningClassStatus::Inactive]);
        $this->assertValidationException(fn () => $attempts->startAttempt($context['student'], $context['enrollment'], $context['assignment']));
        $context['class']->update(['status' => LearningClassStatus::Active]);

        $context['assignment']->update(['status' => ClassAssessmentStatus::Inactive]);
        $this->assertValidationException(fn () => $attempts->startAttempt($context['student'], $context['enrollment'], $context['assignment']->refresh()));
        $context['assignment']->update(['status' => ClassAssessmentStatus::Active]);

        foreach ([AssessmentStatus::Draft, AssessmentStatus::Archived] as $status) {
            $context['assessment']->update(['status' => $status]);
            $this->assertValidationException(fn () => $attempts->startAttempt($context['student'], $context['enrollment'], $context['assignment']->refresh()));
        }
        $context['assessment']->update(['status' => AssessmentStatus::Published]);

        $context['assignment']->update(['opens_at' => now()->addHour()]);
        $this->assertValidationException(fn () => $attempts->startAttempt($context['student'], $context['enrollment'], $context['assignment']->refresh()));
        $context['assignment']->update(['opens_at' => null, 'closes_at' => now()->subMinute()]);
        $this->assertValidationException(fn () => $attempts->startAttempt($context['student'], $context['enrollment'], $context['assignment']->refresh()));

        $otherClass = LearningClass::factory()->for($context['class']->course)->create();
        $otherEnrollment = Enrollment::factory()->for($otherClass)->for($context['student'], 'student')->create();
        $context['assignment']->update(['closes_at' => null]);
        $this->assertValidationException(fn () => $attempts->startAttempt($context['student'], $otherEnrollment, $context['assignment']->refresh()));
    }

    public function test_existing_attempt_is_resumed_and_all_attempt_states_count_toward_the_limit(): void
    {
        $context = $this->makeAssessmentContext(
            [QuestionType::MultipleChoice],
            ['max_attempts' => 2],
        );
        $service = app(AssessmentAttemptService::class);
        $first = $service->startAttempt($context['student'], $context['enrollment'], $context['assignment']);
        $resumed = $service->startAttempt($context['student'], $context['enrollment'], $context['assignment']);

        $this->assertTrue($first->is($resumed));
        $this->assertDatabaseCount('assessment_attempts', 1);
        $service->submit($context['student'], $first);
        $second = $service->startAttempt($context['student'], $context['enrollment'], $context['assignment']);
        $this->assertSame(2, $second->attempt_number);
        $service->submit($context['student'], $second);
        $this->assertValidationException(
            fn () => $service->startAttempt($context['student'], $context['enrollment'], $context['assignment']),
        );
    }

    public function test_submission_closing_and_assignment_deletion_preserve_attempt_history(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice]);
        $service = app(AssessmentAttemptService::class);
        $attempt = $service->startAttempt($context['student'], $context['enrollment'], $context['assignment']);

        $context['assignment']->update(['closes_at' => now()->subMinute()]);
        $this->assertValidationException(fn () => $service->submit($context['student'], $attempt));
        $this->assertValidationException(fn () => app(ClassAssessmentService::class)->unassign($context['assignment']));
        $this->assertDatabaseHas('assessment_attempts', ['id' => $attempt->id]);
        $this->assertDatabaseHas('assessment_attempt_questions', ['assessment_attempt_id' => $attempt->id]);
    }

    public function test_answers_save_by_type_replace_selections_and_become_immutable_after_submission(): void
    {
        $context = $this->makeAssessmentContext();
        $attemptService = app(AssessmentAttemptService::class);
        $answerService = app(AssessmentAnswerService::class);
        $attempt = $attemptService->startAttempt($context['student'], $context['enrollment'], $context['assignment']);
        $attempt->load('attemptQuestions.options');
        $byType = $attempt->attemptQuestions->keyBy(fn (AssessmentAttemptQuestion $question): string => $question->question_type->value);

        $multipleChoice = $byType->get(QuestionType::MultipleChoice->value);
        $wrong = $multipleChoice->options->firstWhere('is_correct', false);
        $correct = $multipleChoice->options->firstWhere('is_correct', true);
        $answerService->save($context['student'], $attempt, $multipleChoice, $this->answer([$wrong->id]));
        $changed = $answerService->save($context['student'], $attempt, $multipleChoice, $this->answer([$correct->id]));
        $this->assertSame([$correct->id], $changed->selectedOptions->pluck('id')->all());

        $multipleSelect = $byType->get(QuestionType::MultipleSelect->value);
        $answerService->save(
            $context['student'],
            $attempt,
            $multipleSelect,
            $this->answer($multipleSelect->options->where('is_correct', true)->pluck('id')->all()),
        );
        $answerService->save($context['student'], $attempt, $byType->get(QuestionType::TrueFalse->value), [
            ...$this->answer(),
            'answer_boolean' => true,
        ]);
        $answerService->save($context['student'], $attempt, $byType->get(QuestionType::ShortAnswer->value), [
            ...$this->answer(),
            'answer_text' => '  LARAVEL  ',
        ]);
        $essay = $byType->get(QuestionType::Essay->value);
        $answerService->save($context['student'], $attempt, $essay, [
            ...$this->answer(),
            'answer_text' => 'A considered essay response.',
        ]);

        $submitted = $attemptService->submit($context['student'], $attempt);
        $this->assertSame(AssessmentAttemptStatus::PendingGrading, $submitted->status);
        $this->assertSame('8.00', $submitted->auto_points);
        $this->assertNull($submitted->earned_points);
        $this->assertNull($submitted->percentage);
        $this->assertNotNull($submitted->submitted_at);
        $this->assertValidationException(
            fn () => $answerService->save($context['student'], $submitted, $essay, [
                ...$this->answer(),
                'answer_text' => 'Changed too late',
            ]),
        );
        $this->assertValidationException(fn () => $attemptService->submit($context['student'], $submitted));
    }

    public function test_cross_attempt_questions_and_cross_question_options_are_rejected(): void
    {
        $first = $this->makeAssessmentContext([QuestionType::MultipleChoice, QuestionType::MultipleSelect]);
        $second = $this->makeAssessmentContext([QuestionType::MultipleChoice]);
        $attempts = app(AssessmentAttemptService::class);
        $answers = app(AssessmentAnswerService::class);
        $firstAttempt = $attempts->startAttempt($first['student'], $first['enrollment'], $first['assignment']);
        $secondAttempt = $attempts->startAttempt($second['student'], $second['enrollment'], $second['assignment']);
        $firstAttempt->load('attemptQuestions.options');
        $secondAttempt->load('attemptQuestions.options');

        $this->assertValidationException(fn () => $answers->save(
            $first['student'],
            $firstAttempt,
            $secondAttempt->attemptQuestions->first(),
            $this->answer(),
        ));

        $mc = $firstAttempt->attemptQuestions->firstWhere('question_type', QuestionType::MultipleChoice);
        $otherOption = $firstAttempt->attemptQuestions
            ->firstWhere('question_type', QuestionType::MultipleSelect)
            ->options
            ->first();
        $this->assertValidationException(fn () => $answers->save(
            $first['student'],
            $firstAttempt,
            $mc,
            $this->answer([$otherOption->id]),
        ));
    }

    public function test_objective_submission_scores_full_or_zero_and_grades_immediately(): void
    {
        $context = $this->makeAssessmentContext([
            QuestionType::MultipleChoice,
            QuestionType::TrueFalse,
            QuestionType::ShortAnswer,
        ]);
        $attempts = app(AssessmentAttemptService::class);
        $answers = app(AssessmentAnswerService::class);
        $attempt = $attempts->startAttempt($context['student'], $context['enrollment'], $context['assignment']);
        $attempt->load('attemptQuestions.options');

        foreach ($attempt->attemptQuestions as $question) {
            $payload = match ($question->question_type) {
                QuestionType::MultipleChoice => $this->answer([$question->options->firstWhere('is_correct', true)->id]),
                QuestionType::TrueFalse => [...$this->answer(), 'answer_boolean' => false],
                QuestionType::ShortAnswer => [...$this->answer(), 'answer_text' => ' Laravel '],
                default => $this->answer(),
            };
            $answers->save($context['student'], $attempt, $question, $payload);
        }

        $submitted = $attempts->submit($context['student'], $attempt);
        $this->assertSame(AssessmentAttemptStatus::Graded, $submitted->status);
        $this->assertSame('4.00', $submitted->earned_points);
        $this->assertSame('5.00', $submitted->max_points);
        $this->assertSame('80.00', $submitted->percentage);
        $this->assertNotNull($submitted->graded_at);
    }

    public function test_multiple_select_requires_an_exact_set_and_short_answer_honors_case_rules(): void
    {
        $multi = $this->makeAssessmentContext([QuestionType::MultipleSelect], ['max_attempts' => 3]);
        $attempts = app(AssessmentAttemptService::class);
        $answers = app(AssessmentAnswerService::class);
        $expectedScores = ['0.00', '0.00', '3.00'];

        foreach ($expectedScores as $index => $expectedScore) {
            $attempt = $attempts->startAttempt($multi['student'], $multi['enrollment'], $multi['assignment']);
            $question = $attempt->attemptQuestions()->with('options')->firstOrFail();
            $correct = $question->options->where('is_correct', true)->pluck('id')->all();
            $selected = match ($index) {
                0 => [$correct[0]],
                1 => [...$correct, $question->options->firstWhere('is_correct', false)->id],
                default => $correct,
            };
            $answers->save($multi['student'], $attempt, $question, $this->answer($selected));
            $this->assertSame($expectedScore, $attempts->submit($multi['student'], $attempt)->earned_points);
        }

        $short = $this->makeAssessmentContext([QuestionType::ShortAnswer], ['max_attempts' => 4]);
        foreach ([
            ' LARAVEL ' => '2.00',
            'exactcase' => '0.00',
            ' ExactCase ' => '2.00',
            'wrong' => '0.00',
        ] as $text => $expectedScore) {
            $attempt = $attempts->startAttempt($short['student'], $short['enrollment'], $short['assignment']);
            $question = $attempt->attemptQuestions()->firstOrFail();
            $answers->save($short['student'], $attempt, $question, [...$this->answer(), 'answer_text' => $text]);
            $this->assertSame($expectedScore, $attempts->submit($short['student'], $attempt)->earned_points);
        }
    }

    /** @param array<int, int> $selected */
    private function answer(array $selected = []): array
    {
        return ['answer_text' => null, 'answer_boolean' => null, 'selected_option_ids' => $selected];
    }

    private function assertValidationException(Closure $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a validation exception.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }
}
