<?php

namespace Tests\Feature\Admin;

use App\Enums\AcademicStatus;
use App\Enums\LessonType;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LessonManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_admin_can_view_lessons_and_preview_content(): void
    {
        $admin = $this->userWithRole('Admin');
        $lesson = Lesson::factory()->create(['title' => 'Readable HTML']);

        $this->actingAs($admin)
            ->get(route('admin.lessons.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/lessons/Index')
                ->has('lessons.data', 1)
                ->where('lessons.data.0.id', $lesson->id)
                ->where('canManage', true));

        $this->actingAs($admin)
            ->get(route('admin.lessons.show', $lesson))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/lessons/Show')
                ->where('lesson.title', 'Readable HTML'));
    }

    public function test_tutor_can_view_lessons_read_only(): void
    {
        $tutor = $this->userWithRole('Tutor');
        $lesson = Lesson::factory()->create();

        $this->actingAs($tutor)
            ->get(route('admin.lessons.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

        $this->actingAs($tutor)
            ->get(route('admin.lessons.show', $lesson))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

        $this->actingAs($tutor)
            ->post(route('admin.lessons.store'), $this->textPayload())
            ->assertForbidden();

        $this->actingAs($tutor)
            ->delete(route('admin.lessons.destroy', $lesson))
            ->assertForbidden();
    }

    public function test_student_and_parent_cannot_access_lesson_management(): void
    {
        foreach (['Student', 'Parent'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('admin.lessons.index'))
                ->assertForbidden();
        }
    }

    public function test_admin_can_create_text_lesson(): void
    {
        $admin = $this->userWithRole('Admin');
        $module = Module::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.lessons.store'), $this->textPayload([
                'module_id' => $module->id,
                'title' => 'Introduction to HTML',
                'slug' => '',
                'content' => 'HTML provides semantic document structure.',
            ]))
            ->assertRedirect();

        $lesson = Lesson::query()->where('slug', 'introduction-to-html')->firstOrFail();

        $this->assertSame(LessonType::Text, $lesson->lesson_type);
        $this->assertSame('HTML provides semantic document structure.', $lesson->content);
        $this->assertTrue($lesson->module->is($module));
    }

    public function test_admin_can_create_video_lesson(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->post(route('admin.lessons.store'), $this->textPayload([
                'lesson_type' => LessonType::Video->value,
                'content' => 'Optional video notes.',
                'external_url' => 'https://www.youtube.com/watch?v=example',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('lessons', [
            'lesson_type' => LessonType::Video->value,
            'external_url' => 'https://www.youtube.com/watch?v=example',
        ]);
    }

    public function test_admin_can_create_link_lesson(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->post(route('admin.lessons.store'), $this->textPayload([
                'lesson_type' => LessonType::Link->value,
                'content' => null,
                'external_url' => 'https://developer.mozilla.org/en-US/docs/Web/HTML',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('lessons', [
            'lesson_type' => LessonType::Link->value,
            'external_url' => 'https://developer.mozilla.org/en-US/docs/Web/HTML',
        ]);
    }

    public function test_text_lessons_require_content_and_uploaded_types_require_files(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->post(route('admin.lessons.store'), $this->textPayload(['content' => null]))
            ->assertSessionHasErrors('content');

        foreach ([LessonType::Document, LessonType::Image] as $lessonType) {
            $this->actingAs($admin)
                ->post(route('admin.lessons.store'), $this->textPayload([
                    'lesson_type' => $lessonType->value,
                    'content' => 'Optional notes.',
                ]))
                ->assertSessionHasErrors('file');
        }
    }

    public function test_admin_can_upload_pdf_document_lesson_to_private_storage(): void
    {
        $admin = $this->userWithRole('Admin');
        $file = UploadedFile::fake()->create('html-guide.pdf', 500, 'application/pdf');

        $this->actingAs($admin)
            ->post(route('admin.lessons.store'), $this->textPayload([
                'lesson_type' => LessonType::Document->value,
                'content' => 'Reference document.',
                'file' => $file,
            ]))
            ->assertRedirect();

        $lesson = Lesson::query()->where('lesson_type', LessonType::Document)->firstOrFail();

        $this->assertNotNull($lesson->managedFilePath());
        Storage::disk('local')->assertExists($lesson->managedFilePath());
    }

    public function test_admin_can_upload_image_lesson_to_private_storage(): void
    {
        $admin = $this->userWithRole('Admin');
        $file = UploadedFile::fake()->image('html-diagram.webp', 400, 300);

        $this->actingAs($admin)
            ->post(route('admin.lessons.store'), $this->textPayload([
                'lesson_type' => LessonType::Image->value,
                'content' => 'An annotated HTML diagram.',
                'file' => $file,
            ]))
            ->assertRedirect();

        $lesson = Lesson::query()->where('lesson_type', LessonType::Image)->firstOrFail();

        $this->assertNotNull($lesson->managedFilePath());
        Storage::disk('local')->assertExists($lesson->managedFilePath());
    }

    public function test_invalid_and_oversized_lesson_files_are_rejected(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->post(route('admin.lessons.store'), $this->textPayload([
                'lesson_type' => LessonType::Document->value,
                'file' => UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream'),
            ]))
            ->assertSessionHasErrors('file');

        $this->actingAs($admin)
            ->post(route('admin.lessons.store'), $this->textPayload([
                'lesson_type' => LessonType::Document->value,
                'file' => UploadedFile::fake()->create('huge.pdf', 20481, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('file');
    }

    public function test_external_url_requires_http_or_https_and_is_required_for_video_and_link(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->post(route('admin.lessons.store'), $this->textPayload([
                'lesson_type' => LessonType::Video->value,
                'content' => null,
                'external_url' => null,
            ]))
            ->assertSessionHasErrors('external_url');

        $this->actingAs($admin)
            ->post(route('admin.lessons.store'), $this->textPayload([
                'lesson_type' => LessonType::Link->value,
                'content' => null,
                'external_url' => 'ftp://example.com/resource',
            ]))
            ->assertSessionHasErrors('external_url');
    }

    public function test_lesson_slug_is_unique_within_module(): void
    {
        $admin = $this->userWithRole('Admin');
        $module = Module::factory()->create();
        Lesson::factory()->for($module)->create(['slug' => 'introduction']);

        $this->actingAs($admin)
            ->post(route('admin.lessons.store'), $this->textPayload([
                'module_id' => $module->id,
                'slug' => 'introduction',
            ]))
            ->assertSessionHasErrors('slug');
    }

    public function test_same_lesson_slug_is_allowed_in_different_modules(): void
    {
        $admin = $this->userWithRole('Admin');
        $first = Module::factory()->create();
        $second = Module::factory()->create();
        Lesson::factory()->for($first)->create(['slug' => 'introduction']);

        $this->actingAs($admin)
            ->post(route('admin.lessons.store'), $this->textPayload([
                'module_id' => $second->id,
                'slug' => 'introduction',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('lessons', [
            'module_id' => $second->id,
            'slug' => 'introduction',
        ]);
    }

    public function test_lesson_type_is_cast_to_enum(): void
    {
        $lesson = Lesson::factory()->video()->create();

        $this->assertSame(LessonType::Video, $lesson->lesson_type);
    }

    public function test_admin_can_update_lesson(): void
    {
        $admin = $this->userWithRole('Admin');
        $newModule = Module::factory()->create();
        $lesson = Lesson::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.lessons.update', $lesson), $this->textPayload([
                'module_id' => $newModule->id,
                'title' => 'Updated Lesson',
                'slug' => 'updated-lesson',
                'duration_minutes' => 45,
                'sort_order' => 5,
                'status' => AcademicStatus::Inactive->value,
            ]))
            ->assertRedirect(route('admin.lessons.show', $lesson));

        $lesson->refresh();

        $this->assertTrue($lesson->module->is($newModule));
        $this->assertSame('Updated Lesson', $lesson->title);
        $this->assertSame(45, $lesson->duration_minutes);
        $this->assertSame(AcademicStatus::Inactive, $lesson->status);
    }

    public function test_replacing_uploaded_file_cleans_up_previous_managed_file(): void
    {
        $admin = $this->userWithRole('Admin');
        $lesson = $this->createUploadedDocument($admin);
        $oldPath = $lesson->managedFilePath();

        $this->actingAs($admin)
            ->post(route('admin.lessons.update', $lesson), $this->textPayload([
                '_method' => 'put',
                'module_id' => $lesson->module_id,
                'lesson_type' => LessonType::Document->value,
                'file' => UploadedFile::fake()->create('replacement.pdf', 200, 'application/pdf'),
            ]))
            ->assertRedirect(route('admin.lessons.show', $lesson));

        $lesson->refresh();

        $this->assertNotSame($oldPath, $lesson->managedFilePath());
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($lesson->managedFilePath());
    }

    public function test_admin_can_soft_delete_lesson_and_managed_file_is_cleaned(): void
    {
        $admin = $this->userWithRole('Admin');
        $lesson = $this->createUploadedDocument($admin);
        $path = $lesson->managedFilePath();

        $this->actingAs($admin)
            ->delete(route('admin.lessons.destroy', $lesson))
            ->assertRedirect(route('admin.lessons.index'));

        $this->assertSoftDeleted($lesson);
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($lesson->fresh()->file_path);
    }

    public function test_authorized_admin_and_tutor_can_access_lesson_file(): void
    {
        $lesson = Lesson::factory()->document()->create();
        $path = $lesson->managedFilePath();
        Storage::disk('local')->put($path, 'PDF contents');

        foreach (['Admin', 'Tutor'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('admin.lessons.file', $lesson))
                ->assertOk();
        }
    }

    public function test_unauthorized_users_cannot_access_lesson_files(): void
    {
        $lesson = Lesson::factory()->document()->create();
        Storage::disk('local')->put($lesson->managedFilePath(), 'PDF contents');

        $this->get(route('admin.lessons.file', $lesson))
            ->assertRedirect(route('login'));

        $this->actingAs($this->userWithRole('Student'))
            ->get(route('admin.lessons.file', $lesson))
            ->assertForbidden();
    }

    public function test_browser_supplied_or_traversal_file_paths_cannot_be_used(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->post(route('admin.lessons.store'), $this->textPayload([
                'file_path' => '../../secret.pdf',
            ]))
            ->assertRedirect();

        $created = Lesson::query()->latest('id')->firstOrFail();
        $this->assertNull($created->file_path);

        Storage::disk('local')->put('secret.pdf', 'sensitive');
        $created->update(['file_path' => '../secret.pdf']);

        $this->actingAs($admin)
            ->get(route('admin.lessons.file', $created))
            ->assertNotFound();

        Storage::disk('local')->assertExists('secret.pdf');
    }

    public function test_missing_lesson_file_returns_not_found(): void
    {
        $admin = $this->userWithRole('Admin');
        $lesson = Lesson::factory()->document()->create();

        $this->actingAs($admin)
            ->get(route('admin.lessons.file', $lesson))
            ->assertNotFound();
    }

    public function test_lesson_filters_work(): void
    {
        $admin = $this->userWithRole('Admin');
        $program = Program::factory()->create();
        $course = Course::factory()->for($program)->create();
        $competency = Competency::factory()->for($course)->create();
        $module = Module::factory()->for($competency)->create();
        $expected = Lesson::factory()->for($module)->video()->create([
            'title' => 'Unique Semantic Video',
            'status' => AcademicStatus::Inactive,
        ]);
        Lesson::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.lessons.index', [
                'search' => 'Unique Semantic',
                'program_id' => $program->id,
                'course_id' => $course->id,
                'competency_id' => $competency->id,
                'module_id' => $module->id,
                'lesson_type' => LessonType::Video->value,
                'status' => AcademicStatus::Inactive->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('lessons.data', 1)
                ->where('lessons.data.0.id', $expected->id));
    }

    public function test_lesson_relationships_and_ordering_work(): void
    {
        $module = Module::factory()->create();
        $alpha = Lesson::factory()->for($module)->create(['title' => 'Alpha', 'sort_order' => 2]);
        $first = Lesson::factory()->for($module)->create(['title' => 'Zeta', 'sort_order' => 1]);
        $beta = Lesson::factory()->for($module)->create(['title' => 'Beta', 'sort_order' => 2]);

        $this->assertTrue($first->module->is($module));
        $this->assertEquals(
            [$first->id, $alpha->id, $beta->id],
            $module->lessons->modelKeys(),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function textPayload(array $overrides = []): array
    {
        return array_merge([
            'module_id' => Module::factory()->create()->id,
            'title' => 'HTML Document Structure',
            'slug' => 'html-document-structure',
            'lesson_type' => LessonType::Text->value,
            'content' => 'Learn the parts of a valid HTML document.',
            'external_url' => null,
            'duration_minutes' => 15,
            'sort_order' => 0,
            'status' => AcademicStatus::Active->value,
        ], $overrides);
    }

    private function createUploadedDocument(User $admin): Lesson
    {
        $this->actingAs($admin)
            ->post(route('admin.lessons.store'), $this->textPayload([
                'lesson_type' => LessonType::Document->value,
                'file' => UploadedFile::fake()->create('original.pdf', 100, 'application/pdf'),
            ]))
            ->assertRedirect();

        return Lesson::query()->where('lesson_type', LessonType::Document)->firstOrFail();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
