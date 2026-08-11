<?php

namespace App\Services;

use App\Enums\LessonAssetType;
use App\Models\Lesson;
use App\Models\LessonAsset;
use Closure;
use Illuminate\Validation\ValidationException;

final class LessonContentService
{
    /** @var list<string> */
    public const CODE_LANGUAGES = [
        'plain',
        'html',
        'css',
        'javascript',
        'typescript',
        'php',
        'sql',
        'python',
        'cpp',
        'java',
        'json',
        'bash',
    ];

    /** @var list<string> */
    public const CALLOUT_TYPES = ['info', 'tip', 'warning', 'important'];

    public function __construct(private readonly LessonVideoEmbedService $videoEmbed) {}

    /** @return array{type: string, content: array<int, mixed>} */
    public function emptyDocument(): array
    {
        return [
            'type' => 'doc',
            'content' => [['type' => 'paragraph']],
        ];
    }

    /**
     * Validate and reduce browser JSON to the canonical supported schema.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function normalize(Lesson $lesson, array $document): array
    {
        $this->onlyKeys($document, ['type', 'content']);

        if (($document['type'] ?? null) !== 'doc') {
            $this->invalid('The lesson document must use a doc root node.');
        }

        $content = $document['content'] ?? [];

        if (! is_array($content) || ! array_is_list($content)) {
            $this->invalid('The lesson document content must be an ordered node list.');
        }

        $topLevelTypes = [
            'paragraph', 'heading', 'bulletList', 'orderedList', 'blockquote',
            'table', 'horizontalRule', 'codeBlock', 'lessonImage', 'lessonFile',
            'externalVideo', 'callout',
        ];

        return [
            'type' => 'doc',
            'content' => array_map(function (mixed $node) use ($lesson, $topLevelTypes): array {
                if (! is_array($node) || ! in_array($node['type'] ?? null, $topLevelTypes, true)) {
                    $this->invalid('The lesson document contains a node in an invalid position.');
                }

                return $this->normalizeBlock($lesson, $node);
            }, $content),
        ];
    }

    /**
     * Add route-derived presentation attributes without persisting them.
     *
     * @param  Closure(LessonAsset): array{url: string, downloadUrl: string}  $assetUrls
     * @return array<string, mixed>
     */
    public function forRendering(Lesson $lesson, Closure $assetUrls): array
    {
        $document = $lesson->content_document ?? $this->emptyDocument();
        $normalized = $this->normalize($lesson, $document);

        return $this->mapNodes($normalized, function (array $node) use ($lesson, $assetUrls): array {
            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];

            if (in_array($node['type'], ['lessonImage', 'lessonFile'], true)) {
                $asset = $lesson->assets()->findOrFail((int) $attrs['lessonAssetId']);
                $urls = $assetUrls($asset);
                $node['attrs'] = [
                    ...$attrs,
                    ...$urls,
                    'originalName' => $asset->original_name,
                    'mimeType' => $asset->mime_type,
                    'fileSize' => $asset->file_size,
                ];
            }

            if ($node['type'] === 'externalVideo') {
                $video = $this->videoEmbed->parse((string) $attrs['url']);

                if ($video === null) {
                    $this->invalid('The lesson contains an invalid external video.');
                }

                $node['attrs'] = [...$attrs, 'embedUrl' => $video['embed_url']];
            }

            return $node;
        });
    }

    /** @param array<string, mixed> $document */
    public function extractPlainText(array $document): string
    {
        $parts = [];

        $this->walk($document, function (array $node) use (&$parts): void {
            if ($node['type'] === 'text' && is_string($node['text'] ?? null)) {
                $parts[] = $node['text'];
            }

            if (in_array($node['type'], ['lessonImage', 'lessonFile', 'externalVideo'], true)) {
                $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];

                foreach (['altText', 'title', 'caption'] as $key) {
                    if (is_string($attrs[$key] ?? null) && trim($attrs[$key]) !== '') {
                        $parts[] = $attrs[$key];
                    }
                }
            }
        });

        return trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? '');
    }

    /** @param array<string, mixed>|null $document
     * @return list<int>
     */
    public function referencedAssetIds(?array $document): array
    {
        if ($document === null) {
            return [];
        }

        $ids = [];
        $this->walk($document, function (array $node) use (&$ids): void {
            if (in_array($node['type'] ?? null, ['lessonImage', 'lessonFile'], true)) {
                $id = $node['attrs']['lessonAssetId'] ?? null;

                if (is_int($id)) {
                    $ids[] = $id;
                }
            }
        });

        return array_values(array_unique($ids));
    }

    /** @return array<string, mixed> */
    private function normalizeBlock(Lesson $lesson, mixed $node): array
    {
        if (! is_array($node) || ! is_string($node['type'] ?? null)) {
            $this->invalid('Every lesson block must have a supported node type.');
        }

        return match ($node['type']) {
            'paragraph' => $this->containerNode($lesson, $node, [], true),
            'heading' => $this->headingNode($lesson, $node),
            'bulletList' => $this->containerNode($lesson, $node, [], false, ['listItem']),
            'orderedList' => $this->orderedListNode($lesson, $node),
            'listItem' => $this->containerNode($lesson, $node, [], false),
            'blockquote' => $this->containerNode($lesson, $node, [], false),
            'table' => $this->containerNode($lesson, $node, [], false, ['tableRow']),
            'tableRow' => $this->containerNode($lesson, $node, [], false, ['tableCell', 'tableHeader']),
            'tableCell', 'tableHeader' => $this->tableCellNode($lesson, $node),
            'horizontalRule' => $this->leafNode($node),
            'codeBlock' => $this->codeBlockNode($node),
            'lessonImage' => $this->imageNode($lesson, $node),
            'lessonFile' => $this->fileNode($lesson, $node),
            'externalVideo' => $this->videoNode($node),
            'callout' => $this->calloutNode($lesson, $node),
            default => $this->invalid("Unsupported lesson node [{$node['type']}]."),
        };
    }

    /** @return array<string, mixed> */
    private function normalizeInline(Lesson $lesson, mixed $node): array
    {
        if (! is_array($node) || ! is_string($node['type'] ?? null)) {
            $this->invalid('Inline lesson content must use supported nodes.');
        }

        return match ($node['type']) {
            'text' => $this->textNode($node),
            'hardBreak' => $this->leafNode($node),
            default => $this->invalid("Unsupported inline lesson node [{$node['type']}]."),
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $attributeKeys
     * @param  list<string>|null  $childTypes
     * @return array<string, mixed>
     */
    private function containerNode(
        Lesson $lesson,
        array $node,
        array $attributeKeys,
        bool $inline,
        ?array $childTypes = null,
    ): array {
        $this->onlyKeys($node, ['type', 'attrs', 'content']);
        $attrs = $this->attributes($node, $attributeKeys);
        $content = $this->contentList($node);

        $normalized = array_map(function (mixed $child) use ($lesson, $inline, $childTypes): array {
            if ($childTypes !== null && (! is_array($child) || ! in_array($child['type'] ?? null, $childTypes, true))) {
                $this->invalid('A lesson node contains an invalid child node.');
            }

            return $inline
                ? $this->normalizeInline($lesson, $child)
                : $this->normalizeBlock($lesson, $child);
        }, $content);

        return array_filter([
            'type' => $node['type'],
            'attrs' => $attrs === [] ? null : $attrs,
            'content' => $normalized === [] ? null : $normalized,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function headingNode(Lesson $lesson, array $node): array
    {
        $normalized = $this->containerNode($lesson, $node, ['level'], true);
        $level = $normalized['attrs']['level'] ?? null;

        if (! is_int($level) || ! in_array($level, [1, 2, 3], true)) {
            $this->invalid('Lesson headings must use level 1, 2, or 3.');
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function orderedListNode(Lesson $lesson, array $node): array
    {
        $normalized = $this->containerNode($lesson, $node, ['start'], false, ['listItem']);
        $start = $normalized['attrs']['start'] ?? 1;

        if (! is_int($start) || $start < 1 || $start > 9999) {
            $this->invalid('Ordered-list numbering is invalid.');
        }

        $normalized['attrs'] = ['start' => $start];

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function tableCellNode(Lesson $lesson, array $node): array
    {
        $normalized = $this->containerNode($lesson, $node, ['colspan', 'rowspan', 'colwidth'], false);
        $attrs = $normalized['attrs'] ?? [];

        foreach (['colspan', 'rowspan'] as $key) {
            $value = $attrs[$key] ?? 1;

            if (! is_int($value) || $value < 1 || $value > 100) {
                $this->invalid('Table cell spans are invalid.');
            }

            $attrs[$key] = $value;
        }

        $colwidth = $attrs['colwidth'] ?? null;

        if ($colwidth !== null && (! is_array($colwidth) || ! array_is_list($colwidth))) {
            $this->invalid('Table column widths are invalid.');
        }

        if (is_array($colwidth)) {
            foreach ($colwidth as $width) {
                if (! is_int($width) || $width < 1 || $width > 5000) {
                    $this->invalid('Table column widths are invalid.');
                }
            }
        }

        $attrs['colwidth'] = $colwidth;
        $normalized['attrs'] = $attrs;

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function codeBlockNode(array $node): array
    {
        $this->onlyKeys($node, ['type', 'attrs', 'content']);
        $attrs = $this->attributes($node, ['language']);
        $language = $attrs['language'] ?? 'plain';

        if (! is_string($language) || ! in_array($language, self::CODE_LANGUAGES, true)) {
            $this->invalid('The selected code language is not supported.');
        }

        $content = $this->contentList($node);
        $normalizedContent = [];

        foreach ($content as $child) {
            if (! is_array($child) || ($child['type'] ?? null) !== 'text') {
                $this->invalid('Code blocks may contain text only.');
            }

            $normalizedContent[] = $this->textNode($child, false);
        }

        return array_filter([
            'type' => 'codeBlock',
            'attrs' => ['language' => $language],
            'content' => $normalizedContent === [] ? null : $normalizedContent,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function imageNode(Lesson $lesson, array $node): array
    {
        $this->onlyKeys($node, ['type', 'attrs']);
        $attrs = $this->attributes($node, [
            'lessonAssetId', 'altText', 'caption', 'alignment', 'size', 'decorative',
        ]);
        $asset = $this->asset($lesson, $attrs['lessonAssetId'] ?? null, LessonAssetType::Image);
        $altText = $attrs['altText'] ?? $asset->alt_text ?? '';
        $caption = $attrs['caption'] ?? $asset->caption;
        $alignment = $attrs['alignment'] ?? 'center';
        $size = $attrs['size'] ?? 'large';
        $decorative = $attrs['decorative'] ?? false;

        if (! is_bool($decorative)
            || (! $decorative && (! is_string($altText) || trim($altText) === ''))
            || ! is_string($altText)
            || mb_strlen($altText) > 500) {
            $this->invalid('Lesson images require alt text unless marked decorative.');
        }

        if ($caption !== null && (! is_string($caption) || mb_strlen($caption) > 2000)) {
            $this->invalid('The image caption is invalid.');
        }

        if (! in_array($alignment, ['left', 'center', 'right'], true)
            || ! in_array($size, ['small', 'medium', 'large', 'full'], true)) {
            $this->invalid('The image display settings are invalid.');
        }

        return [
            'type' => 'lessonImage',
            'attrs' => [
                'lessonAssetId' => $asset->id,
                'altText' => $decorative ? '' : trim($altText),
                'caption' => $caption,
                'alignment' => $alignment,
                'size' => $size,
                'decorative' => $decorative,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function fileNode(Lesson $lesson, array $node): array
    {
        $this->onlyKeys($node, ['type', 'attrs']);
        $attrs = $this->attributes($node, ['lessonAssetId', 'title', 'caption']);
        $asset = $this->asset($lesson, $attrs['lessonAssetId'] ?? null, LessonAssetType::Document);
        $title = $attrs['title'] ?? $asset->original_name;
        $caption = $attrs['caption'] ?? $asset->caption;

        if (! is_string($title) || trim($title) === '' || mb_strlen($title) > 255) {
            $this->invalid('File resources require a valid title.');
        }

        if ($caption !== null && (! is_string($caption) || mb_strlen($caption) > 2000)) {
            $this->invalid('The file resource caption is invalid.');
        }

        return [
            'type' => 'lessonFile',
            'attrs' => [
                'lessonAssetId' => $asset->id,
                'title' => trim($title),
                'caption' => $caption,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function videoNode(array $node): array
    {
        $this->onlyKeys($node, ['type', 'attrs']);
        $attrs = $this->attributes($node, ['url', 'title', 'caption', 'provider', 'videoId']);
        $url = $attrs['url'] ?? null;

        if (! is_string($url) || ($video = $this->videoEmbed->parse($url)) === null) {
            $this->invalid('Videos must use a supported YouTube or Vimeo URL.');
        }

        $title = $attrs['title'] ?? 'Lesson video';
        $caption = $attrs['caption'] ?? null;

        if (! is_string($title) || trim($title) === '' || mb_strlen($title) > 255) {
            $this->invalid('Lesson videos require a descriptive title.');
        }

        if ($caption !== null && (! is_string($caption) || mb_strlen($caption) > 2000)) {
            $this->invalid('The video caption is invalid.');
        }

        return [
            'type' => 'externalVideo',
            'attrs' => [
                'url' => $url,
                'title' => trim($title),
                'caption' => $caption,
                'provider' => $video['provider'],
                'videoId' => $video['video_id'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function calloutNode(Lesson $lesson, array $node): array
    {
        $normalized = $this->containerNode($lesson, $node, ['type'], true);
        $type = $normalized['attrs']['type'] ?? null;

        if (! is_string($type) || ! in_array($type, self::CALLOUT_TYPES, true)) {
            $this->invalid('The selected callout type is not supported.');
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function textNode(array $node, bool $allowMarks = true): array
    {
        $this->onlyKeys($node, $allowMarks ? ['type', 'text', 'marks'] : ['type', 'text']);

        if (! is_string($node['text'] ?? null)) {
            $this->invalid('Text nodes require string content.');
        }

        $normalized = ['type' => 'text', 'text' => $node['text']];

        if ($allowMarks && isset($node['marks'])) {
            if (! is_array($node['marks']) || ! array_is_list($node['marks'])) {
                $this->invalid('Text formatting marks are invalid.');
            }

            $normalized['marks'] = array_map(fn (mixed $mark): array => $this->mark($mark), $node['marks']);
        }

        return $normalized;
    }

    /** @return array<string, mixed> */
    private function mark(mixed $mark): array
    {
        if (! is_array($mark) || ! is_string($mark['type'] ?? null)) {
            $this->invalid('Text formatting marks are invalid.');
        }

        if (in_array($mark['type'], ['bold', 'italic'], true)) {
            $this->onlyKeys($mark, ['type']);

            return ['type' => $mark['type']];
        }

        if ($mark['type'] !== 'link') {
            $this->invalid("Unsupported text mark [{$mark['type']}].");
        }

        $this->onlyKeys($mark, ['type', 'attrs']);
        $attrs = $this->attributes($mark, ['href', 'target', 'rel', 'class']);
        $href = $attrs['href'] ?? null;

        if (! is_string($href) || ! $this->videoEmbed->isSafeHttpUrl($href)) {
            $this->invalid('Lesson links must use a safe HTTP or HTTPS URL.');
        }

        return [
            'type' => 'link',
            'attrs' => [
                'href' => $href,
                'target' => '_blank',
                'rel' => 'noopener noreferrer',
                'class' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function leafNode(array $node): array
    {
        $this->onlyKeys($node, ['type']);

        return ['type' => $node['type']];
    }

    private function asset(Lesson $lesson, mixed $id, LessonAssetType $type): LessonAsset
    {
        if (! is_int($id)) {
            $this->invalid('Lesson asset references must use an integer ID.');
        }

        $asset = LessonAsset::query()->find($id);

        if (! $asset instanceof LessonAsset) {
            $this->invalid('The referenced lesson asset does not exist.');
        }

        if ($asset->lesson_id !== $lesson->id) {
            $this->invalid('The referenced asset belongs to another lesson.');
        }

        if ($asset->asset_type !== $type) {
            $this->invalid('The referenced lesson asset has the wrong type.');
        }

        return $asset;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $allowed
     * @return array<string, mixed>
     */
    private function attributes(array $node, array $allowed): array
    {
        $attrs = $node['attrs'] ?? [];

        if (! is_array($attrs) || array_is_list($attrs) && $attrs !== []) {
            $this->invalid('Lesson node attributes are invalid.');
        }

        $this->onlyKeys($attrs, $allowed);

        return $attrs;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<int, mixed>
     */
    private function contentList(array $node): array
    {
        $content = $node['content'] ?? [];

        if (! is_array($content) || ! array_is_list($content)) {
            $this->invalid('Lesson node content must be an ordered list.');
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<string>  $allowed
     */
    private function onlyKeys(array $value, array $allowed): void
    {
        $unknown = array_diff(array_keys($value), $allowed);

        if ($unknown !== []) {
            $this->invalid('Unsupported lesson data was supplied: '.implode(', ', $unknown).'.');
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['content_document' => $message]);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  Closure(array<string, mixed>): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function mapNodes(array $node, Closure $callback): array
    {
        $node = $callback($node);

        if (is_array($node['content'] ?? null)) {
            $node['content'] = array_map(
                fn (array $child): array => $this->mapNodes($child, $callback),
                $node['content'],
            );
        }

        return $node;
    }

    /** @param array<string, mixed> $node
     * @param  Closure(array<string, mixed>): void  $callback
     */
    private function walk(array $node, Closure $callback): void
    {
        $callback($node);

        foreach ($node['content'] ?? [] as $child) {
            if (is_array($child)) {
                $this->walk($child, $callback);
            }
        }
    }
}
