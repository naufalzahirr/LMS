<?php

namespace Tests\Feature\Admin;

use App\Enums\AcademicStatus;
use App\Enums\AssessmentPurpose;
use App\Enums\AssessmentStatus;
use App\Enums\ClassAssessmentStatus;
use App\Enums\LearningClassStatus;
use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\Competency;
use App\Models\Course;
use App\Models\LearningClass;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssessmentAuthoringManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_author_compose_and_publish_assessment(): void
    {
        $admin = $this->userWithRole('Admin');
        $course = Course::factory()->create();
        $competency = Competency::factory()->for($course)->create();

        $this->actingAs($admin)->post(route('admin.question-banks.store'), $this->bankPayload($course))
            ->assertRedirect(route('admin.question-banks.index'));
        $bank = QuestionBank::query()->where('code', 'CORE-BANK')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.questions.store'), $this->questionPayload($bank, $competency))
            ->assertRedirect();
        $question = Question::query()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.assessments.store'), $this->assessmentPayload($competency))
            ->assertRedirect();
        $assessment = Assessment::query()->firstOrFail();
        $this->assertSame(AssessmentStatus::Draft, $assessment->status);

        $this->actingAs($admin)->post(route('admin.assessments.questions.store', $assessment), [
            'question_id' => $question->id,
            'points' => '2.50',
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.assessments.publish', $assessment))
            ->assertRedirect();

        $this->assertSame(AssessmentStatus::Published, $assessment->refresh()->status);
        $this->assertDatabaseHas('assessment_questions', [
            'assessment_id' => $assessment->id,
            'question_id' => $question->id,
            'points' => '2.50',
        ]);
    }

    public function test_tutor_authoring_is_scoped_to_courses_with_active_teaching_classes(): void
    {
        $tutor = $this->userWithRole('Tutor');
        $assignedCourse = Course::factory()->create();
        $assignedClass = LearningClass::factory()->for($assignedCourse)->create([
            'status' => LearningClassStatus::Active,
        ]);
        $assignedClass->tutors()->attach($tutor);
        $assignedCompetency = Competency::factory()->for($assignedCourse)->create();
        $otherCourse = Course::factory()->create();
        $otherCompetency = Competency::factory()->for($otherCourse)->create();

        $this->actingAs($tutor)->get(route('admin.question-banks.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('courses', 1)
                ->where('courses.0.id', $assignedCourse->id));

        $this->actingAs($tutor)->post(route('admin.question-banks.store'), $this->bankPayload($assignedCourse))
            ->assertRedirect();
        $bank = QuestionBank::query()->firstOrFail();

        $this->actingAs($tutor)->post(route('admin.questions.store'), $this->questionPayload($bank, $assignedCompetency))
            ->assertRedirect();
        $this->actingAs($tutor)->post(route('admin.assessments.store'), $this->assessmentPayload($assignedCompetency))
            ->assertRedirect();

        $this->actingAs($tutor)->post(route('admin.question-banks.store'), $this->bankPayload($otherCourse, ['code' => 'OTHER']))
            ->assertForbidden();
        $this->actingAs($tutor)->post(route('admin.assessments.store'), $this->assessmentPayload($otherCompetency, ['code' => 'OTHER-ASM']))
            ->assertForbidden();
    }

    public function test_student_and_parent_cannot_access_authoring_routes(): void
    {
        foreach (['Student', 'Parent'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.question-banks.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.questions.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.assessments.index'))->assertForbidden();
        }
    }

    public function test_admin_controls_class_assignment_and_tutor_has_read_only_visibility(): void
    {
        $admin = $this->userWithRole('Admin');
        $tutor = $this->userWithRole('Tutor');
        $course = Course::factory()->create();
        $learningClass = LearningClass::factory()->for($course)->create();
        $learningClass->tutors()->attach($tutor);
        $assessment = Assessment::factory()
            ->for(Competency::factory()->for($course))
            ->published()
            ->create();

        $payload = [
            'assessment_id' => $assessment->id,
            'opens_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'closes_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            'max_attempts' => 2,
            'status' => ClassAssessmentStatus::Active->value,
        ];

        $this->actingAs($admin)->post(route('admin.classes.assessments.store', $learningClass), $payload)
            ->assertRedirect();
        $assignment = $learningClass->assessmentAssignments()->firstOrFail();

        $this->actingAs($tutor)->get(route('tutor.classes.show', $learningClass))
            ->assertInertia(fn (Assert $page) => $page
                ->has('assessmentAssignments', 1)
                ->where('assessmentAssignments.0.id', $assignment->id)
                ->where('assessmentAssignments.0.title', $assessment->title));

        $this->actingAs($tutor)->patch(route('admin.classes.assessments.update', [$learningClass, $assignment]), [
            'opens_at' => null,
            'closes_at' => null,
            'max_attempts' => 3,
            'status' => ClassAssessmentStatus::Inactive->value,
        ])->assertForbidden();
        $this->actingAs($tutor)->delete(route('admin.classes.assessments.destroy', [$learningClass, $assignment]))
            ->assertForbidden();
    }

    public function test_class_assignment_rejects_draft_and_cross_course_assessments(): void
    {
        $admin = $this->userWithRole('Admin');
        $learningClass = LearningClass::factory()->create();
        $draft = Assessment::factory()
            ->for(Competency::factory()->for($learningClass->course))
            ->create();
        $other = Assessment::factory()->published()->create();

        foreach ([$draft, $other] as $assessment) {
            $this->actingAs($admin)->post(route('admin.classes.assessments.store', $learningClass), [
                'assessment_id' => $assessment->id,
                'opens_at' => null,
                'closes_at' => null,
                'max_attempts' => 1,
                'status' => ClassAssessmentStatus::Active->value,
            ])->assertSessionHasErrors('assessment_id');
        }

        $this->assertDatabaseCount('learning_class_assessments', 0);
    }

    public function test_assessment_composition_props_do_not_expose_normalized_answer_keys(): void
    {
        $admin = $this->userWithRole('Admin');
        $course = Course::factory()->create();
        $competency = Competency::factory()->for($course)->create();
        $bank = QuestionBank::factory()->for($course)->create();
        $question = Question::factory()->for($bank)->for($competency)->multipleChoice()->create();
        $assessment = Assessment::factory()->for($competency)->create();
        $assessment->assessmentQuestions()->create([
            'question_id' => $question->id,
            'points' => '1.00',
            'sort_order' => 0,
        ]);

        $this->actingAs($admin)->get(route('admin.assessments.show', $assessment))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/assessments/Show')
                ->has('questions', 1)
                ->missing('questions.0.options')
                ->missing('questions.0.accepted_answers')
                ->missing('questions.0.correct_boolean'));
    }

    /** @param  array<string, mixed>  $overrides */
    private function bankPayload(Course $course, array $overrides = []): array
    {
        return array_merge([
            'course_id' => $course->id,
            'name' => 'Core Bank',
            'code' => 'CORE-BANK',
            'description' => 'Reusable questions.',
            'status' => AcademicStatus::Active->value,
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function questionPayload(QuestionBank $bank, Competency $competency): array
    {
        return [
            'question_bank_id' => $bank->id,
            'competency_id' => $competency->id,
            'question_type' => QuestionType::MultipleChoice->value,
            'prompt' => 'Which answer is correct?',
            'explanation' => 'The first answer is correct.',
            'default_points' => '1.00',
            'status' => AcademicStatus::Active->value,
            'sort_order' => 0,
            'options' => [
                ['option_text' => 'Correct', 'is_correct' => true, 'sort_order' => 0],
                ['option_text' => 'Incorrect', 'is_correct' => false, 'sort_order' => 1],
            ],
            'accepted_answers' => [],
        ];
    }

    /** @param  array<string, mixed>  $overrides */
    private function assessmentPayload(Competency $competency, array $overrides = []): array
    {
        return array_merge([
            'competency_id' => $competency->id,
            'title' => 'Core Checkpoint',
            'code' => 'CORE-ASM',
            'description' => 'A formative checkpoint.',
            'purpose' => AssessmentPurpose::Formative->value,
            'instructions' => 'Answer every question.',
            'shuffle_questions' => false,
        ], $overrides);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
