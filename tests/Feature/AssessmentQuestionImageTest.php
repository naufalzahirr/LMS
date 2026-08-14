<?php

namespace Tests\Feature;

use App\Enums\AssessmentAttemptStatus;
use App\Enums\AssessmentFeedbackMode;
use App\Enums\QuestionType;
use App\Models\AssessmentAttempt;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Question;
use App\Models\QuestionAsset;
use App\Models\QuestionBank;
use App\Services\AssessmentAttemptService;
use App\Services\QuestionAssetService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsAssessmentAttemptContexts;
use Tests\TestCase;

class AssessmentQuestionImageTest extends TestCase
{
    use BuildsAssessmentAttemptContexts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_a_question_without_an_image_keeps_working_end_to_end(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice]);
        $attempt = $this->startAttempt($context);
        $snapshot = $attempt->attemptQuestions()->firstOrFail();

        $this->assertNull($snapshot->question_asset_id);
        $this->assertDatabaseCount('question_assets', 0);

        $this->actingAs($context['student'])
            ->get(route('student.assessment-attempts.show', $attempt))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('attempt.questions.0.image', null));

        // A question that never had an image has nothing to serve.
        $this->actingAs($context['student'])
            ->get(route('student.attempt-question-images.show', [$attempt, $snapshot]))
            ->assertNotFound();
    }

    public function test_admin_creates_a_question_with_a_private_image_and_cannot_supply_a_path(): void
    {
        $admin = $this->userWithAssessmentRole('Admin');
        $course = Course::factory()->create();
        $competency = Competency::factory()->for($course)->create();
        $bank = QuestionBank::factory()->for($course)->create();

        $this->actingAs($admin)
            ->post(route('admin.questions.store'), [
                ...$this->questionPayload($bank->id, $competency->id),
                'image' => UploadedFile::fake()->image('lima-apel.png', 800, 600),
                'image_alt_text' => 'Lima apel merah berjajar',
            ])
            ->assertRedirect();

        $question = Question::query()->firstOrFail();
        $asset = $question->image;
        $this->assertInstanceOf(QuestionAsset::class, $asset);
        $this->assertSame('Lima apel merah berjajar', $asset->alt_text);
        $this->assertSame("question-assets/{$question->id}", dirname($asset->file_path));
        Storage::disk('local')->assertExists($asset->managedFilePath());

        // The stored path is server-derived and never echoed to the client.
        $this->actingAs($admin)
            ->get(route('admin.questions.show', $question))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('question.image.alt_text', 'Lima apel merah berjajar')
                ->where('question.image.url', $asset->authoringUrl())
                ->missing('question.image.file_path'));
    }

    public function test_upload_validation_rejects_a_disallowed_mime_and_a_missing_alt_text(): void
    {
        $admin = $this->userWithAssessmentRole('Admin');
        $course = Course::factory()->create();
        $competency = Competency::factory()->for($course)->create();
        $bank = QuestionBank::factory()->for($course)->create();
        $payload = $this->questionPayload($bank->id, $competency->id);

        $this->actingAs($admin)
            ->post(route('admin.questions.store'), [
                ...$payload,
                'image' => UploadedFile::fake()->create('malicious.svg', 8, 'image/svg+xml'),
                'image_alt_text' => 'Vector payload',
            ])
            ->assertSessionHasErrors('image');

        $this->actingAs($admin)
            ->post(route('admin.questions.store'), [
                ...$payload,
                'image' => UploadedFile::fake()->create('notes.pdf', 8, 'application/pdf'),
                'image_alt_text' => 'A PDF',
            ])
            ->assertSessionHasErrors('image');

        $this->actingAs($admin)
            ->post(route('admin.questions.store'), [
                ...$payload,
                'image' => UploadedFile::fake()->image('apel.png', 400, 300),
            ])
            ->assertSessionHasErrors('image_alt_text');

        $this->assertDatabaseCount('question_assets', 0);
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_editing_a_question_without_touching_the_file_input_keeps_its_image(): void
    {
        $admin = $this->userWithAssessmentRole('Admin');
        $question = $this->questionWithImage();
        $assetId = $question->image->id;
        $path = $question->image->managedFilePath();

        $this->actingAs($admin)
            ->put(route('admin.questions.update', $question), [
                ...$this->questionPayload($question->question_bank_id, $question->competency_id),
                'prompt' => 'Ada berapa apel sekarang?',
            ])
            ->assertRedirect(route('admin.questions.show', $question));

        $question->refresh();
        $this->assertSame('Ada berapa apel sekarang?', $question->prompt);
        $this->assertSame($assetId, $question->image?->id);
        Storage::disk('local')->assertExists($path);
    }

    public function test_an_author_can_replace_and_then_remove_the_image_through_the_question_form(): void
    {
        $admin = $this->userWithAssessmentRole('Admin');
        $question = $this->questionWithImage();
        $originalPath = $question->image->managedFilePath();
        $payload = $this->questionPayload($question->question_bank_id, $question->competency_id);

        $this->actingAs($admin)
            ->put(route('admin.questions.update', $question), [
                ...$payload,
                'image' => UploadedFile::fake()->image('enam-apel.png', 800, 600),
                'image_alt_text' => 'Enam apel merah berjajar',
            ])
            ->assertRedirect();

        $replacedPath = $question->refresh()->image->managedFilePath();
        $this->assertSame('Enam apel merah berjajar', $question->image->alt_text);
        $this->assertNotSame($originalPath, $replacedPath);
        Storage::disk('local')->assertMissing($originalPath);
        Storage::disk('local')->assertExists($replacedPath);
        $this->assertDatabaseCount('question_assets', 1);

        // Alt text alone may be corrected without re-uploading the file.
        $this->actingAs($admin)
            ->put(route('admin.questions.update', $question), [
                ...$payload,
                'image_alt_text' => 'Enam apel merah dalam satu baris',
            ])
            ->assertRedirect();
        $this->assertSame('Enam apel merah dalam satu baris', $question->refresh()->image->alt_text);
        $this->assertSame($replacedPath, $question->image->managedFilePath());

        // Removal wins over anything else submitted in the same request.
        $this->actingAs($admin)
            ->put(route('admin.questions.update', $question), [
                ...$payload,
                'remove_image' => '1',
                'image' => UploadedFile::fake()->image('diabaikan.png', 400, 300),
                'image_alt_text' => 'Diabaikan',
            ])
            ->assertRedirect();

        $this->assertNull($question->refresh()->image);
        $this->assertDatabaseCount('question_assets', 0);
        Storage::disk('local')->assertMissing($replacedPath);
    }

    public function test_replacing_and_removing_an_image_leaves_no_broken_reference_or_orphan_file(): void
    {
        $admin = $this->userWithAssessmentRole('Admin');
        $question = $this->questionWithImage();
        $original = $question->image;
        $originalPath = $original->managedFilePath();

        app(QuestionAssetService::class)->replace(
            $question,
            UploadedFile::fake()->image('enam-apel.png', 800, 600),
            'Enam apel merah berjajar',
        );

        $replaced = $question->refresh()->image;
        $this->assertSame($original->id, $replaced->id, 'The single-image row is updated, not duplicated.');
        $this->assertNotSame($originalPath, $replaced->managedFilePath());
        $this->assertSame('Enam apel merah berjajar', $replaced->alt_text);
        Storage::disk('local')->assertMissing($originalPath);
        Storage::disk('local')->assertExists($replaced->managedFilePath());
        $this->assertDatabaseCount('question_assets', 1);

        $currentPath = $replaced->managedFilePath();
        app(QuestionAssetService::class)->delete($question->refresh());

        $this->assertNull($question->refresh()->image);
        $this->assertDatabaseCount('question_assets', 0);
        Storage::disk('local')->assertMissing($currentPath);

        $this->actingAs($admin)
            ->get(route('admin.questions.image', $question))
            ->assertNotFound();
    }

    public function test_the_attempt_snapshots_the_image_and_the_student_may_fetch_it_privately(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice]);
        $question = $context['questions'][QuestionType::MultipleChoice->value];
        $asset = $this->attachImage($question);
        $attempt = $this->startAttempt($context);
        $snapshot = $attempt->attemptQuestions()->firstOrFail();

        $this->assertSame($asset->id, $snapshot->question_asset_id);

        $this->actingAs($context['student'])
            ->get(route('student.assessment-attempts.show', $attempt))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('attempt.questions.0.image.alt_text', 'Lima apel merah berjajar')
                ->where('attempt.questions.0.image.url', route('student.attempt-question-images.show', [$attempt, $snapshot])));

        $response = $this->actingAs($context['student'])
            ->get(route('student.attempt-question-images.show', [$attempt, $snapshot]))
            ->assertOk();

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame("default-src 'none'; sandbox", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_question_image_delivery_is_closed_to_everyone_outside_the_attempt(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice]);
        $question = $context['questions'][QuestionType::MultipleChoice->value];
        $this->attachImage($question);
        $attempt = $this->startAttempt($context);
        $snapshot = $attempt->attemptQuestions()->firstOrFail();
        $studentUrl = route('student.attempt-question-images.show', [$attempt, $snapshot]);
        $graderUrl = route('admin.attempt-question-images.show', [$attempt, $snapshot]);

        // Logged out.
        $this->get($studentUrl)->assertRedirect(route('login'));
        $this->get($graderUrl)->assertRedirect(route('login'));
        $this->get(route('admin.questions.image', $question))->assertRedirect(route('login'));

        // A Student enrolled elsewhere, and a Student with no enrollment.
        $otherContext = $this->makeAssessmentContext([QuestionType::MultipleChoice]);
        $this->actingAs($otherContext['student'])->get($studentUrl)->assertForbidden();
        $this->actingAs($this->userWithAssessmentRole('Student'))->get($studentUrl)->assertForbidden();

        // The owning Student may not borrow the grading-scoped route.
        $this->actingAs($context['student'])->get($graderUrl)->assertForbidden();
        // …nor reach the authoring route for the source question.
        $this->actingAs($context['student'])
            ->get(route('admin.questions.image', $question))
            ->assertForbidden();

        // A Tutor who does not teach this class is refused on every surface.
        $foreignTutor = $this->userWithAssessmentRole('Tutor');
        $this->actingAs($foreignTutor)->get($graderUrl)->assertForbidden();
        $this->actingAs($foreignTutor)->get($studentUrl)->assertForbidden();
        $this->actingAs($foreignTutor)->get(route('admin.questions.image', $question))->assertForbidden();

        // The Tutor assigned to the attempt's own class may grade, and see it.
        $assignedTutor = $this->userWithAssessmentRole('Tutor');
        $context['class']->tutors()->attach($assignedTutor);
        $this->actingAs($assignedTutor)->get($graderUrl)->assertOk();

        // An attempt question belonging to a different attempt is never served
        // through this attempt, even for an authorized grader.
        $foreignSnapshot = $this->startAttempt($otherContext)->attemptQuestions()->firstOrFail();
        $this->actingAs($assignedTutor)
            ->get(route('admin.attempt-question-images.show', [$attempt, $foreignSnapshot]))
            ->assertNotFound();
    }

    public function test_the_image_never_participates_in_answering_submission_or_grading(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice]);
        $question = $context['questions'][QuestionType::MultipleChoice->value];
        $this->attachImage($question);
        $attempt = $this->startAttempt($context);
        $snapshot = $attempt->attemptQuestions()->with('options')->firstOrFail();
        $correctOptionId = $snapshot->options->firstWhere('is_correct', true)->id;

        $this->actingAs($context['student'])
            ->patchJson(route('student.assessment-answers.update', [$attempt, $snapshot]), [
                'selected_option_ids' => [$correctOptionId],
            ])
            ->assertNoContent();

        $this->actingAs($context['student'])
            ->post(route('student.assessment-attempts.submit', $attempt))
            ->assertRedirect(route('student.assessment-attempts.result', $attempt));

        $attempt->refresh();
        $this->assertSame(AssessmentAttemptStatus::Graded, $attempt->status);
        $this->assertSame('2.00', $attempt->earned_points);
        $this->assertSame('2.00', $attempt->max_points);
        $this->assertTrue($snapshot->refresh()->answer->is_correct);
    }

    public function test_the_result_page_shows_the_same_snapshotted_image(): void
    {
        $context = $this->makeAssessmentContext(
            [QuestionType::MultipleChoice],
            ['feedback_mode' => AssessmentFeedbackMode::AfterEachAttempt],
        );
        $question = $context['questions'][QuestionType::MultipleChoice->value];
        $this->attachImage($question);
        $attempt = $this->startAttempt($context);
        $snapshot = $attempt->attemptQuestions()->firstOrFail();

        $this->actingAs($context['student'])
            ->post(route('student.assessment-attempts.submit', $attempt))
            ->assertRedirect();

        $this->actingAs($context['student'])
            ->get(route('student.assessment-attempts.result', $attempt))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.detailed_feedback', true)
                ->where('result.questions.0.image.alt_text', 'Lima apel merah berjajar')
                ->where('result.questions.0.image.url', route('student.attempt-question-images.show', [$attempt, $snapshot])));
    }

    public function test_an_image_cannot_change_once_an_attempt_has_snapshotted_it(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice]);
        $question = $context['questions'][QuestionType::MultipleChoice->value];
        $asset = $this->attachImage($question);
        $this->startAttempt($context);
        $question->refresh();

        foreach ([
            fn () => app(QuestionAssetService::class)->replace(
                $question,
                UploadedFile::fake()->image('lain.png', 400, 300),
                'Gambar lain',
            ),
            fn () => app(QuestionAssetService::class)->delete($question),
            fn () => app(QuestionAssetService::class)->updateAltText($question, 'Teks lain'),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('An attempted question must reject image changes.');
            } catch (ValidationException) {
                // Expected: matches how QuestionService already guards edits.
            }
        }

        $this->assertSame($asset->file_path, $question->refresh()->image->file_path);
        $this->assertSame('Lima apel merah berjajar', $question->image->alt_text);
        Storage::disk('local')->assertExists($asset->managedFilePath());
    }

    public function test_a_tampered_stored_path_is_never_read_from_disk(): void
    {
        $admin = $this->userWithAssessmentRole('Admin');
        $question = $this->questionWithImage();
        $question->image->forceFill([
            'file_path' => "question-assets/{$question->id}/../../../.env",
        ])->save();

        $this->actingAs($admin)
            ->get(route('admin.questions.image', $question->refresh()))
            ->assertNotFound();
    }

    /** @param array<string, mixed> $context */
    private function startAttempt(array $context): AssessmentAttempt
    {
        return app(AssessmentAttemptService::class)->startAttempt(
            $context['student'],
            $context['enrollment'],
            $context['assignment'],
        );
    }

    private function questionWithImage(): Question
    {
        $course = Course::factory()->create();
        $competency = Competency::factory()->for($course)->create();
        $bank = QuestionBank::factory()->for($course)->create();
        $question = Question::factory()->for($bank)->for($competency)->multipleChoice()->create();
        $this->attachImage($question);

        return $question->refresh();
    }

    private function attachImage(Question $question): QuestionAsset
    {
        return app(QuestionAssetService::class)->replace(
            $question,
            UploadedFile::fake()->image('lima-apel.png', 800, 600),
            'Lima apel merah berjajar',
        );
    }

    /** @return array<string, mixed> */
    private function questionPayload(int $bankId, int $competencyId): array
    {
        return [
            'question_bank_id' => $bankId,
            'competency_id' => $competencyId,
            'question_type' => QuestionType::MultipleChoice->value,
            'prompt' => 'Ada berapa apel pada gambar?',
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
