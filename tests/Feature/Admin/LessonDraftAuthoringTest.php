<?php

namespace Tests\Feature\Admin;

use App\Enums\AcademicStatus;
use App\Enums\LearningClassStatus;
use App\Enums\LessonAssetType;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\LessonAsset;
use App\Models\Module;
use App\Models\User;
use App\Services\LearningProgressQueryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LessonDraftAuthoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_admin_can_upload_multimedia_during_initial_authoring_and_finalize_the_same_draft(): void
    {
        $admin = $this->user('Admin');
        $module = Module::factory()->create();
        $draftResponse = $this->actingAs($admin)->postJson(route('admin.lesson-drafts.store'), [
            'module_id' => $module->id,
        ])->assertOk();
        $draft = Lesson::query()->findOrFail($draftResponse->json('draft.id'));

        $this->assertTrue($draft->is_authoring_draft);
        $this->assertSame($admin->id, $draft->draft_owner_id);
        $this->assertSame(AcademicStatus::Inactive, $draft->status);
        $this->assertNotNull($draft->draft_expires_at);

        $this->actingAs($admin)
            ->get(route('admin.lessons.index'))
            ->assertInertia(fn (Assert $page) => $page->where('lessons.total', 0));

        $imageResponse = $this->actingAs($admin)->post(
            route('admin.lesson-assets.store', $draft),
            [
                'asset_type' => 'image',
                'file' => UploadedFile::fake()->image('diagram.png', 800, 600),
                'alt_text' => 'A diagram explaining the lesson structure',
                'caption' => 'Lesson structure',
            ],
        )->assertCreated();
        $pdfResponse = $this->actingAs($admin)->post(
            route('admin.lesson-assets.store', $draft),
            [
                'asset_type' => 'document',
                'file' => UploadedFile::fake()->create('reference.pdf', 500, 'application/pdf'),
                'caption' => 'Printable reference',
            ],
        )->assertCreated();
        $imageId = $imageResponse->json('asset.id');
        $pdfId = $pdfResponse->json('asset.id');
        $document = [
            'type' => 'doc',
            'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [
                    ['type' => 'text', 'text' => 'Accessible multimedia lesson'],
                ]],
                ['type' => 'lessonImage', 'attrs' => [
                    'lessonAssetId' => $imageId,
                    'altText' => 'A diagram explaining the lesson structure',
                    'caption' => 'Lesson structure',
                    'alignment' => 'center',
                    'size' => 'large',
                    'decorative' => false,
                ]],
                ['type' => 'lessonFile', 'attrs' => [
                    'lessonAssetId' => $pdfId,
                    'title' => 'Printable lesson reference',
                    'caption' => 'Printable reference',
                ]],
            ],
        ];

        $this->actingAs($admin)->post(route('admin.lessons.store'), [
            'draft_id' => $draft->id,
            'module_id' => $module->id,
            'title' => 'Accessible multimedia lesson',
            'slug' => 'accessible-multimedia-lesson',
            'content_document' => json_encode($document, JSON_THROW_ON_ERROR),
            'duration_minutes' => 15,
            'sort_order' => 1,
            'status' => 'active',
        ])->assertRedirect(route('admin.lessons.show', $draft));

        $draft->refresh();
        $this->assertFalse($draft->is_authoring_draft);
        $this->assertNull($draft->draft_owner_id);
        $this->assertNull($draft->draft_expires_at);
        $this->assertSame(AcademicStatus::Active, $draft->status);
        $this->assertSame('Accessible multimedia lesson', $draft->title);
        $this->assertDatabaseCount('lessons', 1);
        $this->assertDatabaseCount('lesson_assets', 2);
    }

    public function test_draft_is_absent_from_student_navigation_and_direct_lesson_or_asset_access_is_rejected(): void
    {
        [$student, $learningClass, $module] = $this->studentContext();
        $admin = $this->user('Admin');
        $activeLesson = Lesson::factory()->for($module)->create();
        $draft = $this->createDraft($admin, $module);
        $asset = $this->storedAsset($draft, 'draft.png');

        app(LearningProgressQueryService::class)->loadActiveHierarchy($learningClass);
        $loadedLessons = $learningClass->course->competencies
            ->flatMap->modules
            ->flatMap->lessons;

        $this->assertTrue($loadedLessons->contains('id', $activeLesson->id));
        $this->assertFalse($loadedLessons->contains('id', $draft->id));

        $this->actingAs($student)
            ->get(route('student.lessons.show', [$learningClass, $draft]))
            ->assertForbidden();
        $this->actingAs($student)
            ->get(route('student.lesson-assets.file', [$learningClass, $draft, $asset]))
            ->assertForbidden();
    }

    public function test_tutor_can_create_and_move_only_their_own_draft_inside_assigned_courses(): void
    {
        $tutor = $this->user('Tutor');
        $otherTutor = $this->user('Tutor');
        $assignedCourse = Course::factory()->create();
        $assignedModule = $this->moduleFor($assignedCourse);
        $secondAssignedModule = $this->moduleFor($assignedCourse);
        $class = LearningClass::factory()->for($assignedCourse)->create([
            'status' => LearningClassStatus::Active,
        ]);
        $class->tutors()->attach([$tutor->id, $otherTutor->id]);
        $unassignedModule = Module::factory()->create();

        $response = $this->actingAs($tutor)->postJson(route('admin.lesson-drafts.store'), [
            'module_id' => $assignedModule->id,
        ])->assertOk();
        $draft = Lesson::query()->findOrFail($response->json('draft.id'));

        $this->actingAs($tutor)->postJson(route('admin.lesson-drafts.store'), [
            'module_id' => $secondAssignedModule->id,
            'draft_id' => $draft->id,
        ])->assertOk();
        $this->assertSame($secondAssignedModule->id, $draft->refresh()->module_id);

        $this->actingAs($tutor)->postJson(route('admin.lesson-drafts.store'), [
            'module_id' => $unassignedModule->id,
            'draft_id' => $draft->id,
        ])->assertForbidden();
        $this->actingAs($otherTutor)->postJson(route('admin.lesson-drafts.store'), [
            'module_id' => $assignedModule->id,
            'draft_id' => $draft->id,
        ])->assertForbidden();
        $this->actingAs($otherTutor)->post(route('admin.lesson-assets.store', $draft), [
            'asset_type' => 'image',
            'file' => UploadedFile::fake()->image('cross-tutor.png'),
            'alt_text' => 'Blocked cross-Tutor upload',
        ])->assertForbidden();
    }

    public function test_author_preview_uses_safe_renderer_payload_without_writing_student_progress(): void
    {
        $admin = $this->user('Admin');
        $student = $this->user('Student');
        $draft = $this->createDraft($admin, Module::factory()->create());
        $document = [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Preview only']]],
                ['type' => 'externalVideo', 'attrs' => [
                    'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    'title' => 'Trusted video',
                    'caption' => 'Preview caption',
                ]],
            ],
        ];

        $this->actingAs($admin)
            ->postJson(route('admin.lessons.preview', $draft), ['content_document' => $document])
            ->assertOk()
            ->assertJsonPath(
                'content_document.content.1.attrs.embedUrl',
                'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            );
        $this->assertDatabaseCount('lesson_progress', 0);
        $this->assertSame(
            [['type' => 'paragraph']],
            $draft->refresh()->content_document['content'],
        );

        $this->actingAs($student)
            ->postJson(route('admin.lessons.preview', $draft), ['content_document' => $document])
            ->assertForbidden();
        $this->assertDatabaseCount('lesson_progress', 0);
    }

    public function test_cleanup_removes_expired_draft_and_stale_unused_assets_but_keeps_referenced_assets(): void
    {
        $admin = $this->user('Admin');
        $draft = $this->createDraft($admin, Module::factory()->create());
        $draftAsset = $this->storedAsset($draft, 'expired.png');
        $draft->forceFill(['draft_expires_at' => now()->subMinute()])->save();

        $lesson = Lesson::factory()->create();
        $used = $this->storedAsset($lesson, 'used.png');
        $unused = $this->storedAsset($lesson, 'unused.png');
        $lesson->update(['content_document' => [
            'type' => 'doc',
            'content' => [['type' => 'lessonImage', 'attrs' => [
                'lessonAssetId' => $used->id,
                'altText' => 'Referenced diagram',
                'caption' => null,
                'alignment' => 'center',
                'size' => 'large',
                'decorative' => false,
            ]]],
        ]]);
        $lesson->forceFill(['updated_at' => now()->subDays(2)])->save();
        $used->forceFill(['created_at' => now()->subDays(2)])->save();
        $unused->forceFill(['created_at' => now()->subDays(2)])->save();

        $recentlyEditedLesson = Lesson::factory()->create();
        $undoSafeAsset = $this->storedAsset($recentlyEditedLesson, 'recently-removed.png');
        $undoSafeAsset->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->artisan('lesson-authoring:cleanup', ['--asset-hours' => 24])->assertSuccessful();

        $this->assertDatabaseMissing('lessons', ['id' => $draft->id]);
        $this->assertDatabaseMissing('lesson_assets', ['id' => $draftAsset->id]);
        $this->assertDatabaseMissing('lesson_assets', ['id' => $unused->id]);
        $this->assertDatabaseHas('lesson_assets', ['id' => $used->id]);
        $this->assertDatabaseHas('lesson_assets', ['id' => $undoSafeAsset->id]);
        Storage::disk('local')->assertMissing($draftAsset->file_path);
        Storage::disk('local')->assertMissing($unused->file_path);
        Storage::disk('local')->assertExists($used->file_path);
        Storage::disk('local')->assertExists($undoSafeAsset->file_path);
    }

    public function test_discarding_draft_and_deleting_lesson_remove_private_assets(): void
    {
        $admin = $this->user('Admin');
        $draft = $this->createDraft($admin, Module::factory()->create());
        $draftAsset = $this->storedAsset($draft, 'draft-resource.png');

        $this->actingAs($admin)
            ->delete(route('admin.lesson-drafts.destroy', $draft))
            ->assertNoContent();
        $this->assertDatabaseMissing('lessons', ['id' => $draft->id]);
        Storage::disk('local')->assertMissing($draftAsset->file_path);

        $lesson = Lesson::factory()->create();
        $lessonAsset = $this->storedAsset($lesson, 'published-resource.png');
        $this->actingAs($admin)
            ->delete(route('admin.lessons.destroy', $lesson))
            ->assertRedirect(route('admin.lessons.index'));
        $this->assertSoftDeleted($lesson);
        $this->assertDatabaseMissing('lesson_assets', ['id' => $lessonAsset->id]);
        Storage::disk('local')->assertMissing($lessonAsset->file_path);
    }

    private function createDraft(User $author, Module $module): Lesson
    {
        $response = $this->actingAs($author)->postJson(route('admin.lesson-drafts.store'), [
            'module_id' => $module->id,
        ])->assertOk();

        return Lesson::query()->findOrFail($response->json('draft.id'));
    }

    /** @return array{User, LearningClass, Module} */
    private function studentContext(): array
    {
        $student = $this->user('Student');
        $course = Course::factory()->create();
        $module = $this->moduleFor($course);
        $learningClass = LearningClass::factory()->for($course)->create();
        Enrollment::factory()->for($learningClass)->create(['student_id' => $student->id]);

        return [$student, $learningClass, $module];
    }

    private function moduleFor(Course $course): Module
    {
        $competency = Competency::factory()->for($course)->create();

        return Module::factory()->for($competency)->create();
    }

    private function storedAsset(Lesson $lesson, string $name): LessonAsset
    {
        $path = "lesson-assets/{$lesson->id}/{$name}";
        Storage::disk('local')->put($path, 'private image bytes');

        return LessonAsset::query()->create([
            'lesson_id' => $lesson->id,
            'asset_type' => LessonAssetType::Image,
            'original_name' => $name,
            'file_path' => $path,
            'mime_type' => 'image/png',
            'file_size' => 19,
            'alt_text' => 'Accessible image',
        ]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
