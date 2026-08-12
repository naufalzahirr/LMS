<?php

namespace Tests\Feature\Student;

use App\Enums\AcademicStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Enums\LessonProgressStatus;
use App\Models\AssessmentAttempt;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\MasteryRule;
use App\Models\Module;
use App\Models\RemedialAssignment;
use App\Models\StudentCompetencyProgress;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_continue_learning_caps_at_three_and_links_to_the_next_lesson(): void
    {
        $student = $this->userWithRole('Student');
        $targetContext = $this->context($student, enrolledAt: now()->subDay());

        for ($i = 0; $i < 3; $i++) {
            $this->context($student, enrolledAt: now()->subDays(2 + $i));
        }

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/Dashboard')
                ->has('dashboard.continue_learning', 3)
                ->where(
                    'dashboard.continue_learning.0.continue_url',
                    route('student.lessons.show', [$targetContext['learningClass'], $targetContext['lessonOne']]),
                ));
    }

    public function test_progress_summary_sums_across_all_active_enrollments_not_just_the_visible_three(): void
    {
        $student = $this->userWithRole('Student');

        for ($i = 0; $i < 4; $i++) {
            $context = $this->context($student, enrolledAt: now()->subDays($i));
            LessonProgress::factory()->for($context['enrollment'])->for($context['lessonOne'])->create([
                'status' => LessonProgressStatus::Completed,
            ]);
        }

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/Dashboard')
                ->has('dashboard.continue_learning', 3)
                ->where('dashboard.progress.completed_lessons', 4)
                ->where('dashboard.progress.total_lessons', 8));
    }

    public function test_needs_attention_lists_assigned_remedial_with_a_working_link(): void
    {
        $student = $this->userWithRole('Student');
        $context = $this->context($student);
        $rule = MasteryRule::factory()->create([
            'learning_class_id' => $context['learningClass']->id,
            'competency_id' => $context['competency']->id,
        ]);
        $remedial = RemedialAssignment::factory()->create([
            'mastery_rule_id' => $rule->id,
            'enrollment_id' => $context['enrollment']->id,
            'competency_id' => $context['competency']->id,
        ]);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/Dashboard')
                ->has('dashboard.needs_attention.remedial', 1)
                ->where('dashboard.needs_attention.remedial.0.competency_name', $context['competency']->name)
                ->where('dashboard.needs_attention.remedial.0.remedial_url', route('student.remedials.show', $remedial)));
    }

    public function test_available_assessment_appears_in_needs_attention_and_assessments_section_without_leaking_answer_keys(): void
    {
        $student = $this->userWithRole('Student');
        $context = $this->context($student);
        $assignment = LearningClassAssessment::factory()->create([
            'learning_class_id' => $context['learningClass']->id,
        ]);

        $response = $this->actingAs($student)->get(route('dashboard'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/Dashboard')
                ->where('dashboard.needs_attention.assessments_available.count', 1)
                ->has('dashboard.assessments', 1)
                ->where('dashboard.assessments.0.id', $assignment->id)
                ->where('dashboard.assessments.0.availability', 'Available')
                ->missing('dashboard.assessments.0.correct_option_ids')
                ->missing('dashboard.assessments.0.accepted_answers')
                ->missing('dashboard.assessments.0.answer_key'));

        $response->assertDontSee('accepted_answers')
            ->assertDontSee('correct_option_ids')
            ->assertDontSee('correct_boolean');
    }

    public function test_not_started_assessment_offers_a_start_action(): void
    {
        $student = $this->userWithRole('Student');
        $context = $this->context($student);
        $assignment = LearningClassAssessment::factory()->create([
            'learning_class_id' => $context['learningClass']->id,
            'max_attempts' => 2,
        ]);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/Dashboard')
                ->where('dashboard.assessments.0.availability', 'Available')
                ->where('dashboard.assessments.0.action.label', 'Start Assessment')
                ->where('dashboard.assessments.0.action.method', 'post')
                ->where(
                    'dashboard.assessments.0.action.url',
                    route('student.assessments.start', [$context['learningClass'], $assignment]),
                ));
    }

    public function test_in_progress_assessment_offers_a_continue_action(): void
    {
        $student = $this->userWithRole('Student');
        $context = $this->context($student);
        $assignment = LearningClassAssessment::factory()->create([
            'learning_class_id' => $context['learningClass']->id,
            'max_attempts' => 2,
        ]);
        $attempt = AssessmentAttempt::factory()->inProgress()->create([
            'learning_class_assessment_id' => $assignment->id,
            'enrollment_id' => $context['enrollment']->id,
        ]);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/Dashboard')
                ->where('dashboard.assessments.0.availability', 'In Progress')
                ->where('dashboard.assessments.0.action.label', 'Continue Assessment')
                ->where('dashboard.assessments.0.action.method', 'get')
                ->where('dashboard.assessments.0.action.url', route('student.assessment-attempts.show', $attempt)));
    }

    public function test_pending_grading_assessment_does_not_offer_a_start_action(): void
    {
        $student = $this->userWithRole('Student');
        $context = $this->context($student);
        // max_attempts=2 with 1 used means the underlying `can_start` flag is still
        // true (a retry is technically allowed) — this is exactly the condition that
        // previously leaked "Start Assessment" for a submission awaiting grading.
        $assignment = LearningClassAssessment::factory()->create([
            'learning_class_id' => $context['learningClass']->id,
            'max_attempts' => 2,
        ]);
        $attempt = AssessmentAttempt::factory()->pendingGrading()->create([
            'learning_class_assessment_id' => $assignment->id,
            'enrollment_id' => $context['enrollment']->id,
            'attempt_number' => 1,
        ]);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/Dashboard')
                ->where('dashboard.assessments.0.availability', 'Submitted / Pending Grading')
                ->where('dashboard.assessments.0.can_start', true)
                ->where('dashboard.assessments.0.action.label', 'View submission')
                ->where('dashboard.assessments.0.action.method', 'get')
                ->where(
                    'dashboard.assessments.0.action.url',
                    route('student.assessment-attempts.result', $attempt),
                ));
    }

    public function test_shows_all_caught_up_when_nothing_needs_action(): void
    {
        $student = $this->userWithRole('Student');
        $this->context($student);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/Dashboard')
                ->where('dashboard.needs_attention.remedial', [])
                ->where('dashboard.needs_attention.assessments_available.count', 0));
    }

    public function test_shows_no_enrollment_history_for_a_brand_new_student(): void
    {
        $student = $this->userWithRole('Student');

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/Dashboard')
                ->where('dashboard.has_any_enrollment_history', false)
                ->where('dashboard.continue_learning', []));
    }

    public function test_distinguishes_completed_history_from_never_enrolled(): void
    {
        $student = $this->userWithRole('Student');
        $this->context($student, EnrollmentStatus::Completed, LearningClassStatus::Completed);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/Dashboard')
                ->where('dashboard.has_any_enrollment_history', true)
                ->where('dashboard.continue_learning', []));
    }

    public function test_withdrawn_enrollment_is_excluded_from_continue_learning(): void
    {
        $student = $this->userWithRole('Student');
        $activeContext = $this->context($student);
        $this->context($student, EnrollmentStatus::Withdrawn);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/Dashboard')
                ->has('dashboard.continue_learning', 1)
                ->where('dashboard.continue_learning.0.learning_class_id', $activeContext['learningClass']->id));
    }

    public function test_competency_total_reflects_active_competencies_even_with_no_progress_rows(): void
    {
        $student = $this->userWithRole('Student');
        $course = Course::factory()->create();
        $competencyA = Competency::factory()->for($course)->create(['sort_order' => 1]);
        Competency::factory()->for($course)->create(['sort_order' => 2]);
        Competency::factory()->for($course)->create(['sort_order' => 3]);
        Competency::factory()->for($course)->create(['sort_order' => 4, 'status' => AcademicStatus::Inactive]);
        $learningClass = LearningClass::factory()->for($course)->create(['status' => LearningClassStatus::Active]);
        Enrollment::factory()->for($learningClass)->create([
            'student_id' => $student->id,
            'status' => EnrollmentStatus::Active,
        ]);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/Dashboard')
                ->where('dashboard.progress.competencies_total', 3)
                ->where('dashboard.progress.competencies_mastered', 0));

        StudentCompetencyProgress::factory()->mastered()->create([
            'enrollment_id' => Enrollment::query()->where('student_id', $student->id)->firstOrFail()->id,
            'competency_id' => $competencyA->id,
        ]);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/Dashboard')
                ->where('dashboard.progress.competencies_total', 3)
                ->where('dashboard.progress.competencies_mastered', 1));
    }

    public function test_competency_total_does_not_double_count_a_course_shared_by_two_active_enrollments(): void
    {
        $student = $this->userWithRole('Student');
        $course = Course::factory()->create();
        Competency::factory()->for($course)->create(['sort_order' => 1]);
        Competency::factory()->for($course)->create(['sort_order' => 2]);
        $firstClass = LearningClass::factory()->for($course)->create(['status' => LearningClassStatus::Active]);
        $secondClass = LearningClass::factory()->for($course)->create(['status' => LearningClassStatus::Active]);
        Enrollment::factory()->for($firstClass)->create(['student_id' => $student->id, 'status' => EnrollmentStatus::Active]);
        Enrollment::factory()->for($secondClass)->create(['student_id' => $student->id, 'status' => EnrollmentStatus::Active]);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/Dashboard')
                ->where('dashboard.progress.competencies_total', 2));
    }

    public function test_admin_and_tutor_never_receive_the_student_dashboard(): void
    {
        $this->actingAs($this->userWithRole('Admin'))->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/Dashboard'));

        $this->actingAs($this->userWithRole('Tutor'))->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->component('tutor/Dashboard'));
    }

    /** @return array<string, mixed> */
    private function context(
        User $student,
        EnrollmentStatus $enrollmentStatus = EnrollmentStatus::Active,
        LearningClassStatus $classStatus = LearningClassStatus::Active,
        ?CarbonInterface $enrolledAt = null,
    ): array {
        $course = Course::factory()->create();
        $competency = Competency::factory()->for($course)->create(['sort_order' => 1]);
        $module = Module::factory()->for($competency)->create(['sort_order' => 1]);
        $lessonOne = Lesson::factory()->for($module)->create(['sort_order' => 1]);
        $lessonTwo = Lesson::factory()->for($module)->create(['sort_order' => 2]);
        $learningClass = LearningClass::factory()->for($course)->create(['status' => $classStatus]);
        $enrollment = Enrollment::factory()->for($learningClass)->create([
            'student_id' => $student->id,
            'status' => $enrollmentStatus,
            'enrolled_at' => $enrolledAt ?? now(),
            'completed_at' => $enrollmentStatus === EnrollmentStatus::Completed ? now() : null,
        ]);

        return compact('course', 'competency', 'module', 'lessonOne', 'lessonTwo', 'learningClass', 'enrollment');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
