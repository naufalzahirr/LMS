<?php

namespace Tests\Feature\Student;

use App\Enums\LessonAssetType;
use App\Models\Competency;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\LessonAsset;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RichLessonPlayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_student_opens_rich_lesson_with_trusted_video_and_authorized_asset_urls(): void
    {
        $student = User::factory()->create();
        $student->assignRole('Student');
        $competency = Competency::factory()->create();
        $module = Module::factory()->for($competency)->create();
        $lesson = Lesson::factory()->for($module)->create();
        $learningClass = LearningClass::factory()->for($competency->course)->create();
        $enrollment = Enrollment::factory()->for($learningClass)->create(['student_id' => $student->id]);
        $path = "lesson-assets/{$lesson->id}/diagram.png";
        Storage::disk('local')->put($path, 'image bytes');
        $asset = LessonAsset::query()->create([
            'lesson_id' => $lesson->id,
            'asset_type' => LessonAssetType::Image,
            'original_name' => 'diagram.png',
            'file_path' => $path,
            'mime_type' => 'image/png',
            'file_size' => 11,
            'alt_text' => 'HTML table diagram',
        ]);
        $lesson->update(['content_document' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '<script>shown as code-like text only</script>']]],
                ['type' => 'callout', 'attrs' => ['type' => 'info'], 'content' => [[
                    'type' => 'text',
                    'text' => 'Review the table structure before continuing.',
                ]]],
                ['type' => 'externalVideo', 'attrs' => [
                    'url' => 'https://www.youtube.com/watch?v=UB1O30fR-EE',
                    'title' => 'HTML walkthrough',
                    'caption' => null,
                ]],
                ['type' => 'lessonImage', 'attrs' => [
                    'lessonAssetId' => $asset->id,
                    'altText' => 'HTML table diagram',
                    'caption' => null,
                    'alignment' => 'center',
                    'size' => 'large',
                    'decorative' => false,
                ]],
            ],
        ]]);

        $this->actingAs($student)
            ->get(route('student.lessons.show', [$learningClass, $lesson]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/lessons/Show')
                ->where('lesson.content_document.content.0.content.0.text', '<script>shown as code-like text only</script>')
                ->where('lesson.content_document.content.1.attrs.type', 'info')
                ->where('lesson.content_document.content.1.content.0.text', 'Review the table structure before continuing.')
                ->where('lesson.content_document.content.2.attrs.embedUrl', 'https://www.youtube-nocookie.com/embed/UB1O30fR-EE')
                ->where(
                    'lesson.content_document.content.3.attrs.url',
                    route('student.lesson-assets.file', [$learningClass, $lesson, $asset]),
                )
                ->missing('lesson.content_document.content.3.attrs.file_path')
                ->where('learningClass.completed_lessons', 0)
                ->where('learningClass.total_lessons', 1));

        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'status' => 'in_progress',
        ]);
    }
}
