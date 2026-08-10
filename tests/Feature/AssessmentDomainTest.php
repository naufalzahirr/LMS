<?php

namespace Tests\Feature;

use App\Enums\AcademicStatus;
use App\Enums\AssessmentStatus;
use App\Enums\ClassAssessmentStatus;
use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\Competency;
use App\Models\Course;
use App\Models\LearningClass;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Services\AssessmentService;
use App\Services\ClassAssessmentService;
use App\Services\QuestionService;
use Database\Seeders\AcademicSeeder;
use Database\Seeders\AssessmentSeeder;
use Database\Seeders\DeliverySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssessmentDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_factories_create_normalized_question_types_and_relationships(): void
    {
        $bank = QuestionBank::factory()->create();
        $competency = Competency::factory()->for($bank->course)->create();
        $multipleChoice = Question::factory()->for($bank)->for($competency)->multipleChoice()->create();
        $shortAnswer = Question::factory()->for($bank)->for($competency)->shortAnswer()->create();
        $assessment = Assessment::factory()->for($competency)->create();
        $composition = AssessmentQuestion::factory()->for($assessment)->for($multipleChoice)->create([
            'points' => '2.50',
            'sort_order' => 0,
        ]);

        $this->assertSame(QuestionType::MultipleChoice, $multipleChoice->question_type);
        $this->assertCount(2, $multipleChoice->options);
        $this->assertCount(1, $multipleChoice->options->where('is_correct', true));
        $this->assertCount(1, $shortAnswer->acceptedAnswers);
        $this->assertTrue($assessment->questions->first()->is($multipleChoice));
        $this->assertTrue($composition->assessment->is($assessment));
        $this->assertTrue($bank->questions->contains($shortAnswer));
        $this->assertTrue($competency->assessments->contains($assessment));
    }

    public function test_question_service_replaces_normalized_answer_key_when_type_changes(): void
    {
        [$bank, $competency] = $this->questionContext();
        $service = app(QuestionService::class);
        $question = $service->create($this->questionPayload($bank, $competency, QuestionType::MultipleChoice, [
            'options' => [
                ['option_text' => 'A', 'is_correct' => true, 'sort_order' => 0],
                ['option_text' => 'B', 'is_correct' => false, 'sort_order' => 1],
            ],
        ]));

        $question = $service->update($question, $this->questionPayload($bank, $competency, QuestionType::ShortAnswer, [
            'accepted_answers' => [
                ['answer_text' => 'Laravel', 'case_sensitive' => false],
                ['answer_text' => 'laravel', 'case_sensitive' => true],
            ],
        ]));
        $this->assertCount(0, $question->options);
        $this->assertCount(1, $question->acceptedAnswers);
        $this->assertNull($question->correct_boolean);

        $question = $service->update($question, $this->questionPayload($bank, $competency, QuestionType::TrueFalse, [
            'correct_boolean' => false,
        ]));
        $this->assertCount(0, $question->acceptedAnswers);
        $this->assertFalse($question->correct_boolean);

        $question = $service->update($question, $this->questionPayload($bank, $competency, QuestionType::Essay));
        $this->assertCount(0, $question->options);
        $this->assertCount(0, $question->acceptedAnswers);
        $this->assertNull($question->correct_boolean);
        $this->assertTrue($service->hasValidAnswerKey($question));
    }

    public function test_question_service_rejects_cross_course_competency_and_invalid_keys(): void
    {
        [$bank, $competency] = $this->questionContext();
        $service = app(QuestionService::class);

        try {
            $service->create($this->questionPayload($bank, Competency::factory()->create(), QuestionType::Essay));
            $this->fail('Cross-course question was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('competency_id', $exception->errors());
        }

        try {
            $service->create($this->questionPayload($bank, $competency, QuestionType::MultipleChoice, [
                'options' => [
                    ['option_text' => 'A', 'is_correct' => true, 'sort_order' => 0],
                    ['option_text' => 'B', 'is_correct' => true, 'sort_order' => 1],
                ],
            ]));
            $this->fail('Multiple-choice question with two correct options was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('options', $exception->errors());
        }
    }

    public function test_assessment_composition_and_publication_rules_are_enforced(): void
    {
        [$bank, $competency] = $this->questionContext();
        $question = Question::factory()->for($bank)->for($competency)->multipleChoice()->create();
        $otherQuestion = Question::factory()->essay()->create();
        $assessment = Assessment::factory()->for($competency)->create();
        $service = app(AssessmentService::class);

        try {
            $service->publish($assessment);
            $this->fail('Empty assessment was published.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assessment', $exception->errors());
        }

        try {
            $service->addQuestion($assessment, $otherQuestion);
            $this->fail('Cross-competency question was attached.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('question_id', $exception->errors());
        }

        $item = $service->addQuestion($assessment, $question, '2.50');
        $this->assertSame('2.50', $item->points);
        $this->assertSame(AssessmentStatus::Published, $service->publish($assessment)->status);
        $this->assertSame(AssessmentStatus::Archived, $service->archive($assessment)->status);

        $this->expectException(ValidationException::class);
        $service->updateQuestionPoints($assessment->refresh(), $item, '3.00');
    }

    public function test_only_published_same_course_assessment_can_be_assigned_once(): void
    {
        $course = Course::factory()->create();
        $class = LearningClass::factory()->for($course)->create();
        $competency = Competency::factory()->for($course)->create();
        $assessment = Assessment::factory()->for($competency)->published()->create();
        $service = app(ClassAssessmentService::class);
        $payload = [
            'assessment_id' => $assessment->id,
            'opens_at' => null,
            'closes_at' => null,
            'max_attempts' => 2,
            'status' => ClassAssessmentStatus::Active,
        ];

        $assignment = $service->assign($class, $assessment, $payload);
        $this->assertTrue($assignment->assessment->is($assessment));
        $this->assertSame(2, $assignment->max_attempts);

        try {
            $service->assign($class, $assessment, $payload);
            $this->fail('Duplicate class assessment was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assessment_id', $exception->errors());
        }

        $otherAssessment = Assessment::factory()->published()->create();
        $this->expectException(ValidationException::class);
        $service->assign($class, $otherAssessment, [...$payload, 'assessment_id' => $otherAssessment->id]);
    }

    public function test_assessment_seeder_is_idempotent(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            AcademicSeeder::class,
            DeliverySeeder::class,
            AssessmentSeeder::class,
            AssessmentSeeder::class,
        ]);

        $this->assertSame(1, QuestionBank::query()->where('code', 'HTML-CORE')->count());
        $this->assertSame(5, Question::query()->count());
        $this->assertSame(1, Assessment::query()->where('code', 'HTML-FORMATIVE-01')->count());
        $this->assertSame(5, AssessmentQuestion::query()->count());
        $this->assertDatabaseCount('learning_class_assessments', 1);
    }

    /** @return array{QuestionBank, Competency} */
    private function questionContext(): array
    {
        $course = Course::factory()->create();

        return [
            QuestionBank::factory()->for($course)->create(),
            Competency::factory()->for($course)->create(),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function questionPayload(
        QuestionBank $bank,
        Competency $competency,
        QuestionType $type,
        array $overrides = [],
    ): array {
        return array_merge([
            'question_bank_id' => $bank->id,
            'competency_id' => $competency->id,
            'question_type' => $type,
            'prompt' => 'A valid question prompt?',
            'explanation' => null,
            'default_points' => '1.00',
            'correct_boolean' => null,
            'status' => AcademicStatus::Active,
            'sort_order' => 0,
            'options' => [],
            'accepted_answers' => [],
        ], $overrides);
    }
}
