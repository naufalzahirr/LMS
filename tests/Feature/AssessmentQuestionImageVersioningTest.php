<?php

namespace Tests\Feature;

use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\User;
use App\Services\QuestionAssetService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsAssessmentAttemptContexts;
use Tests\TestCase;

/**
 * The authoring preview addresses a question's image by question id, so the
 * URL alone cannot tell the browser that the binary behind it changed. These
 * cover the version token that makes a replacement a distinct resource.
 */
class AssessmentQuestionImageVersioningTest extends TestCase
{
    use BuildsAssessmentAttemptContexts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_replacing_the_binary_changes_the_preview_url_the_author_receives(): void
    {
        $admin = $this->userWithAssessmentRole('Admin');
        $question = $this->questionWithImage('Gambar A');
        $payload = $this->questionPayload($question);

        $before = $this->previewImage($admin, $question);
        $this->assertNotSame('', $before['url']);
        $this->assertSame('Gambar A', $before['alt_text']);

        $this->actingAs($admin)
            ->put(route('admin.questions.update', $question), [
                ...$payload,
                'image' => UploadedFile::fake()->image('gambar-b.png', 640, 480),
                'image_alt_text' => 'Gambar B',
            ])
            ->assertRedirect(route('admin.questions.show', $question));

        $after = $this->previewImage($admin, $question);

        $this->assertNotSame(
            $before['url'],
            $after['url'],
            'A replaced binary must reach the browser as a different URL, or the old image stays on screen.',
        );
        $this->assertSame('Gambar B', $after['alt_text']);

        // The versioned URL must still serve the new binary under the same
        // private, authorized route.
        $this->actingAs($admin)->get($after['url'])->assertOk();
        $this->assertSame(
            $question->refresh()->image->file_path,
            $this->storedPathFor($question),
            'The served asset row must point at the newly stored file.',
        );
    }

    public function test_the_version_is_stable_while_the_binary_is_untouched(): void
    {
        $admin = $this->userWithAssessmentRole('Admin');
        $question = $this->questionWithImage('Gambar A');
        $payload = $this->questionPayload($question);

        $before = $this->previewImage($admin, $question);

        // Re-reading the page must not churn the URL.
        $this->assertSame($before['url'], $this->previewImage($admin, $question)['url']);

        // Editing only the prompt must not churn it either.
        $this->actingAs($admin)
            ->put(route('admin.questions.update', $question), [
                ...$payload,
                'prompt' => 'Ada berapa apel sekarang?',
                'image_alt_text' => 'Gambar A',
            ])
            ->assertRedirect();

        $afterPromptEdit = $this->previewImage($admin, $question);
        $this->assertSame(
            $before['url'],
            $afterPromptEdit['url'],
            'An unchanged binary must keep its URL so the browser reuses the image it already has.',
        );
        $this->assertSame('Ada berapa apel sekarang?', $question->refresh()->prompt);

        // Correcting alt text alone does not change the binary, so the URL
        // stays put — the new alt arrives through the Inertia payload.
        $this->actingAs($admin)
            ->put(route('admin.questions.update', $question), [
                ...$payload,
                'image_alt_text' => 'Gambar A yang diperbaiki',
            ])
            ->assertRedirect();

        $afterAltEdit = $this->previewImage($admin, $question);
        $this->assertSame($before['url'], $afterAltEdit['url']);
        $this->assertSame('Gambar A yang diperbaiki', $afterAltEdit['alt_text']);
    }

    public function test_removing_the_image_clears_the_versioned_payload(): void
    {
        $admin = $this->userWithAssessmentRole('Admin');
        $question = $this->questionWithImage('Gambar A');

        $this->actingAs($admin)
            ->put(route('admin.questions.update', $question), [
                ...$this->questionPayload($question),
                'remove_image' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.questions.show', $question))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('question.image', null));

        $this->assertNull($question->refresh()->image);
    }

    public function test_the_assessment_authoring_list_serves_the_same_versioned_url(): void
    {
        $admin = $this->userWithAssessmentRole('Admin');
        $question = $this->questionWithImage('Gambar A');
        $assessment = Assessment::factory()->for($question->competency)->create();
        $assessment->assessmentQuestions()->create([
            'question_id' => $question->id,
            'points' => '1.00',
            'sort_order' => 0,
        ]);

        $listed = $this->actingAs($admin)
            ->get(route('admin.assessments.show', $assessment))
            ->assertOk()
            ->viewData('page')['props']['questions'][0]['image'];

        $this->assertSame($this->previewImage($admin, $question)['url'], $listed['url']);
    }

    /** @return array{url: string, alt_text: string} */
    private function previewImage(User $admin, Question $question): array
    {
        $image = $this->actingAs($admin)
            ->get(route('admin.questions.show', $question))
            ->assertOk()
            ->viewData('page')['props']['question']['image'];

        return ['url' => $image['url'], 'alt_text' => $image['alt_text']];
    }

    private function storedPathFor(Question $question): string
    {
        return $question->refresh()->image->file_path;
    }

    private function questionWithImage(string $altText): Question
    {
        $course = Course::factory()->create();
        $competency = Competency::factory()->for($course)->create();
        $bank = QuestionBank::factory()->for($course)->create();
        $question = Question::factory()->for($bank)->for($competency)->multipleChoice()->create();
        app(QuestionAssetService::class)->replace(
            $question,
            UploadedFile::fake()->image('gambar-a.png', 640, 480),
            $altText,
        );

        return $question->refresh();
    }

    /** @return array<string, mixed> */
    private function questionPayload(Question $question): array
    {
        return [
            'question_bank_id' => $question->question_bank_id,
            'competency_id' => $question->competency_id,
            'question_type' => QuestionType::MultipleChoice->value,
            'prompt' => $question->prompt,
            'default_points' => '1.00',
            'status' => 'active',
            'sort_order' => 0,
            'options' => [
                ['option_text' => '5', 'is_correct' => true, 'sort_order' => 0],
                ['option_text' => '4', 'is_correct' => false, 'sort_order' => 1],
            ],
        ];
    }
}
