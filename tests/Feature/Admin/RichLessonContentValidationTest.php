<?php

namespace Tests\Feature\Admin;

use App\Enums\AcademicStatus;
use App\Enums\LessonAssetType;
use App\Enums\LessonCheckpointType;
use App\Models\Lesson;
use App\Models\LessonAsset;
use App\Models\LessonCheckpoint;
use App\Models\User;
use App\Services\LessonContentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_complete_multiblock_document_preserves_order_formatting_and_code_exactly(): void
    {
        $admin = $this->admin();
        $lesson = Lesson::factory()->create();
        $image = $this->asset($lesson, LessonAssetType::Image);
        $pdf = $this->asset($lesson, LessonAssetType::Document);
        $checkpoint = LessonCheckpoint::factory()->for($lesson)->create([
            'checkpoint_type' => LessonCheckpointType::TrueFalse,
            'prompt' => 'Rich lesson content is stored as structured data.',
            'configuration' => [],
            'answer_key' => ['correct_boolean' => true],
        ]);
        $codeSamples = [
            'html' => "<main>\n  <h1>Lesson</h1>\n</main>",
            'css' => ".lesson {\n  display: grid;\n}",
            'javascript' => "const lesson = { ready: true };\nconsole.log(lesson);",
            'php' => "<?php\n\nreturn ['lesson' => true];",
            'python' => "def lesson():\n    return {'ready': True}",
            'cpp' => "#include <iostream>\nint main() {\n  return 0;\n}",
            'json' => "{\n  \"lesson\": true\n}",
            'bash' => "#!/usr/bin/env bash\nprintf '%s\\n' \"lesson\"",
        ];
        $document = [
            'type' => 'doc',
            'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'Complete Rich Lesson']]],
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Formatting']]],
                ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'Details']]],
                ['type' => 'paragraph', 'content' => [
                    ['type' => 'text', 'text' => 'Several paragraphs begin here. '],
                    ['type' => 'text', 'text' => 'Bold', 'marks' => [['type' => 'bold']]],
                    ['type' => 'text', 'text' => ' and '],
                    ['type' => 'text', 'text' => 'italic', 'marks' => [['type' => 'italic']]],
                    ['type' => 'text', 'text' => ' with '],
                    ['type' => 'text', 'text' => 'a safe link', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com/lesson?source=qa']]]],
                    ['type' => 'text', 'text' => '.'],
                ]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A second paragraph remains independent.']]],
                ['type' => 'bulletList', 'content' => [
                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Bullet one']]]]],
                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Bullet two']]]]],
                ]],
                ['type' => 'orderedList', 'attrs' => ['start' => 2], 'content' => [
                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Numbered item']]]]],
                ]],
                ['type' => 'blockquote', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Structured content stays inert.']]]]],
                ['type' => 'horizontalRule'],
                ['type' => 'table', 'content' => [
                    ['type' => 'tableRow', 'content' => [
                        ['type' => 'tableHeader', 'attrs' => ['colspan' => 1, 'rowspan' => 1, 'colwidth' => null], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Block']]]]],
                        ['type' => 'tableHeader', 'attrs' => ['colspan' => 1, 'rowspan' => 1, 'colwidth' => null], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'State']]]]],
                    ]],
                    ['type' => 'tableRow', 'content' => [
                        ['type' => 'tableCell', 'attrs' => ['colspan' => 1, 'rowspan' => 1, 'colwidth' => null], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Table']]]]],
                        ['type' => 'tableCell', 'attrs' => ['colspan' => 1, 'rowspan' => 1, 'colwidth' => null], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Preserved']]]]],
                    ]],
                ]],
                ...collect($codeSamples)->map(fn (string $code, string $language): array => [
                    'type' => 'codeBlock',
                    'attrs' => ['language' => $language],
                    'content' => [['type' => 'text', 'text' => $code]],
                ])->values()->all(),
                ['type' => 'lessonImage', 'attrs' => [
                    'lessonAssetId' => $image->id,
                    'altText' => 'Accessible image',
                    'caption' => 'Image caption',
                    'alignment' => 'center',
                    'size' => 'large',
                    'decorative' => false,
                ]],
                ...collect(LessonContentService::CALLOUT_TYPES)->map(fn (string $type): array => [
                    'type' => 'callout',
                    'attrs' => ['type' => $type],
                    'content' => [['type' => 'text', 'text' => "A {$type} callout with durable content."]],
                ])->all(),
                ['type' => 'externalVideo', 'attrs' => [
                    'url' => 'https://youtu.be/dQw4w9WgXcQ?t=42',
                    'title' => 'Trusted video',
                    'caption' => 'Responsive external media',
                ]],
                ['type' => 'lessonFile', 'attrs' => [
                    'lessonAssetId' => $pdf->id,
                    'title' => 'Lesson PDF',
                    'caption' => 'Downloadable resource',
                ]],
                ['type' => 'lessonCheckpoint', 'attrs' => ['checkpointId' => $checkpoint->id]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Final paragraph after every rich block.']]],
            ],
        ];

        $this->actingAs($admin)
            ->put(route('admin.lessons.update', $lesson), $this->payload($lesson, $document))
            ->assertRedirect(route('admin.lessons.show', $lesson));

        $stored = $lesson->refresh()->content_document;
        $this->assertSame(
            [
                'heading', 'heading', 'heading', 'paragraph', 'paragraph', 'bulletList',
                'orderedList', 'blockquote', 'horizontalRule', 'table',
                ...array_fill(0, count($codeSamples), 'codeBlock'),
                'lessonImage', 'callout', 'callout', 'callout', 'callout',
                'externalVideo', 'lessonFile', 'lessonCheckpoint', 'paragraph',
            ],
            array_column($stored['content'], 'type'),
        );
        $this->assertSame([1, 2, 3], array_map(
            fn (array $node): int => $node['attrs']['level'],
            array_slice($stored['content'], 0, 3),
        ));
        $storedCode = array_slice($stored['content'], 10, count($codeSamples));
        $this->assertSame(array_keys($codeSamples), array_column(array_column($storedCode, 'attrs'), 'language'));
        $this->assertSame(array_values($codeSamples), array_map(
            fn (array $node): string => $node['content'][0]['text'],
            $storedCode,
        ));
        $this->assertSame('youtube', $stored['content'][23]['attrs']['provider']);

        $this->actingAs($admin)
            ->get(route('admin.lessons.show', $lesson))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lesson.content_document.content.23.attrs.embedUrl', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
                ->missing('lesson.content_document.content.25.attrs.checkpoint.correct_boolean'));

        $this->actingAs($admin)
            ->get(route('admin.lessons.edit', $lesson))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lesson.content_document.content.0.content.0.text', 'Complete Rich Lesson')
                ->where('lesson.content_document.content.10.content.0.text', $codeSamples['html'])
                ->where('lesson.content_document.content.25.attrs.checkpoint.correct_boolean', true)
                ->where('lesson.content_document.content.26.content.0.text', 'Final paragraph after every rich block.'));
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
        $name = $type === LessonAssetType::Image ? 'image.png' : 'resource.pdf';

        return LessonAsset::query()->create([
            'lesson_id' => $lesson->id,
            'asset_type' => $type,
            'original_name' => $name,
            'file_path' => "lesson-assets/{$lesson->id}/{$name}",
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
