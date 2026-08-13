<?php

namespace Tests\Feature\Admin;

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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LessonAssetSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_authorized_admin_uploads_private_image_and_cannot_supply_a_path(): void
    {
        $lesson = Lesson::factory()->create();
        $admin = $this->user('Admin');

        $response = $this->actingAs($admin)->post(route('admin.lesson-assets.store', $lesson), [
            'asset_type' => 'image',
            'file' => UploadedFile::fake()->image('diagram.png', 800, 600),
            'alt_text' => 'Diagram of an HTML table',
            'caption' => 'Rows and columns',
        ]);

        $response->assertCreated()
            ->assertJsonPath('asset.asset_type', 'image')
            ->assertJsonMissingPath('asset.file_path');
        $asset = LessonAsset::query()->firstOrFail();
        $this->assertSame($lesson->id, $asset->lesson_id);
        Storage::disk('local')->assertExists($asset->managedFilePath());

        $this->actingAs($admin)->post(route('admin.lesson-assets.store', $lesson), [
            'asset_type' => 'document',
            'file' => UploadedFile::fake()->create('guide.pdf', 10, 'application/pdf'),
            'file_path' => '../secrets.pdf',
        ])->assertSessionHasErrors('file_path');
    }

    public function test_explicitly_decorative_image_may_omit_alt_text(): void
    {
        $lesson = Lesson::factory()->create();
        $admin = $this->user('Admin');

        $this->actingAs($admin)->post(route('admin.lesson-assets.store', $lesson), [
            'asset_type' => 'image',
            'file' => UploadedFile::fake()->image('divider.png', 800, 80),
            'decorative' => true,
        ])->assertCreated()->assertJsonPath('asset.alt_text', null);

        $this->assertDatabaseHas('lesson_assets', [
            'lesson_id' => $lesson->id,
            'alt_text' => null,
        ]);
    }

    public function test_scoped_tutor_uploads_pdf_only_to_an_assigned_active_course(): void
    {
        $lesson = Lesson::factory()->create();
        $tutor = $this->user('Tutor');
        $class = LearningClass::factory()->for($lesson->module->competency->course)->create([
            'status' => LearningClassStatus::Active,
        ]);
        $class->tutors()->attach($tutor);

        $this->actingAs($tutor)->post(route('admin.lesson-assets.store', $lesson), [
            'asset_type' => 'document',
            'file' => UploadedFile::fake()->create('exercise.pdf', 500, 'application/pdf'),
        ])->assertCreated();

        $otherLesson = Lesson::factory()->create();
        $this->actingAs($tutor)->post(route('admin.lesson-assets.store', $otherLesson), [
            'asset_type' => 'image',
            'file' => UploadedFile::fake()->image('other.png'),
            'alt_text' => 'Other course image',
        ])->assertForbidden();
    }

    public function test_authorized_admin_can_update_asset_accessibility_metadata(): void
    {
        $lesson = Lesson::factory()->create();
        $admin = $this->user('Admin');
        $image = $this->storedAsset($lesson, LessonAssetType::Image, 'diagram.png');
        $document = $this->storedAsset($lesson, LessonAssetType::Document, 'guide.pdf');

        $this->actingAs($admin)
            ->patchJson(route('admin.lesson-assets.update', [$lesson, $image]), [
                'alt_text' => 'Updated accessible diagram description',
                'caption' => 'Updated diagram caption',
            ])
            ->assertOk()
            ->assertJsonPath('asset.alt_text', 'Updated accessible diagram description')
            ->assertJsonMissingPath('asset.file_path');

        $this->actingAs($admin)
            ->patchJson(route('admin.lesson-assets.update', [$lesson, $document]), [
                'caption' => 'Updated PDF description',
            ])
            ->assertOk()
            ->assertJsonPath('asset.caption', 'Updated PDF description');

        $this->assertDatabaseHas('lesson_assets', [
            'id' => $image->id,
            'alt_text' => 'Updated accessible diagram description',
            'caption' => 'Updated diagram caption',
        ]);
    }

    public function test_student_and_parent_cannot_upload_or_manage_assets(): void
    {
        $lesson = Lesson::factory()->create();

        foreach (['Student', 'Parent'] as $role) {
            $this->actingAs($this->user($role))->post(route('admin.lesson-assets.store', $lesson), [
                'asset_type' => 'image',
                'file' => UploadedFile::fake()->image('blocked.png'),
                'alt_text' => 'Blocked image',
            ])->assertForbidden();
        }

        $this->assertDatabaseCount('lesson_assets', 0);
    }

    public function test_invalid_mime_and_oversized_assets_are_rejected(): void
    {
        $lesson = Lesson::factory()->create();
        $admin = $this->user('Admin');
        $requests = [
            [
                'asset_type' => 'image',
                'file' => UploadedFile::fake()->create('image.svg', 10, 'image/svg+xml'),
                'alt_text' => 'Unsafe SVG',
            ],
            [
                'asset_type' => 'image',
                'file' => UploadedFile::fake()->image('huge.png')->size(10241),
                'alt_text' => 'Oversized image',
            ],
            [
                'asset_type' => 'document',
                'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            ],
            [
                'asset_type' => 'document',
                'file' => UploadedFile::fake()->create('huge.pdf', 20481, 'application/pdf'),
            ],
        ];

        foreach ($requests as $payload) {
            $this->actingAs($admin)
                ->post(route('admin.lesson-assets.store', $lesson), $payload)
                ->assertSessionHasErrors('file');
        }

        $this->assertDatabaseCount('lesson_assets', 0);
    }

    public function test_enrolled_student_can_view_image_and_download_pdf_but_parent_cannot(): void
    {
        [$student, $learningClass, $lesson] = $this->studentContext();
        $image = $this->storedAsset($lesson, LessonAssetType::Image, 'diagram.png');
        $pdf = $this->storedAsset($lesson, LessonAssetType::Document, 'exercise.pdf');
        $lesson->forceFill(['content_document' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'lessonImage', 'attrs' => [
                    'lessonAssetId' => $image->id,
                    'altText' => 'Accessible image',
                    'caption' => null,
                    'alignment' => 'center',
                    'size' => 'large',
                    'decorative' => false,
                ]],
                ['type' => 'lessonFile', 'attrs' => [
                    'lessonAssetId' => $pdf->id,
                    'title' => 'Exercise PDF',
                    'caption' => null,
                ]],
            ],
        ]])->save();

        $this->actingAs($student)
            ->get(route('student.lesson-assets.file', [$learningClass, $lesson, $image]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($student)
            ->get(route('student.lesson-assets.file', [$learningClass, $lesson, $pdf, 'download' => 1]))
            ->assertOk()
            ->assertDownload('exercise.pdf');

        $this->actingAs($this->user('Parent'))
            ->get(route('student.lesson-assets.file', [$learningClass, $lesson, $image]))
            ->assertForbidden();
    }

    public function test_student_cannot_access_another_course_or_lessons_asset(): void
    {
        [$student, $learningClass, $lesson] = $this->studentContext();
        $otherLesson = Lesson::factory()->create();
        $otherAsset = $this->storedAsset($otherLesson, LessonAssetType::Image, 'secret.png');

        $this->actingAs($student)
            ->get(route('student.lesson-assets.file', [$learningClass, $otherLesson, $otherAsset]))
            ->assertForbidden();
        $this->actingAs($student)
            ->get(route('student.lesson-assets.file', [$learningClass, $lesson, $otherAsset]))
            ->assertNotFound();
    }

    public function test_student_cannot_access_an_unreferenced_asset_from_an_accessible_lesson(): void
    {
        [$student, $learningClass, $lesson] = $this->studentContext();
        $removedAsset = $this->storedAsset($lesson, LessonAssetType::Image, 'removed.png');
        $lesson->forceFill([
            'content_document' => [
                'type' => 'doc',
                'content' => [['type' => 'paragraph']],
            ],
        ])->save();

        $this->actingAs($student)
            ->get(route('student.lesson-assets.file', [$learningClass, $lesson, $removedAsset]))
            ->assertNotFound();

        Storage::disk('local')->assertExists($removedAsset->managedFilePath());
    }

    public function test_path_traversal_asset_is_never_served(): void
    {
        [$student, $learningClass, $lesson] = $this->studentContext();
        $asset = LessonAsset::query()->create([
            'lesson_id' => $lesson->id,
            'asset_type' => LessonAssetType::Document,
            'original_name' => 'secret.pdf',
            'file_path' => '../secret.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
        ]);

        $this->actingAs($student)
            ->get(route('student.lesson-assets.file', [$learningClass, $lesson, $asset]))
            ->assertNotFound();
    }

    public function test_referenced_asset_cannot_be_deleted_but_unreferenced_asset_can(): void
    {
        $lesson = Lesson::factory()->create();
        $admin = $this->user('Admin');
        $referenced = $this->storedAsset($lesson, LessonAssetType::Image, 'used.png');
        $unused = $this->storedAsset($lesson, LessonAssetType::Image, 'unused.png');
        $lesson->update(['content_document' => [
            'type' => 'doc',
            'content' => [['type' => 'lessonImage', 'attrs' => [
                'lessonAssetId' => $referenced->id,
                'altText' => 'Used image',
                'caption' => null,
                'alignment' => 'center',
                'size' => 'large',
                'decorative' => false,
            ]]],
        ]]);

        $this->actingAs($admin)
            ->delete(route('admin.lesson-assets.destroy', [$lesson, $referenced]))
            ->assertSessionHasErrors('asset');
        Storage::disk('local')->assertExists($referenced->managedFilePath());

        $unusedPath = $unused->managedFilePath();
        $this->actingAs($admin)
            ->delete(route('admin.lesson-assets.destroy', [$lesson, $unused]))
            ->assertNoContent();
        $this->assertDatabaseMissing('lesson_assets', ['id' => $unused->id]);
        Storage::disk('local')->assertMissing($unusedPath);
    }

    /** @return array{User, LearningClass, Lesson} */
    private function studentContext(): array
    {
        $student = $this->user('Student');
        $course = Course::factory()->create();
        $competency = Competency::factory()->for($course)->create();
        $module = Module::factory()->for($competency)->create();
        $lesson = Lesson::factory()->for($module)->create();
        $learningClass = LearningClass::factory()->for($course)->create();
        Enrollment::factory()->for($learningClass)->create(['student_id' => $student->id]);

        return [$student, $learningClass, $lesson];
    }

    private function storedAsset(Lesson $lesson, LessonAssetType $type, string $name): LessonAsset
    {
        $path = "lesson-assets/{$lesson->id}/{$name}";
        Storage::disk('local')->put($path, 'private bytes');

        return LessonAsset::query()->create([
            'lesson_id' => $lesson->id,
            'asset_type' => $type,
            'original_name' => $name,
            'file_path' => $path,
            'mime_type' => $type === LessonAssetType::Image ? 'image/png' : 'application/pdf',
            'file_size' => 13,
            'alt_text' => $type === LessonAssetType::Image ? 'Accessible image' : null,
        ]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
