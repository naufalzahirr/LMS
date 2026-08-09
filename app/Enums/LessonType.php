<?php

namespace App\Enums;

enum LessonType: string
{
    case Text = 'text';
    case Video = 'video';
    case Document = 'document';
    case Image = 'image';
    case Link = 'link';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Video => 'Video',
            self::Document => 'Document',
            self::Image => 'Image',
            self::Link => 'Link',
        };
    }

    public function usesUploadedFile(): bool
    {
        return in_array($this, [self::Document, self::Image], true);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            self::cases(),
        );
    }
}
