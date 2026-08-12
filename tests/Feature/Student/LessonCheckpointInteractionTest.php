<?php

namespace Tests\Feature\Student;

use App\Enums\LessonCheckpointType;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\LessonCheckpoint;
use App\Models\LessonCheckpointAttempt;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LessonCheckpointInteractionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_student_payload_contains_public_checkpoint_state_without_any_answer_key(): void
    {
        [$student, $learningClass, $lesson] = $this->studentContext();
        [$checkpoint, $correctId] = $this->multipleChoice($lesson);
        $lesson->forceFill(['content_document' => $this->document([$checkpoint])])->save();

        $this->actingAs($student)
            ->get(route('student.lessons.show', [$learningClass, $lesson]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lesson.content_document.content.0.attrs.checkpoint.id', $checkpoint->id)
                ->where('lesson.content_document.content.0.attrs.checkpoint.prompt', $checkpoint->prompt)
                ->where('lesson.content_document.content.0.attrs.checkpoint.mastered', false)
                ->where('lesson.content_document.content.0.attrs.checkpoint.attempt_count', 0)
                ->where('lesson.content_document.content.0.attrs.checkpoint.explanation', null)
                ->missing('lesson.content_document.content.0.attrs.checkpoint.answer_key')
                ->missing('lesson.content_document.content.0.attrs.checkpoint.correct_option_ids')
                ->missing('lesson.content_document.content.0.attrs.checkpoint.accepted_answers'));

        $response = $this->actingAs($student)
            ->get(route('student.lessons.show', [$learningClass, $lesson]));
        $this->assertStringNotContainsString($correctId.'\":true', $response->getContent());
        $this->assertStringNotContainsString('correct_option_ids', $response->getContent());
    }

    public function test_multiple_choice_true_false_and_fill_blank_are_evaluated_server_side(): void
    {
        [$student, $learningClass, $lesson, $enrollment] = $this->studentContext();
        [$multipleChoice, $correctId, $incorrectId] = $this->multipleChoice($lesson);
        $trueFalse = LessonCheckpoint::factory()->for($lesson)->create([
            'checkpoint_type' => LessonCheckpointType::TrueFalse,
            'prompt' => 'Laravel is a PHP framework.',
            'configuration' => [],
            'answer_key' => ['correct_boolean' => true],
        ]);
        $fillBlank = LessonCheckpoint::factory()->for($lesson)->create([
            'checkpoint_type' => LessonCheckpointType::FillBlank,
            'prompt' => "Laravel's CLI tool is called ____.",
            'configuration' => [],
            'answer_key' => ['accepted_answers' => ['artisan']],
        ]);
        $lesson->forceFill(['content_document' => $this->document([
            $multipleChoice, $trueFalse, $fillBlank,
        ])])->save();

        $this->submit($student, $learningClass, $lesson, $multipleChoice, $incorrectId)
            ->assertOk()->assertJsonPath('correct', false)->assertJsonPath('mastered', false);
        $this->submit($student, $learningClass, $lesson, $multipleChoice, $correctId)
            ->assertOk()->assertJsonPath('correct', true)->assertJsonPath('mastered', true)
            ->assertJsonPath('attempt_count', 2);
        $this->submit($student, $learningClass, $lesson, $multipleChoice, $incorrectId)
            ->assertOk()->assertJsonPath('correct', false)->assertJsonPath('mastered', true);

        $this->submit($student, $learningClass, $lesson, $trueFalse, false)
            ->assertOk()->assertJsonPath('correct', false);
        $this->submit($student, $learningClass, $lesson, $trueFalse, true)
            ->assertOk()->assertJsonPath('correct', true);

        foreach (['artisan', ' Artisan ', 'ARTISAN'] as $answer) {
            $this->submit($student, $learningClass, $lesson, $fillBlank, $answer)
                ->assertOk()->assertJsonPath('correct', true);
        }
        $this->submit($student, $learningClass, $lesson, $fillBlank, 'composer')
            ->assertOk()->assertJsonPath('correct', false);

        $this->assertDatabaseCount('lesson_checkpoint_attempts', 9);
        $this->assertSame(
            [1, 2, 3],
            LessonCheckpointAttempt::query()
                ->where('lesson_checkpoint_id', $multipleChoice->id)
                ->where('enrollment_id', $enrollment->id)
                ->orderBy('attempt_number')
                ->pluck('attempt_number')
                ->all(),
        );
    }

    public function test_multiple_select_requires_the_exact_set_regardless_of_submission_order(): void
    {
        [$student, $learningClass, $lesson] = $this->studentContext();
        $correctA = (string) Str::uuid();
        $correctB = (string) Str::uuid();
        $incorrect = (string) Str::uuid();
        $checkpoint = LessonCheckpoint::factory()->for($lesson)->create([
            'checkpoint_type' => LessonCheckpointType::MultipleSelect,
            'prompt' => 'Which are Laravel components?',
            'configuration' => ['options' => [
                ['id' => $correctA, 'text' => 'Routing'],
                ['id' => $correctB, 'text' => 'Blade'],
                ['id' => $incorrect, 'text' => 'Photoshop'],
            ]],
            'answer_key' => ['correct_option_ids' => [$correctA, $correctB]],
        ]);
        $lesson->forceFill(['content_document' => $this->document([$checkpoint])])->save();

        $this->submit($student, $learningClass, $lesson, $checkpoint, [$correctA])
            ->assertOk()->assertJsonPath('correct', false);
        $this->submit($student, $learningClass, $lesson, $checkpoint, [$correctA, $correctB, $incorrect])
            ->assertOk()->assertJsonPath('correct', false);
        $this->submit($student, $learningClass, $lesson, $checkpoint, [$correctB, $correctA])
            ->assertOk()->assertJsonPath('correct', true);
    }

    public function test_mastered_state_and_explanation_persist_when_the_lesson_is_reopened(): void
    {
        [$student, $learningClass, $lesson] = $this->studentContext();
        [$checkpoint, $correctId] = $this->multipleChoice($lesson);
        $lesson->forceFill(['content_document' => $this->document([$checkpoint])])->save();

        $this->submit($student, $learningClass, $lesson, $checkpoint, $correctId)
            ->assertOk()->assertJsonPath('mastered', true);

        $this->actingAs($student)
            ->get(route('student.lessons.show', [$learningClass, $lesson]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('lesson.content_document.content.0.attrs.checkpoint.mastered', true)
                ->where('lesson.content_document.content.0.attrs.checkpoint.attempt_count', 1)
                ->where('lesson.content_document.content.0.attrs.checkpoint.explanation', $checkpoint->explanation)
                ->where('lesson.checkpoint_summary.mastered', 1)
                ->where('lesson.checkpoint_summary.total', 1));
    }

    public function test_submission_security_rejects_inaccessible_or_cross_lesson_checkpoints_and_never_accepts_a_target_student(): void
    {
        [$student, $learningClass, $lesson, $enrollment] = $this->studentContext();
        [$checkpoint, $correctId] = $this->multipleChoice($lesson);
        $lesson->forceFill(['content_document' => $this->document([$checkpoint])])->save();
        $otherStudent = $this->user('Student');

        $this->actingAs($otherStudent)
            ->postJson(route('student.lesson-checkpoints.store', [$learningClass, $lesson, $checkpoint]), [
                'answer' => $correctId,
            ])->assertForbidden();

        $otherLesson = Lesson::factory()->for($lesson->module)->create();
        $foreign = LessonCheckpoint::factory()->for($otherLesson)->create();
        $this->actingAs($student)
            ->postJson(route('student.lesson-checkpoints.store', [$learningClass, $lesson, $foreign]), [
                'answer' => $foreign->configuration['options'][0]['id'],
            ])->assertNotFound();

        $this->actingAs($student)
            ->postJson(route('admin.lesson-checkpoints.store', $lesson), [
                'checkpoint_type' => 'true_false',
                'prompt' => 'Blocked authoring request.',
                'correct_boolean' => true,
            ])->assertForbidden();

        $this->actingAs($student)
            ->postJson(route('student.lesson-checkpoints.store', [$learningClass, $lesson, $checkpoint]), [
                'answer' => $correctId,
                'student_id' => $otherStudent->id,
                'enrollment_id' => 999999,
                'is_correct' => false,
            ])->assertOk()->assertJsonPath('correct', true);
        $this->assertDatabaseHas('lesson_checkpoint_attempts', [
            'lesson_checkpoint_id' => $checkpoint->id,
            'enrollment_id' => $enrollment->id,
            'is_correct' => true,
        ]);
    }

    /** @return array{User, LearningClass, Lesson, Enrollment} */
    private function studentContext(): array
    {
        $student = $this->user('Student');
        $lesson = Lesson::factory()->create();
        $learningClass = LearningClass::factory()->create([
            'course_id' => $lesson->module->competency->course_id,
        ]);
        $enrollment = Enrollment::factory()->for($learningClass)->create([
            'student_id' => $student->id,
        ]);

        return [$student, $learningClass, $lesson, $enrollment];
    }

    /** @return array{LessonCheckpoint, string, string} */
    private function multipleChoice(Lesson $lesson): array
    {
        $correctId = (string) Str::uuid();
        $incorrectId = (string) Str::uuid();
        $checkpoint = LessonCheckpoint::factory()->for($lesson)->create([
            'checkpoint_type' => LessonCheckpointType::MultipleChoice,
            'prompt' => 'Which command starts Laravel?',
            'explanation' => 'php artisan serve starts the local development server.',
            'configuration' => ['options' => [
                ['id' => $correctId, 'text' => 'php artisan serve'],
                ['id' => $incorrectId, 'text' => 'php artisan migrate'],
            ]],
            'answer_key' => ['correct_option_ids' => [$correctId]],
        ]);

        return [$checkpoint, $correctId, $incorrectId];
    }

    /** @param array<int, LessonCheckpoint> $checkpoints @return array{type: string, content: array<int, mixed>} */
    private function document(array $checkpoints): array
    {
        return [
            'type' => 'doc',
            'content' => array_map(
                fn (LessonCheckpoint $checkpoint): array => [
                    'type' => 'lessonCheckpoint',
                    'attrs' => ['checkpointId' => $checkpoint->id],
                ],
                $checkpoints,
            ),
        ];
    }

    private function submit(
        User $student,
        LearningClass $learningClass,
        Lesson $lesson,
        LessonCheckpoint $checkpoint,
        string|bool|array $answer,
    ): TestResponse {
        return $this->actingAs($student)->postJson(
            route('student.lesson-checkpoints.store', [$learningClass, $lesson, $checkpoint]),
            ['answer' => $answer],
        );
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
