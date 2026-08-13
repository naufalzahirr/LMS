<?php

namespace Tests\Feature;

use App\Enums\QuestionType;
use App\Services\AssessmentAttemptService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
