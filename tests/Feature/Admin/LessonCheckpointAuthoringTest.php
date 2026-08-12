<?php

namespace Tests\Feature\Admin;

use App\Enums\AcademicStatus;
use App\Enums\LearningClassStatus;
use App\Enums\LessonCheckpointType;
use App\Models\Course;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\LessonCheckpoint;
use App\Models\Module;
use App\Models\User;
use App\Services\LessonContentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LessonCheckpointAuthoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_create_and_update_a_checkpoint_without_creating_a_duplicate(): void
    {
        $admin = $this->user('Admin');
        $lesson = Lesson::factory()->create();
        $payload = $this->multipleChoicePayload();

        $created = $this->actingAs($admin)
            ->postJson(route('admin.lesson-checkpoints.store', $lesson), $payload)
            ->assertCreated()
            ->assertJsonPath('checkpoint.type', 'multiple_choice')
            ->assertJsonPath('checkpoint.prompt', $payload['prompt'])
            ->assertJsonPath('checkpoint.correct_option_ids.0', $payload['correct_option_ids'][0]);
        $checkpoint = LessonCheckpoint::query()->findOrFail($created->json('checkpoint.id'));
        $updatedPayload = [
            ...$payload,
            'prompt' => 'Which command starts the Laravel local server?',
            'options' => array_reverse($payload['options']),
        ];

        $this->actingAs($admin)
            ->patchJson(
                route('admin.lesson-checkpoints.update', [$lesson, $checkpoint]),
                $updatedPayload,
            )
            ->assertOk()
            ->assertJsonPath('checkpoint.prompt', $updatedPayload['prompt'])
            ->assertJsonPath('checkpoint.options.1.id', $payload['options'][0]['id']);

        $this->assertDatabaseCount('lesson_checkpoints', 1);
        $this->assertSame(
            $payload['correct_option_ids'],
            $checkpoint->refresh()->answer_key['correct_option_ids'],
        );
    }

    public function test_checkpoint_authoring_validation_enforces_each_supported_type(): void
    {
        $admin = $this->user('Admin');
        $lesson = Lesson::factory()->create();
        $option = (string) Str::uuid();

        $this->actingAs($admin)->postJson(route('admin.lesson-checkpoints.store', $lesson), [
            'checkpoint_type' => 'multiple_choice',
            'prompt' => '',
            'options' => [['id' => $option, 'text' => 'Only option']],
            'correct_option_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors(['prompt', 'options', 'correct_option_ids']);

        $this->actingAs($admin)->postJson(route('admin.lesson-checkpoints.store', $lesson), [
            'checkpoint_type' => 'multiple_select',
            'prompt' => 'Select all.',
            'options' => [
                ['id' => (string) Str::uuid(), 'text' => 'One'],
                ['id' => (string) Str::uuid(), 'text' => ''],
            ],
            'correct_option_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors(['options.1.text', 'correct_option_ids']);

        $this->actingAs($admin)->postJson(route('admin.lesson-checkpoints.store', $lesson), [
            'checkpoint_type' => 'true_false',
            'prompt' => 'Laravel is a PHP framework.',
        ])->assertUnprocessable()->assertJsonValidationErrors(['correct_boolean']);

        $this->actingAs($admin)->postJson(route('admin.lesson-checkpoints.store', $lesson), [
            'checkpoint_type' => 'fill_blank',
            'prompt' => 'Laravel CLI is called ____.',
            'accepted_answers' => [' '],
        ])->assertUnprocessable()->assertJsonValidationErrors(['accepted_answers.0']);

        $this->assertDatabaseCount('lesson_checkpoints', 0);
    }

    public function test_answer_metadata_is_only_exposed_on_the_authorized_edit_page(): void
    {
        $admin = $this->user('Admin');
        $lesson = Lesson::factory()->create();
        $checkpoint = LessonCheckpoint::factory()->for($lesson)->create();
        $lesson->update(['content_document' => $this->checkpointDocument($checkpoint->id)]);
        $checkpointPath = 'lesson.content_document.content.1.attrs.checkpoint';

        $this->actingAs($admin)
            ->get(route('admin.lessons.show', $lesson))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where("{$checkpointPath}.interactive", false)
                ->missing("{$checkpointPath}.correct_option_ids")
                ->missing("{$checkpointPath}.update_url"));

        $this->actingAs($admin)
            ->get(route('admin.lessons.edit', $lesson))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where("{$checkpointPath}.correct_option_ids", $checkpoint->answer_key['correct_option_ids'])
                ->where("{$checkpointPath}.update_url", route('admin.lesson-checkpoints.update', [$lesson, $checkpoint]))
                ->missing("{$checkpointPath}.interactive"));
    }

    public function test_tutor_scope_follows_the_parent_lesson_course(): void
    {
        $tutor = $this->user('Tutor');
        $assignedCourse = Course::factory()->create();
        $assignedModule = $this->moduleFor($assignedCourse);
        $unassignedModule = Module::factory()->create();
        $learningClass = LearningClass::factory()->for($assignedCourse)->create([
            'status' => LearningClassStatus::Active,
        ]);
        $learningClass->tutors()->attach($tutor);
        $assignedLesson = Lesson::factory()->for($assignedModule)->create();
        $unassignedLesson = Lesson::factory()->for($unassignedModule)->create();

        $this->actingAs($tutor)
            ->postJson(route('admin.lesson-checkpoints.store', $assignedLesson), $this->multipleChoicePayload())
            ->assertCreated();
        $this->actingAs($tutor)
            ->postJson(route('admin.lesson-checkpoints.store', $unassignedLesson), $this->multipleChoicePayload())
            ->assertForbidden();

        $foreignCheckpoint = LessonCheckpoint::factory()->for($unassignedLesson)->create();
        $this->actingAs($tutor)
            ->patchJson(
                route('admin.lesson-checkpoints.update', [$unassignedLesson, $foreignCheckpoint]),
                $this->multipleChoicePayload(),
            )
            ->assertForbidden();
    }

    public function test_draft_preview_promotion_and_abandonment_follow_the_lesson_lifecycle(): void
    {
        $admin = $this->user('Admin');
        $module = Module::factory()->create();
        $draftResponse = $this->actingAs($admin)->postJson(route('admin.lesson-drafts.store'), [
            'module_id' => $module->id,
        ])->assertOk();
        $draft = Lesson::query()->findOrFail($draftResponse->json('draft.id'));
        $created = $this->actingAs($admin)
            ->postJson(route('admin.lesson-checkpoints.store', $draft), $this->multipleChoicePayload())
            ->assertCreated();
        $checkpointId = (int) $created->json('checkpoint.id');
        $document = $this->checkpointDocument($checkpointId);

        $this->actingAs($admin)
            ->postJson(route('admin.lessons.preview', $draft), ['content_document' => $document])
            ->assertOk()
            ->assertJsonPath('content_document.content.1.attrs.checkpoint.id', $checkpointId)
            ->assertJsonPath('content_document.content.1.attrs.checkpoint.interactive', false);
        $this->assertDatabaseCount('lesson_checkpoint_attempts', 0);
        $this->assertDatabaseCount('lesson_progress', 0);

        $this->actingAs($admin)->post(route('admin.lessons.store'), [
            'draft_id' => $draft->id,
            'module_id' => $module->id,
            'title' => 'Interactive Checkpoint Smoke Test',
            'slug' => 'interactive-checkpoint-smoke-test',
            'content_document' => json_encode($document, JSON_THROW_ON_ERROR),
            'duration_minutes' => 15,
            'sort_order' => 1,
            'status' => AcademicStatus::Active->value,
        ])->assertRedirect(route('admin.lessons.show', $draft));

        $this->assertFalse($draft->refresh()->is_authoring_draft);
        $this->assertDatabaseHas('lesson_checkpoints', [
            'id' => $checkpointId,
            'lesson_id' => $draft->id,
        ]);

        $withoutCheckpoint = [
            'type' => 'doc',
            'content' => [['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Checkpoint removed from the learning flow.'],
            ]]],
        ];
        $this->actingAs($admin)->put(route('admin.lessons.update', $draft), [
            'module_id' => $module->id,
            'title' => $draft->title,
            'slug' => $draft->slug,
            'content_document' => json_encode($withoutCheckpoint, JSON_THROW_ON_ERROR),
            'duration_minutes' => $draft->duration_minutes,
            'sort_order' => $draft->sort_order,
            'status' => AcademicStatus::Active->value,
        ])->assertRedirect(route('admin.lessons.show', $draft));
        $this->assertDatabaseMissing('lesson_checkpoints', ['id' => $checkpointId]);

        $abandonedResponse = $this->actingAs($admin)->postJson(route('admin.lesson-drafts.store'), [
            'module_id' => $module->id,
        ])->assertOk();
        $abandoned = Lesson::query()->findOrFail($abandonedResponse->json('draft.id'));
        $abandonedCheckpoint = $this->actingAs($admin)
            ->postJson(route('admin.lesson-checkpoints.store', $abandoned), $this->multipleChoicePayload())
            ->assertCreated()
            ->json('checkpoint.id');

        $this->actingAs($admin)
            ->delete(route('admin.lesson-drafts.destroy', $abandoned))
            ->assertNoContent();
        $this->assertDatabaseMissing('lesson_checkpoints', ['id' => $abandonedCheckpoint]);
    }

    public function test_rich_content_accepts_owned_checkpoint_and_rejects_malformed_or_cross_lesson_references(): void
    {
        $lesson = Lesson::factory()->create();
        $otherLesson = Lesson::factory()->create();
        $checkpoint = LessonCheckpoint::factory()->for($lesson)->create();
        $foreign = LessonCheckpoint::factory()->for($otherLesson)->create();
        $content = app(LessonContentService::class);

        $this->assertSame(
            $this->checkpointDocument($checkpoint->id),
            $content->normalize($lesson, $this->checkpointDocument($checkpoint->id)),
        );

        foreach ([
            ['type' => 'lessonCheckpoint'],
            ['type' => 'lessonCheckpoint', 'attrs' => ['checkpointId' => '1']],
            ['type' => 'lessonCheckpoint', 'attrs' => ['checkpointId' => $checkpoint->id, 'extra' => true]],
            ['type' => 'lessonCheckpoint', 'attrs' => ['checkpointId' => $foreign->id]],
        ] as $invalidNode) {
            try {
                $content->normalize($lesson, ['type' => 'doc', 'content' => [$invalidNode]]);
                $this->fail('Malformed or cross-lesson checkpoint content was accepted.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $legacy = ['type' => 'doc', 'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => 'Legacy content remains valid.']],
        ]]];
        $this->assertSame($legacy, $content->normalize($lesson, $legacy));
    }

    /** @return array<string, mixed> */
    private function multipleChoicePayload(): array
    {
        $correctId = (string) Str::uuid();

        return [
            'checkpoint_type' => LessonCheckpointType::MultipleChoice->value,
            'prompt' => 'Which command starts Laravel?',
            'explanation' => 'php artisan serve starts the local development server.',
            'options' => [
                ['id' => $correctId, 'text' => 'php artisan serve'],
                ['id' => (string) Str::uuid(), 'text' => 'php artisan migrate'],
            ],
            'correct_option_ids' => [$correctId],
        ];
    }

    /** @return array{type: string, content: array<int, mixed>} */
    private function checkpointDocument(int $checkpointId): array
    {
        return [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Before checkpoint']]],
                ['type' => 'lessonCheckpoint', 'attrs' => ['checkpointId' => $checkpointId]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'After checkpoint']]],
            ],
        ];
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function moduleFor(Course $course): Module
    {
        return Module::factory()->create([
            'competency_id' => $course->competencies()->firstOrCreate([
                'code' => 'CP'.Str::upper(Str::random(4)),
            ], [
                'name' => 'Checkpoint competency',
                'slug' => 'checkpoint-competency-'.Str::lower(Str::random(6)),
                'description' => null,
                'learning_objectives' => null,
                'sort_order' => 0,
                'status' => AcademicStatus::Active,
            ])->id,
        ]);
    }
}
