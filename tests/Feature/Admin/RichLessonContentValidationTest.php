<?php

namespace Tests\Feature\Admin;

use App\Enums\AcademicStatus;
use App\Enums\LessonAssetType;
use App\Models\Lesson;
use App\Models\LessonAsset;
use App\Models\User;
use App\Services\LessonContentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RichLessonContentValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_valid_rich_lesson_document_is_accepted_and_searchable_text_can_be_extracted(): void
    {
        $admin = $this->admin();
        $lesson = Lesson::factory()->create();
        $document = [
            'type' => 'doc',
            'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'HTML Tables']]],
                ['type' => 'paragraph', 'content' => [[
                    'type' => 'text',
                    'text' => 'Read MDN',
                    'marks' => [['type' => 'bold'], ['type' => 'link', 'attrs' => ['href' => 'https://developer.mozilla.org/']]],
                ]]],
                ['type' => 'bulletList', 'content' => [[
                    'type' => 'listItem',
                    'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Use semantic headings']]]],
                ]]],
                ['type' => 'codeBlock', 'attrs' => ['language' => 'html'], 'content' => [['type' => 'text', 'text' => '<table></table>']]],
                ['type' => 'callout', 'attrs' => ['type' => 'info'], 'content' => [['type' => 'text', 'text' => 'Use th for headings.']]],
                ['type' => 'externalVideo', 'attrs' => [
                    'url' => 'https://www.youtube.com/watch?v=UB1O30fR-EE',
                    'title' => 'HTML table walkthrough',
                    'caption' => null,
                ]],
                ['type' => 'horizontalRule'],
            ],
        ];

        $this->actingAs($admin)
            ->put(route('admin.lessons.update', $lesson), $this->payload($lesson, $document))
            ->assertRedirect(route('admin.lessons.show', $lesson));

        $lesson->refresh();
        $this->assertSame('externalVideo', $lesson->content_document['content'][5]['type']);
        $this->assertSame('youtube', $lesson->content_document['content'][5]['attrs']['provider']);
        $this->assertStringContainsString(
            'HTML Tables Read MDN',
            app(LessonContentService::class)->extractPlainText($lesson->content_document),
        );
    }

    public function test_unsupported_or_dangerous_content_is_rejected(): void
    {
        $admin = $this->admin();
        $lesson = Lesson::factory()->create();
        $documents = [
            'unknown node' => ['type' => 'doc', 'content' => [['type' => 'interactivePractice']]],
            'raw html node' => ['type' => 'doc', 'content' => [['type' => 'html', 'attrs' => ['html' => '<script>alert(1)</script>']]]],
            'javascript link' => ['type' => 'doc', 'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'unsafe', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]]],
            ]]],
            'invalid video' => [
                'type' => 'doc',
                'content' => [[
                    'type' => 'externalVideo',
                    'attrs' => ['url' => 'https://example.com/video', 'title' => 'Not supported'],
                ]],
            ],
            'unsupported code language' => [
                'type' => 'doc',
                'content' => [['type' => 'codeBlock', 'attrs' => ['language' => 'ruby']]],
            ],
            'invalid callout' => [
                'type' => 'doc',
                'content' => [['type' => 'callout', 'attrs' => ['type' => 'success']]],
            ],
            'malformed callout attributes' => [
                'type' => 'doc',
                'content' => [['type' => 'callout', 'attrs' => ['type' => ['info']]]],
            ],
            'unknown callout property' => [
                'type' => 'doc',
                'content' => [['type' => 'callout', 'attrs' => ['type' => 'info', 'style' => 'display:none']]],
            ],
            'event handler attribute' => [
                'type' => 'doc',
                'content' => [['type' => 'paragraph', 'attrs' => ['onclick' => 'alert(1)']]],
            ],
        ];

        foreach ($documents as $document) {
            $this->actingAs($admin)
                ->from(route('admin.lessons.edit', $lesson))
                ->put(route('admin.lessons.update', $lesson), $this->payload($lesson, $document))
                ->assertRedirect(route('admin.lessons.edit', $lesson))
                ->assertSessionHasErrors('content_document');
        }
    }

    public function test_link_and_video_urls_follow_the_safe_shared_content_contract(): void
    {
        $lesson = Lesson::factory()->create();
        $service = app(LessonContentService::class);

        foreach (['http://example.com/resource', 'https://example.com/resource'] as $url) {
            $normalized = $service->normalize($lesson, [
                'type' => 'doc',
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [[
                        'type' => 'text',
                        'text' => 'Safe resource',
                        'marks' => [['type' => 'link', 'attrs' => ['href' => $url]]],
                    ]],
                ]],
            ]);

            $this->assertSame($url, $normalized['content'][0]['content'][0]['marks'][0]['attrs']['href']);
        }

        foreach (['javascript:alert(1)', 'ftp://example.com/resource', 'https://user:secret@example.com/resource', 'not a url'] as $url) {
            try {
                $service->normalize($lesson, [
                    'type' => 'doc',
                    'content' => [[
                        'type' => 'paragraph',
                        'content' => [[
                            'type' => 'text',
                            'text' => 'Unsafe resource',
                            'marks' => [['type' => 'link', 'attrs' => ['href' => $url]]],
                        ]],
                    ]],
                ]);
                $this->fail("Unsafe lesson link [{$url}] was accepted.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('content_document', $exception->errors());
            }
        }

        foreach ([
            ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube'],
            ['https://vimeo.com/76979871', 'vimeo'],
        ] as [$url, $provider]) {
            $normalized = $service->normalize($lesson, [
                'type' => 'doc',
                'content' => [[
                    'type' => 'externalVideo',
                    'attrs' => ['url' => $url, 'title' => 'Trusted video'],
                ]],
            ]);

            $this->assertSame($provider, $normalized['content'][0]['attrs']['provider']);
        }

        foreach (['not a url', 'https://example.com/video', 'ftp://vimeo.com/76979871'] as $url) {
            try {
                $service->normalize($lesson, [
                    'type' => 'doc',
                    'content' => [[
                        'type' => 'externalVideo',
                        'attrs' => ['url' => $url, 'title' => 'Untrusted video'],
                    ]],
                ]);
                $this->fail("Untrusted lesson video [{$url}] was accepted.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('content_document', $exception->errors());
            }
        }
    }

    public function test_nonexistent_cross_lesson_and_mismatched_assets_are_rejected(): void
    {
        $admin = $this->admin();
        $lesson = Lesson::factory()->create();
        $other = Lesson::factory()->create();
        $image = $this->asset($other, LessonAssetType::Image);
        $pdf = $this->asset($lesson, LessonAssetType::Document);
        $documents = [
            ['type' => 'doc', 'content' => [['type' => 'lessonImage', 'attrs' => $this->imageAttrs(999999)]]],
            ['type' => 'doc', 'content' => [['type' => 'lessonImage', 'attrs' => $this->imageAttrs($image->id)]]],
            ['type' => 'doc', 'content' => [['type' => 'lessonImage', 'attrs' => $this->imageAttrs($pdf->id)]]],
        ];

        foreach ($documents as $document) {
            $this->actingAs($admin)
                ->put(route('admin.lessons.update', $lesson), $this->payload($lesson, $document))
                ->assertSessionHasErrors('content_document');
        }
    }

    public function test_script_like_text_is_stored_only_as_inert_structured_text(): void
    {
        $lesson = Lesson::factory()->create();
        $document = ['type' => 'doc', 'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => '<script>alert(1)</script><img onerror="alert(2)">']],
        ]]];

        $this->actingAs($this->admin())
            ->put(route('admin.lessons.update', $lesson), $this->payload($lesson, $document))
            ->assertRedirect();

        $this->assertSame(
            '<script>alert(1)</script><img onerror="alert(2)">',
            $lesson->fresh()->content_document['content'][0]['content'][0]['text'],
        );
    }

    public function test_older_image_nodes_without_display_metadata_receive_safe_defaults(): void
    {
        $lesson = Lesson::factory()->create();
        $image = $this->asset($lesson, LessonAssetType::Image);
        $document = [
            'type' => 'doc',
            'content' => [[
                'type' => 'lessonImage',
                'attrs' => ['lessonAssetId' => $image->id],
            ]],
        ];

        $this->actingAs($this->admin())
            ->put(route('admin.lessons.update', $lesson), $this->payload($lesson, $document))
            ->assertRedirect();

        $attrs = $lesson->fresh()->content_document['content'][0]['attrs'];
        $this->assertSame('Accessible image', $attrs['altText']);
        $this->assertSame('center', $attrs['alignment']);
        $this->assertSame('large', $attrs['size']);
        $this->assertFalse($attrs['decorative']);
    }

    /** @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    private function payload(Lesson $lesson, array $document): array
    {
        return [
            'module_id' => $lesson->module_id,
            'title' => $lesson->title,
            'slug' => $lesson->slug,
            'content_document' => $document,
            'duration_minutes' => $lesson->duration_minutes,
            'sort_order' => $lesson->sort_order,
            'status' => AcademicStatus::Active->value,
        ];
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
    }

    private function asset(Lesson $lesson, LessonAssetType $type): LessonAsset
    {
        return LessonAsset::query()->create([
            'lesson_id' => $lesson->id,
            'asset_type' => $type,
            'original_name' => $type === LessonAssetType::Image ? 'image.png' : 'resource.pdf',
            'file_path' => "lesson-assets/{$lesson->id}/fixture",
            'mime_type' => $type === LessonAssetType::Image ? 'image/png' : 'application/pdf',
            'file_size' => 100,
            'alt_text' => $type === LessonAssetType::Image ? 'Accessible image' : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function imageAttrs(int $id): array
    {
        return [
            'lessonAssetId' => $id,
            'altText' => 'Accessible image',
            'caption' => null,
            'alignment' => 'center',
            'size' => 'large',
            'decorative' => false,
        ];
    }
}
