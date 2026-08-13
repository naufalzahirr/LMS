<?php

namespace Tests\Feature;

use App\Enums\AssessmentAttemptStatus;
use App\Enums\QuestionType;
use App\Services\AssessmentAttemptService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsAssessmentAttemptContexts;
use Tests\TestCase;

class AssessmentAnswerAutosaveTest extends TestCase
{
    use BuildsAssessmentAttemptContexts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_standalone_json_save_request_succeeds_with_no_content(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::ShortAnswer]);
        $attempt = app(AssessmentAttemptService::class)->startAttempt(
            $context['student'],
            $context['enrollment'],
            $context['assignment'],
        );
        $question = $attempt->attemptQuestions()->firstOrFail();

        $this->actingAs($context['student'])
            ->patch(
                route('student.assessment-answers.update', [$attempt, $question]),
                ['answer_text' => 'Paris', 'answer_boolean' => null, 'selected_option_ids' => []],
                ['Accept' => 'application/json'],
            )
            ->assertNoContent();

        $this->assertDatabaseHas('assessment_answers', [
            'assessment_attempt_question_id' => $question->id,
            'answer_text' => 'Paris',
        ]);
    }

    public function test_essay_and_short_answer_persist_exactly_through_reload_and_submission(): void
    {
        $context = $this->makeAssessmentContext([
            QuestionType::MultipleChoice,
            QuestionType::MultipleSelect,
            QuestionType::ShortAnswer,
            QuestionType::Essay,
        ]);
        $tutor = $this->userWithAssessmentRole('Tutor');
        $context['class']->tutors()->attach($tutor);
        $attempt = app(AssessmentAttemptService::class)->startAttempt(
            $context['student'],
            $context['enrollment'],
            $context['assignment'],
        )->load('attemptQuestions.options');
        $questions = $attempt->attemptQuestions->keyBy('question_type.value');
        $multipleChoice = $questions->get(QuestionType::MultipleChoice->value);
        $multipleSelect = $questions->get(QuestionType::MultipleSelect->value);
        $shortAnswer = $questions->get(QuestionType::ShortAnswer->value);
        $essay = $questions->get(QuestionType::Essay->value);

        $this->saveJson($context['student'], $attempt, $multipleChoice, [
            'selected_option_ids' => [$multipleChoice->options->firstOrFail()->id],
        ]);
        $this->saveJson($context['student'], $attempt, $multipleSelect, [
            'selected_option_ids' => $multipleSelect->options->take(2)->modelKeys(),
        ]);
        $this->saveJson($context['student'], $attempt, $shortAnswer, [
            'answer_text' => 'Laravel',
        ]);
        $this->saveJson($context['student'], $attempt, $essay, [
            'answer_text' => 'Essay persistence test 123',
        ]);

        $this->assertDatabaseHas('assessment_answers', [
            'assessment_attempt_question_id' => $essay->id,
            'answer_text' => 'Essay persistence test 123',
        ]);
        $this->actingAs($context['student'])
            ->get(route('student.assessment-attempts.show', $attempt))
            ->assertInertia(fn (Assert $page) => $page
                ->where('attempt.questions.2.answer.answer_text', 'Laravel')
                ->where('attempt.questions.3.answer.answer_text', 'Essay persistence test 123'));

        // This is the same endpoint used by the Essay blur flush.
        $this->saveJson($context['student'], $attempt, $essay, [
            'answer_text' => 'Essay second persistence test 456',
        ]);
        $this->actingAs($context['student'])
            ->get(route('student.assessment-attempts.show', $attempt))
            ->assertInertia(fn (Assert $page) => $page
                ->where('attempt.questions.3.answer.answer_text', 'Essay second persistence test 456'));

        // The frontend blocks finalization until this latest save receives its
        // successful response. Submit immediately after that response, as the
        // submit confirmation dialog does.
        $this->saveJson($context['student'], $attempt, $essay, [
            'answer_text' => 'Essay submitted response 789',
        ]);
        $this->actingAs($context['student'])
            ->post(route('student.assessment-attempts.submit', $attempt))
            ->assertRedirect(route('student.assessment-attempts.result', $attempt));

        $this->assertSame(AssessmentAttemptStatus::PendingGrading, $attempt->refresh()->status);
        $this->assertNotNull($attempt->submitted_at);
        $this->assertDatabaseHas('assessment_answers', [
            'assessment_attempt_id' => $attempt->id,
            'assessment_attempt_question_id' => $essay->id,
            'answer_text' => 'Essay submitted response 789',
        ]);
        $this->actingAs($tutor)
            ->get(route('tutor.class-assessment-attempts.edit', [
                $context['class'],
                $context['assignment'],
                $attempt,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('assessment-attempts/Grade')
                ->where('essays.0.answer_text', 'Essay submitted response 789')
                ->where('auto_graded.2.student_answer', 'Laravel'));
    }

    public function test_a_non_json_save_request_still_redirects_back_for_backward_compatibility(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::ShortAnswer]);
        $attempt = app(AssessmentAttemptService::class)->startAttempt(
            $context['student'],
            $context['enrollment'],
            $context['assignment'],
        );
        $question = $attempt->attemptQuestions()->firstOrFail();

        $this->actingAs($context['student'])
            ->patch(route('student.assessment-answers.update', [$attempt, $question]), [
                'answer_text' => 'Paris', 'answer_boolean' => null, 'selected_option_ids' => [],
            ])
            ->assertRedirect();
    }

    public function test_validation_failure_returns_json_errors_for_a_standalone_request(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::ShortAnswer]);
        $attempt = app(AssessmentAttemptService::class)->startAttempt(
            $context['student'],
            $context['enrollment'],
            $context['assignment'],
        );
        $question = $attempt->attemptQuestions()->firstOrFail();

        $this->actingAs($context['student'])
            ->patch(
                route('student.assessment-answers.update', [$attempt, $question]),
                ['answer_text' => 123, 'answer_boolean' => null, 'selected_option_ids' => []],
                ['Accept' => 'application/json'],
            )
            ->assertStatus(422)
            ->assertJsonValidationErrors('answer_text');
    }

    public function test_a_student_cannot_save_an_answer_on_another_students_attempt(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::ShortAnswer]);
        $attempt = app(AssessmentAttemptService::class)->startAttempt(
            $context['student'],
            $context['enrollment'],
            $context['assignment'],
        );
        $question = $attempt->attemptQuestions()->firstOrFail();
        $otherStudent = $this->userWithAssessmentRole('Student');

        $this->actingAs($otherStudent)
            ->patch(
                route('student.assessment-answers.update', [$attempt, $question]),
                ['answer_text' => 'Hijacked', 'answer_boolean' => null, 'selected_option_ids' => []],
                ['Accept' => 'application/json'],
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('assessment_answers', ['answer_text' => 'Hijacked']);
    }

    public function test_saving_a_question_that_does_not_belong_to_the_attempt_is_rejected(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::ShortAnswer]);
        $attempt = app(AssessmentAttemptService::class)->startAttempt(
            $context['student'],
            $context['enrollment'],
            $context['assignment'],
        );

        $otherContext = $this->makeAssessmentContext([QuestionType::ShortAnswer]);
        $otherAttempt = app(AssessmentAttemptService::class)->startAttempt(
            $otherContext['student'],
            $otherContext['enrollment'],
            $otherContext['assignment'],
        );
        $unrelatedQuestion = $otherAttempt->attemptQuestions()->firstOrFail();

        $this->actingAs($context['student'])
            ->patch(
                route('student.assessment-answers.update', [$attempt, $unrelatedQuestion]),
                ['answer_text' => 'Mismatch', 'answer_boolean' => null, 'selected_option_ids' => []],
                ['Accept' => 'application/json'],
            )
            ->assertStatus(422);

        $this->assertDatabaseMissing('assessment_answers', ['answer_text' => 'Mismatch']);
    }

    public function test_a_submitted_attempt_cannot_have_its_answers_modified(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice]);
        $attemptService = app(AssessmentAttemptService::class);
        $attempt = $attemptService->startAttempt($context['student'], $context['enrollment'], $context['assignment']);
        $question = $attempt->attemptQuestions()->with('options')->firstOrFail();
        $attemptService->submit($context['student'], $attempt);

        $this->actingAs($context['student'])
            ->patch(
                route('student.assessment-answers.update', [$attempt, $question]),
                ['answer_text' => null, 'answer_boolean' => null, 'selected_option_ids' => [$question->options->first()->id]],
                ['Accept' => 'application/json'],
            )
            ->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function saveJson($student, $attempt, $question, array $overrides): void
    {
        $this->actingAs($student)
            ->patch(
                route('student.assessment-answers.update', [$attempt, $question]),
                [
                    'answer_text' => null,
                    'answer_boolean' => null,
                    'selected_option_ids' => [],
                    ...$overrides,
                ],
                ['Accept' => 'application/json'],
            )
            ->assertNoContent();
    }
}
