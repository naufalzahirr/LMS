<?php

namespace Tests\Feature;

use Tests\TestCase;

class AssessmentFrontendIntegrationTest extends TestCase
{
    public function test_text_answers_snapshot_the_emitted_model_value_before_autosave(): void
    {
        $source = file_get_contents(resource_path('js/pages/Student/Assessments/Attempt.vue'));

        $this->assertIsString($source);
        $this->assertStringContainsString('answers[question.id].answer_text = String(value);', $source);
        $this->assertSame(2, substr_count($source, 'onTextModelUpdate(question, $event)'));
        $this->assertSame(2, substr_count($source, '@blur="saveNow(question)"'));
        $this->assertStringNotContainsString('@input="onTextInput(question)"', $source);
    }

    public function test_grading_header_stays_stacked_through_tablet_width(): void
    {
        $source = file_get_contents(resource_path('js/pages/assessment-attempts/Grade.vue'));

        $this->assertIsString($source);
        $this->assertStringContainsString(
            'flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between',
            $source,
        );
        $this->assertStringContainsString('flex flex-wrap gap-2 lg:shrink-0', $source);
        $this->assertStringNotContainsString(
            'flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between',
            $source,
        );
    }
}
