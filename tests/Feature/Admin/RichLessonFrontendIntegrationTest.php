<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class RichLessonFrontendIntegrationTest extends TestCase
{
    public function test_authoring_pages_forward_draft_upload_and_preview_configuration(): void
    {
        $create = $this->source('resources/js/pages/admin/lessons/Create.vue');
        $edit = $this->source('resources/js/pages/admin/lessons/Edit.vue');
        $fields = $this->source('resources/js/components/admin/LessonFormFields.vue');

        $this->assertStringContainsString(':draft-ensure-url="draftEnsureUrl"', $create);
        $this->assertStringContainsString(':preview-url="previewUrl"', $edit);
        $this->assertStringContainsString(':ensure-asset-upload-url=', $fields);
        $this->assertStringContainsString('ensureDraftForCurrentModule', $fields);
        $this->assertStringContainsString('name="draft_id"', $fields);
        $this->assertStringContainsString('<LessonContentRenderer :document="previewDocument"', $fields);
    }

    public function test_rich_editor_uses_application_dialogs_without_browser_prompts(): void
    {
        $editor = $this->source('resources/js/components/lesson/RichLessonEditor.vue');

        foreach (['LessonLinkDialog', 'LessonVideoDialog', 'LessonImageDialog', 'LessonResourceDialog'] as $dialog) {
            $this->assertStringContainsString("import {$dialog}", $editor);
            $this->assertStringContainsString("<{$dialog}", $editor);
        }

        $this->assertStringNotContainsString('window.prompt', $editor);
        $this->assertStringNotContainsString('window.alert', $editor);
        $this->assertDoesNotMatchRegularExpression('/\bprompt\s*\(/', $editor);
    }

    public function test_rich_editor_preserves_selections_and_owns_link_and_video_validation(): void
    {
        $editor = $this->source('resources/js/components/lesson/RichLessonEditor.vue');
        $link = $this->source('resources/js/components/lesson/dialogs/LessonLinkDialog.vue');
        $video = $this->source('resources/js/components/lesson/dialogs/LessonVideoDialog.vue');
        $contentTypes = $this->source('resources/js/types/lesson-content.ts');
        $styles = $this->source('resources/css/app.css');

        foreach (['linkSelection', 'getMarkRange', 'preserveToolbarSelection', 'restoreNodeSelection', 'videoInsertionPosition', 'imageInsertionPosition', 'resourceInsertionPosition', 'currentBlockInsertionPosition', 'insertBlockAt'] as $selectionGuard) {
            $this->assertStringContainsString($selectionGuard, $editor);
        }

        foreach (['handleDOMEvents', 'syncTextSelectionAfterAtomClick', 'TextSelection.near', 'view.posAtCoords', 'view.posAtDOM'] as $textSelectionGuard) {
            $this->assertStringContainsString($textSelectionGuard, $editor);
        }

        $this->assertStringContainsString('if (!isNodeSelection(view.state.selection))', $editor);
        $this->assertStringContainsString('view.state.tr.setSelection(selection)', $editor);
        $this->assertStringContainsString('if (!selection.$from.parent.inlineContent)', $editor);

        $this->assertStringContainsString('placeholder.length', $editor);
        $this->assertStringContainsString('novalidate', $link);
        $this->assertStringContainsString('type="text"', $link);
        $this->assertStringContainsString('validateLessonLinkUrl', $link);
        $this->assertStringContainsString('novalidate', $video);
        $this->assertStringContainsString('validateLessonVideoUrl', $video);
        $this->assertStringContainsString('max-h-[calc(100dvh-2rem)]', $video);
        $this->assertStringContainsString('overflow-y-auto', $video);
        $this->assertStringContainsString('shrink-0 border-t', $video);
        $this->assertStringContainsString("key !== 'url' || node.type === 'externalVideo'", $contentTypes);
        $this->assertStringContainsString("['tableCell', 'tableHeader'].includes(node.type)", $contentTypes);
        $this->assertStringContainsString('.rich-lesson-editor .tableWrapper', $styles);
        $this->assertStringContainsString('max-w-full overflow-x-auto', $styles);
    }

    private function source(string $path): string
    {
        $contents = file_get_contents(base_path($path));
        $this->assertIsString($contents);

        return $contents;
    }
}
