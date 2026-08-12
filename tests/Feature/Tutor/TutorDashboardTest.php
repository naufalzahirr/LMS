<?php

namespace Tests\Feature\Tutor;

use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Models\AssessmentAttempt;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\StudentCompetencyProgress;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TutorDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_my_classes_is_strictly_scoped_to_the_tutors_own_teaching_classes(): void
    {
        $tutor = $this->userWithRole('Tutor');
        $ownClass = $this->classContext()['learningClass'];
        $ownClass->tutors()->attach($tutor);

        $otherTutor = $this->userWithRole('Tutor');
        $otherClass = $this->classContext()['learningClass'];
        $otherClass->tutors()->attach($otherTutor);

        $this->actingAs($tutor)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tutor/Dashboard')
                ->has('dashboard.my_classes', 1)
                ->where('dashboard.my_classes.0.id', $ownClass->id));
    }

    public function test_my_classes_caps_at_five_but_needs_attention_counts_all_active_classes(): void
    {
        $tutor = $this->userWithRole('Tutor');
        $enrollmentIds = [];

        for ($i = 0; $i < 6; $i++) {
            $context = $this->classContext();
            $context['learningClass']->tutors()->attach($tutor);
            $enrollmentIds[] = $context['enrollment']->id;
        }

        foreach ($enrollmentIds as $enrollmentId) {
            StudentCompetencyProgress::factory()->needsRemedial()->create([
                'enrollment_id' => $enrollmentId,
            ]);
        }

        $this->actingAs($tutor)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tutor/Dashboard')
                ->has('dashboard.my_classes', 5)
                ->where('dashboard.needs_attention.needs_remedial_count', 6));
    }

    public function test_needs_remedial_count_matches_seeded_status_exactly(): void
    {
        $tutor = $this->userWithRole('Tutor');
        $context = $this->classContext();
        $context['learningClass']->tutors()->attach($tutor);

        StudentCompetencyProgress::factory()->needsRemedial()->create([
            'enrollment_id' => $context['enrollment']->id,
        ]);
        StudentCompetencyProgress::factory()->mastered()->create([
            'enrollment_id' => $context['enrollment']->id,
        ]);

        $this->actingAs($tutor)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tutor/Dashboard')
                ->where('dashboard.needs_attention.needs_remedial_count', 1));
    }

    public function test_needs_remedial_count_is_distinct_students_not_competency_progress_rows(): void
    {
        $tutor = $this->userWithRole('Tutor');
        $context = $this->classContext();
        $context['learningClass']->tutors()->attach($tutor);

        StudentCompetencyProgress::factory()->needsRemedial()->create(['enrollment_id' => $context['enrollment']->id]);
        StudentCompetencyProgress::factory()->needsRemedial()->create(['enrollment_id' => $context['enrollment']->id]);
        StudentCompetencyProgress::factory()->needsRemedial()->create(['enrollment_id' => $context['enrollment']->id]);

        $this->actingAs($tutor)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tutor/Dashboard')
                ->where('dashboard.needs_attention.needs_remedial_count', 1));

        $otherStudent = User::factory()->create();
        $otherStudent->assignRole('Student');
        $otherEnrollment = Enrollment::factory()->for($context['learningClass'])->for($otherStudent, 'student')->create([
            'status' => EnrollmentStatus::Active,
        ]);
        StudentCompetencyProgress::factory()->needsRemedial()->create(['enrollment_id' => $otherEnrollment->id]);

        $this->actingAs($tutor)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tutor/Dashboard')
                ->where('dashboard.needs_attention.needs_remedial_count', 2));

        // A NeedsRemedial student from a class this tutor is not assigned to must not count.
        $unassignedContext = $this->classContext();
        StudentCompetencyProgress::factory()->needsRemedial()->create([
            'enrollment_id' => $unassignedContext['enrollment']->id,
        ]);

        $this->actingAs($tutor)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tutor/Dashboard')
                ->where('dashboard.needs_attention.needs_remedial_count', 2));
    }

    public function test_grading_queue_is_scoped_to_the_tutors_own_classes_only(): void
    {
        $tutor = $this->userWithRole('Tutor');
        $ownContext = $this->classContext();
        $ownContext['learningClass']->tutors()->attach($tutor);
        $ownAssignment = LearningClassAssessment::factory()->create([
            'learning_class_id' => $ownContext['learningClass']->id,
        ]);
        AssessmentAttempt::factory()->pendingGrading()->create([
            'learning_class_assessment_id' => $ownAssignment->id,
            'enrollment_id' => $ownContext['enrollment']->id,
        ]);

        $otherTutor = $this->userWithRole('Tutor');
        $otherContext = $this->classContext();
        $otherContext['learningClass']->tutors()->attach($otherTutor);
        $otherAssignment = LearningClassAssessment::factory()->create([
            'learning_class_id' => $otherContext['learningClass']->id,
        ]);
        AssessmentAttempt::factory()->pendingGrading()->create([
            'learning_class_assessment_id' => $otherAssignment->id,
            'enrollment_id' => $otherContext['enrollment']->id,
        ]);

        $this->actingAs($tutor)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tutor/Dashboard')
                ->where('dashboard.grading_queue.count', 1)
                ->has('dashboard.grading_queue.items', 1)
                ->where('dashboard.grading_queue.items.0.assignment_id', $ownAssignment->id));
    }

    public function test_grading_queue_excludes_graded_and_in_progress_attempts(): void
    {
        $tutor = $this->userWithRole('Tutor');
        $context = $this->classContext();
        $context['learningClass']->tutors()->attach($tutor);
        $assignment = LearningClassAssessment::factory()->create([
            'learning_class_id' => $context['learningClass']->id,
        ]);
        AssessmentAttempt::factory()->graded()->create([
            'learning_class_assessment_id' => $assignment->id,
            'enrollment_id' => $context['enrollment']->id,
            'attempt_number' => 1,
        ]);
        AssessmentAttempt::factory()->inProgress()->create([
            'learning_class_assessment_id' => $assignment->id,
            'enrollment_id' => $context['enrollment']->id,
            'attempt_number' => 2,
        ]);

        $this->actingAs($tutor)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tutor/Dashboard')
                ->where('dashboard.grading_queue.count', 0)
                ->where('dashboard.grading_queue.items', []));
    }

    public function test_quick_actions_only_offer_content_authoring_with_an_active_teaching_course(): void
    {
        $tutorWithoutClasses = $this->userWithRole('Tutor');

        $this->actingAs($tutorWithoutClasses)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                $page->component('tutor/Dashboard');
                $page->has('dashboard.quick_actions', 1);
            });

        $tutorWithClass = $this->userWithRole('Tutor');
        $this->classContext()['learningClass']->tutors()->attach($tutorWithClass);

        $this->actingAs($tutorWithClass)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                $page->component('tutor/Dashboard');
                $page->has('dashboard.quick_actions', 3);
            });
    }

    public function test_shows_empty_state_when_the_tutor_has_no_assigned_classes(): void
    {
        $tutor = $this->userWithRole('Tutor');

        $this->actingAs($tutor)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tutor/Dashboard')
                ->where('dashboard.my_classes', [])
                ->where('dashboard.needs_attention.needs_remedial_count', 0)
                ->where('dashboard.grading_queue.count', 0));
    }

    /** @return array<string, mixed> */
    private function classContext(): array
    {
        $course = Course::factory()->create();
        $competency = Competency::factory()->for($course)->create();
        $module = Module::factory()->for($competency)->create();
        Lesson::factory()->for($module)->create();
        $learningClass = LearningClass::factory()->for($course)->create(['status' => LearningClassStatus::Active]);
        $student = User::factory()->create();
        $student->assignRole('Student');
        $enrollment = Enrollment::factory()->for($learningClass)->for($student, 'student')->create([
            'status' => EnrollmentStatus::Active,
        ]);

        return compact('course', 'competency', 'module', 'learningClass', 'student', 'enrollment');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
