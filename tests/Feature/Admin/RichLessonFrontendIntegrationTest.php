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

    private function source(string $path): string
    {
        $contents = file_get_contents(base_path($path));
        $this->assertIsString($contents);

        return $contents;
    }
}
