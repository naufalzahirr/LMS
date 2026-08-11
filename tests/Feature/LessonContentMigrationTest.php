<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\RemedialAssignmentLesson;
use App\Services\LessonContentMigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LessonContentMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_legacy_text_lesson_converts_to_paragraph_content_idempotently(): void
    {
        $lesson = Lesson::factory()->create([
            'content' => "First paragraph.\n\nSecond paragraph.",
            'content_document' => null,
        ]);
        $service = app(LessonContentMigrationService::class);

        $first = $service->migrateLesson($lesson);
        $second = $service->migrateLesson($first);

        $this->assertSame('doc', $first->content_document['type']);
        $this->assertSame('First paragraph.', $first->content_document['content'][0]['content'][0]['text']);
        $this->assertSame('Second paragraph.', $first->content_document['content'][1]['content'][0]['text']);
        $this->assertSame($first->content_document, $second->content_document);
        $this->assertDatabaseCount('lesson_assets', 0);
    }

    public function test_legacy_video_and_link_preserve_notes_and_safe_urls(): void
    {
        $video = Lesson::factory()->video()->create(['content_document' => null]);
        $link = Lesson::factory()->link()->create(['content_document' => null]);
        $service = app(LessonContentMigrationService::class);

        $video = $service->migrateLesson($video);
        $link = $service->migrateLesson($link);

        $this->assertSame('paragraph', $video->content_document['content'][0]['type']);
        $this->assertSame('externalVideo', $video->content_document['content'][1]['type']);
        $this->assertSame($video->external_url, $video->content_document['content'][1]['attrs']['url']);
        $this->assertSame('paragraph', $link->content_document['content'][1]['type']);
        $this->assertSame($link->external_url, $link->content_document['content'][1]['content'][0]['marks'][0]['attrs']['href']);
    }

    public function test_legacy_image_and_document_use_managed_paths_without_losing_files(): void
    {
        $image = Lesson::factory()->image()->create(['content_document' => null]);
        $document = Lesson::factory()->document()->create(['content_document' => null]);
        Storage::disk('local')->put($image->managedFilePath(), 'image bytes');
        Storage::disk('local')->put($document->managedFilePath(), 'pdf bytes');
        $service = app(LessonContentMigrationService::class);

        $image = $service->migrateLesson($image);
        $document = $service->migrateLesson($document);

        $imageNode = $image->content_document['content'][1];
        $documentNode = $document->content_document['content'][1];
        $this->assertSame('lessonImage', $imageNode['type']);
        $this->assertSame('lessonFile', $documentNode['type']);
        $this->assertSame($image->file_path, $image->assets()->firstOrFail()->file_path);
        $this->assertSame($document->file_path, $document->assets()->firstOrFail()->file_path);
        Storage::disk('local')->assertExists($image->managedFilePath());
        Storage::disk('local')->assertExists($document->managedFilePath());

        $service->migrateLesson($image);
        $service->migrateLesson($document);
        $this->assertDatabaseCount('lesson_assets', 2);
    }

    public function test_conversion_does_not_change_progress_or_remedial_relationships(): void
    {
        $lesson = Lesson::factory()->create(['content_document' => null]);
        $progress = LessonProgress::factory()->create(['lesson_id' => $lesson->id]);
        $remedialItem = RemedialAssignmentLesson::factory()->create(['lesson_id' => $lesson->id]);
        $status = $progress->status;
        $completedAt = $remedialItem->completed_at;

        app(LessonContentMigrationService::class)->migrateLesson($lesson);

        $this->assertSame($status, $progress->fresh()->status);
        $this->assertEquals($completedAt, $remedialItem->fresh()->completed_at);
        $this->assertTrue($remedialItem->fresh()->lesson->is($lesson));
    }
}
