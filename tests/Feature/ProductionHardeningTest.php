<?php

namespace Tests\Feature;

use App\Enums\QuestionType;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AssessmentAnswer;
use App\Models\QuestionAcceptedAnswer;
use App\Services\AssessmentAttemptService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsAssessmentAttemptContexts;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use BuildsAssessmentAttemptContexts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_student_and_unassigned_staff_cannot_use_attempt_review_routes(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::Essay]);
        $url = route('admin.class-assessment-attempts.index', [$context['class'], $context['assignment']]);

        foreach ([$context['student'], $this->userWithAssessmentRole('Parent')] as $user) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }

        $unassignedTutor = $this->userWithAssessmentRole('Tutor');
        $this->actingAs($unassignedTutor)
            ->get(route('tutor.class-assessment-attempts.index', [$context['class'], $context['assignment']]))
            ->assertForbidden();

        $this->actingAs($this->userWithAssessmentRole('Admin'))->get($url)->assertOk();
    }

    public function test_private_disk_has_no_generic_file_serving_route(): void
    {
        $this->assertFalse(Route::has('storage.local'));
    }

    public function test_inertia_forbidden_visit_uses_a_navigable_error_page_even_in_testing(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::Essay]);
        $tutor = $this->userWithAssessmentRole('Tutor');
        $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

        $this->actingAs($tutor)
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Version', $version ?? '')
            ->get(route('student.assessment-attempts.result', app(AssessmentAttemptService::class)->startAttempt(
                $context['student'],
                $context['enrollment'],
                $context['assignment'],
            )))
            ->assertForbidden()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Error')
            ->assertJsonPath('props.status', 403);
    }

    public function test_ssr_is_opt_in_until_the_repository_has_a_renderer_bundle(): void
    {
        $this->assertFalse(config('inertia.ssr.enabled'));
        $this->assertFileDoesNotExist(resource_path('js/ssr.ts'));
    }

    public function test_answer_keys_are_hidden_from_generic_model_serialization(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice, QuestionType::ShortAnswer]);
        $multipleChoice = $context['questions'][QuestionType::MultipleChoice->value]->load('options');
        $shortAnswer = $context['questions'][QuestionType::ShortAnswer->value]->load('acceptedAnswers');

        $this->assertArrayNotHasKey('correct_boolean', $multipleChoice->toArray());
        $this->assertArrayNotHasKey('is_correct', $multipleChoice->options->firstOrFail()->toArray());
        $this->assertArrayNotHasKey(
            'answer_text',
            $shortAnswer->acceptedAnswers->firstOrFail()->toArray(),
        );

        $attempt = app(AssessmentAttemptService::class)->startAttempt(
            $context['student'],
            $context['enrollment'],
            $context['assignment'],
        )->load('attemptQuestions.options', 'attemptQuestions.acceptedAnswers');
        $snapshots = $attempt->attemptQuestions->keyBy('question_type.value');
        $choiceSnapshot = $snapshots->get(QuestionType::MultipleChoice->value);
        $shortSnapshot = $snapshots->get(QuestionType::ShortAnswer->value);

        $this->assertArrayNotHasKey('correct_boolean', $choiceSnapshot->toArray());
        $this->assertArrayNotHasKey('explanation', $choiceSnapshot->toArray());
        $this->assertArrayNotHasKey('is_correct', $choiceSnapshot->options->firstOrFail()->toArray());
        $this->assertArrayNotHasKey(
            'answer_text',
            $shortSnapshot->acceptedAnswers->firstOrFail()->toArray(),
        );

        $answer = new AssessmentAnswer(['is_correct' => true, 'auto_score' => '1.00']);
        $this->assertArrayNotHasKey('is_correct', $answer->toArray());
        $this->assertArrayNotHasKey('auto_score', $answer->toArray());
        $this->assertInstanceOf(QuestionAcceptedAnswer::class, $shortAnswer->acceptedAnswers->first());
    }

    public function test_assessment_start_endpoint_is_rate_limited_per_student_and_assignment(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice]);
        $url = route('student.assessments.start', [$context['class'], $context['assignment']]);

        for ($request = 1; $request <= 10; $request++) {
            $this->actingAs($context['student'])->post($url)->assertRedirect();
        }

        $this->actingAs($context['student'])->post($url)->assertTooManyRequests();
    }
}
