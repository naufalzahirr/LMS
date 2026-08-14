<?php

namespace Tests\Feature\Student;

use App\Enums\LessonCheckpointType;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\LessonCheckpoint;
use App\Models\User;
use App\Services\LessonCheckpointService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LessonCheckpointFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_checkpoint_authored_before_the_feedback_fields_existed_still_answers_with_defaults(): void
    {
        [$student, $learningClass, $lesson] = $this->studentContext();
        [$checkpoint, $correctId, $incorrectId] = $this->multipleChoice($lesson);

        // Exactly the shape a pre-migration row has: both feedback columns null.
        $this->assertNull($checkpoint->correct_feedback);
        $this->assertNull($checkpoint->incorrect_feedback);

        $this->submit($student, $learningClass, $lesson, $checkpoint, $incorrectId)
            ->assertOk()
            ->assertJsonPath('correct', false)
            ->assertJsonPath('feedback', LessonCheckpointService::DEFAULT_INCORRECT_FEEDBACK);

        $this->submit($student, $learningClass, $lesson, $checkpoint, $correctId)
            ->assertOk()
            ->assertJsonPath('correct', true)
            ->assertJsonPath('feedback', LessonCheckpointService::DEFAULT_CORRECT_FEEDBACK);
    }

    public function test_authored_feedback_is_selected_by_outcome_and_never_leaks_the_other_branch(): void
    {
        [$student, $learningClass, $lesson] = $this->studentContext();
        [$checkpoint, $correctId, $incorrectId] = $this->multipleChoice($lesson, [
            'correct_feedback' => 'Hebat! Jawabanmu benar.',
            'incorrect_feedback' => 'Belum tepat. Coba perhatikan gambarnya lagi.',
        ]);

        $wrong = $this->submit($student, $learningClass, $lesson, $checkpoint, $incorrectId)->assertOk();
        $wrong->assertJsonPath('feedback', 'Belum tepat. Coba perhatikan gambarnya lagi.');
        $this->assertStringNotContainsString('Hebat!', $wrong->getContent());

        $right = $this->submit($student, $learningClass, $lesson, $checkpoint, $correctId)->assertOk();
        $right->assertJsonPath('feedback', 'Hebat! Jawabanmu benar.');
        $this->assertStringNotContainsString('Coba perhatikan gambarnya lagi', $right->getContent());
    }

    public function test_one_authored_side_falls_back_to_the_default_on_the_other_side(): void
    {
        [$student, $learningClass, $lesson] = $this->studentContext();
        [$checkpoint, $correctId, $incorrectId] = $this->multipleChoice($lesson, [
            'correct_feedback' => 'Tepat sekali!',
            'incorrect_feedback' => null,
        ]);

        $this->submit($student, $learningClass, $lesson, $checkpoint, $correctId)
            ->assertOk()->assertJsonPath('feedback', 'Tepat sekali!');
        $this->submit($student, $learningClass, $lesson, $checkpoint, $incorrectId)
            ->assertOk()->assertJsonPath('feedback', LessonCheckpointService::DEFAULT_INCORRECT_FEEDBACK);
    }

    public function test_explanation_stays_independent_of_feedback_and_is_returned_for_both_outcomes(): void
    {
        [$student, $learningClass, $lesson] = $this->studentContext();
        [$checkpoint, $correctId, $incorrectId] = $this->multipleChoice($lesson, [
            'correct_feedback' => 'Benar sekali!',
            'incorrect_feedback' => 'Belum tepat, ya.',
            'explanation' => 'Ada lima apel pada gambar.',
        ]);

        $this->submit($student, $learningClass, $lesson, $checkpoint, $incorrectId)
            ->assertOk()
            ->assertJsonPath('feedback', 'Belum tepat, ya.')
            ->assertJsonPath('explanation', 'Ada lima apel pada gambar.');

        $this->submit($student, $learningClass, $lesson, $checkpoint, $correctId)
            ->assertOk()
            ->assertJsonPath('feedback', 'Benar sekali!')
            ->assertJsonPath('explanation', 'Ada lima apel pada gambar.');
    }

    public function test_retry_and_sticky_mastery_are_unchanged_by_the_feedback_fields(): void
    {
        [$student, $learningClass, $lesson, $enrollment] = $this->studentContext();
        [$checkpoint, $correctId, $incorrectId] = $this->multipleChoice($lesson, [
            'correct_feedback' => 'Benar sekali!',
            'incorrect_feedback' => 'Belum tepat, ya.',
        ]);

        $this->submit($student, $learningClass, $lesson, $checkpoint, $incorrectId)
            ->assertOk()->assertJsonPath('mastered', false)->assertJsonPath('attempt_count', 1);
        $this->submit($student, $learningClass, $lesson, $checkpoint, $correctId)
            ->assertOk()->assertJsonPath('mastered', true)->assertJsonPath('attempt_count', 2);

        // Once correct, a later wrong retry keeps mastery but must still show
        // the incorrect feedback for that specific attempt.
        $this->submit($student, $learningClass, $lesson, $checkpoint, $incorrectId)
            ->assertOk()
            ->assertJsonPath('correct', false)
            ->assertJsonPath('mastered', true)
            ->assertJsonPath('attempt_count', 3)
            ->assertJsonPath('feedback', 'Belum tepat, ya.');

        $this->assertDatabaseCount('lesson_checkpoint_attempts', 3);
        $this->assertDatabaseHas('lesson_checkpoint_attempts', [
            'lesson_checkpoint_id' => $checkpoint->id,
            'enrollment_id' => $enrollment->id,
            'attempt_number' => 3,
            'is_correct' => false,
        ]);
    }

    public function test_the_student_lesson_payload_never_carries_either_feedback_string(): void
    {
        [$student, $learningClass, $lesson] = $this->studentContext();
        [$checkpoint] = $this->multipleChoice($lesson, [
            'correct_feedback' => 'Rahasia benar.',
            'incorrect_feedback' => 'Rahasia salah.',
        ]);

        $response = $this->actingAs($student)
            ->get(route('student.lessons.show', [$learningClass, $lesson]))
            ->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('lesson.content_document.content.0.attrs.checkpoint.correct_feedback')
            ->missing('lesson.content_document.content.0.attrs.checkpoint.incorrect_feedback'));
        $this->assertStringNotContainsString('Rahasia benar.', $response->getContent());
        $this->assertStringNotContainsString('Rahasia salah.', $response->getContent());
    }

    /** @return array{User, LearningClass, Lesson, Enrollment} */
    private function studentContext(): array
    {
        $student = User::factory()->create();
        $student->assignRole('Student');
        $lesson = Lesson::factory()->create();
        $learningClass = LearningClass::factory()->create([
            'course_id' => $lesson->module->competency->course_id,
        ]);
        $enrollment = Enrollment::factory()->for($learningClass)->create([
            'student_id' => $student->id,
        ]);

        return [$student, $learningClass, $lesson, $enrollment];
    }

    /**
     * @param  array<string, string|null>  $attributes
     * @return array{LessonCheckpoint, string, string}
     */
    private function multipleChoice(Lesson $lesson, array $attributes = []): array
    {
        $correctId = (string) Str::uuid();
        $incorrectId = (string) Str::uuid();
        $checkpoint = LessonCheckpoint::factory()->for($lesson)->create([
            'checkpoint_type' => LessonCheckpointType::MultipleChoice,
            'prompt' => 'Ada berapa apel pada gambar?',
            'explanation' => null,
            'correct_feedback' => null,
            'incorrect_feedback' => null,
            'configuration' => ['options' => [
                ['id' => $correctId, 'text' => '5'],
                ['id' => $incorrectId, 'text' => '4'],
            ]],
            'answer_key' => ['correct_option_ids' => [$correctId]],
            ...$attributes,
        ]);
        $lesson->forceFill(['content_document' => [
            'type' => 'doc',
            'content' => [[
                'type' => 'lessonCheckpoint',
                'attrs' => ['checkpointId' => $checkpoint->id],
            ]],
        ]])->save();

        return [$checkpoint, $correctId, $incorrectId];
    }

    private function submit(
        User $student,
        LearningClass $learningClass,
        Lesson $lesson,
        LessonCheckpoint $checkpoint,
        string $answer,
    ): TestResponse {
        return $this->actingAs($student)->postJson(
            route('student.lesson-checkpoints.store', [$learningClass, $lesson, $checkpoint]),
            ['answer' => $answer],
        );
    }
}
