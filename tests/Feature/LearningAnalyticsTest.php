<?php

namespace Tests\Feature;

use App\Enums\AcademicStatus;
use App\Enums\LearningClassStatus;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Program;
use App\Models\StudentCompetencyProgress;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LearningAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_counts_unique_students_and_preserves_sparse_active_denominators(): void
    {
        $admin = $this->userWithRole('Admin');
        $student = $this->userWithRole('Student');
        $program = Program::factory()->create(['status' => AcademicStatus::Active]);
        $course = Course::factory()->for($program)->create(['status' => AcademicStatus::Active]);
        $firstCompetency = Competency::factory()->for($course)->create(['status' => AcademicStatus::Active]);
        $secondCompetency = Competency::factory()->for($course)->create(['status' => AcademicStatus::Active]);
        $firstLesson = Lesson::factory()->for(Module::factory()->for($firstCompetency))->create();
        Lesson::factory()->for(Module::factory()->for($secondCompetency))->create();
        Competency::factory()->for($course)->create(['status' => AcademicStatus::Inactive]);
        $firstClass = LearningClass::factory()->for($course)->create(['status' => LearningClassStatus::Active]);
        $secondClass = LearningClass::factory()->for($course)->create(['status' => LearningClassStatus::Active]);
        $firstEnrollment = Enrollment::factory()->for($firstClass)->for($student, 'student')->create();
        Enrollment::factory()->for($secondClass)->for($student, 'student')->create();
        LessonProgress::factory()->for($firstEnrollment)->for($firstLesson)->completed()->create();

        $inactiveClass = LearningClass::factory()->for($course)->create(['status' => LearningClassStatus::Inactive]);
        Enrollment::factory()->for($inactiveClass)->for($this->userWithRole('Student'), 'student')->create();

        $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/analytics/Index')
                ->where('analytics.overview.active_classes', 2)
                ->where('analytics.overview.active_students', 1)
                ->where('analytics.overview.completed_lessons', 1)
                ->where('analytics.overview.total_lessons', 4)
                ->where('analytics.overview.competencies_mastered', 0)
                ->where('analytics.overview.competencies_total', 4)
                ->where('analytics.overview.mastery_percentage', 0)
                ->has('analytics.classes.data', 2));
    }

    public function test_admin_distinguishes_unique_remedial_students_from_competency_cases(): void
    {
        $admin = $this->userWithRole('Admin');
        [$course, $learningClass] = $this->courseAndClass();
        $competencies = Competency::factory(3)->for($course)->create();
        $studentA = $this->userWithRole('Student');
        $studentB = $this->userWithRole('Student');
        $enrollmentA = Enrollment::factory()->for($learningClass)->for($studentA, 'student')->create();
        $enrollmentB = Enrollment::factory()->for($learningClass)->for($studentB, 'student')->create();

        foreach ($competencies as $competency) {
            StudentCompetencyProgress::factory()->needsRemedial()->create([
                'enrollment_id' => $enrollmentA->id,
                'competency_id' => $competency->id,
            ]);
        }

        StudentCompetencyProgress::factory()->needsRemedial()->create([
            'enrollment_id' => $enrollmentB->id,
            'competency_id' => $competencies->first()->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.overview.students_needing_remedial', 2)
                ->where('analytics.overview.remedial_cases', 4)
                ->where('analytics.classes.data.0.students_needing_remedial', 2)
                ->where('analytics.classes.data.0.remedial_cases', 4));
    }

    public function test_assessment_analytics_selects_latest_graded_attempt_and_never_scores_pending_as_zero(): void
    {
        $admin = $this->userWithRole('Admin');
        [$course, $learningClass] = $this->courseAndClass();
        $competency = Competency::factory()->for($course)->create();
        $assignment = LearningClassAssessment::factory()->for($learningClass)->create([
            'assessment_id' => Assessment::factory()->for($competency)->published(),
            'max_attempts' => 3,
        ]);
        $firstEnrollment = Enrollment::factory()->for($learningClass)->for($this->userWithRole('Student'), 'student')->create();
        $secondEnrollment = Enrollment::factory()->for($learningClass)->for($this->userWithRole('Student'), 'student')->create();

        AssessmentAttempt::factory()->graded()->create([
            'learning_class_assessment_id' => $assignment->id,
            'enrollment_id' => $firstEnrollment->id,
            'attempt_number' => 1,
            'percentage' => '40.00',
        ]);
        AssessmentAttempt::factory()->graded()->create([
            'learning_class_assessment_id' => $assignment->id,
            'enrollment_id' => $firstEnrollment->id,
            'attempt_number' => 2,
            'percentage' => '80.00',
        ]);
        AssessmentAttempt::factory()->pendingGrading()->create([
            'learning_class_assessment_id' => $assignment->id,
            'enrollment_id' => $firstEnrollment->id,
            'attempt_number' => 3,
        ]);
        AssessmentAttempt::factory()->pendingGrading()->create([
            'learning_class_assessment_id' => $assignment->id,
            'enrollment_id' => $secondEnrollment->id,
            'attempt_number' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.overview.assessment_submitted', 2)
                ->where('analytics.overview.assessment_eligible', 2)
                ->where('analytics.overview.assessment_pending_grading', 2)
                ->where('analytics.overview.assessment_graded', 1)
                ->where('analytics.overview.assessment_average', 80)
                ->where('analytics.assessments.0.submitted_students', 2)
                ->where('analytics.assessments.0.graded_students', 1)
                ->where('analytics.assessments.0.average_score', 80));
    }

    public function test_admin_filters_compose_and_inactive_scope_does_not_inflate_metrics(): void
    {
        $admin = $this->userWithRole('Admin');
        [$courseA, $classA] = $this->courseAndClass();
        [$courseB] = $this->courseAndClass();
        Enrollment::factory()->for($classA)->for($this->userWithRole('Student'), 'student')->create();

        $inactiveCourse = Course::factory()->create(['status' => AcademicStatus::Inactive]);
        $inactiveClass = LearningClass::factory()->for($inactiveCourse)->create(['status' => LearningClassStatus::Active]);
        Enrollment::factory()->for($inactiveClass)->for($this->userWithRole('Student'), 'student')->create();

        $this->actingAs($admin)
            ->get(route('admin.analytics.index', [
                'program_id' => $courseA->program_id,
                'course_id' => $courseB->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.overview.active_classes', 0)
                ->where('analytics.overview.active_students', 0));

        $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.overview.active_classes', 2)
                ->where('analytics.overview.active_students', 1));
    }

    public function test_tutor_analytics_is_server_scoped_to_assigned_classes_even_with_a_manipulated_filter(): void
    {
        $tutor = $this->userWithRole('Tutor');
        [$ownCourse, $ownClass] = $this->courseAndClass();
        [$otherCourse, $otherClass] = $this->courseAndClass();
        $ownClass->tutors()->attach($tutor);
        $ownStudent = $this->userWithRole('Student', ['name' => 'Visible Student']);
        $otherStudent = $this->userWithRole('Student', ['name' => 'Hidden Student']);
        Enrollment::factory()->for($ownClass)->for($ownStudent, 'student')->create();
        Enrollment::factory()->for($otherClass)->for($otherStudent, 'student')->create();

        $response = $this->actingAs($tutor)->get(route('tutor.analytics.index'));
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('tutor/analytics/Index')
            ->where('analytics.overview.active_classes', 1)
            ->where('analytics.overview.active_students', 1)
            ->where('analytics.students.data.0.student', 'Visible Student'));
        $response->assertDontSee('Hidden Student');

        $this->actingAs($tutor)
            ->get(route('tutor.analytics.index', [
                'course_id' => $otherCourse->id,
                'learning_class_id' => $otherClass->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.overview.active_classes', 0)
                ->where('analytics.students.data', []));

        $this->assertNotSame($ownCourse->id, $otherCourse->id);
    }

    public function test_student_progress_contains_only_own_sparse_mastery_and_safe_assessment_results(): void
    {
        $student = $this->userWithRole('Student', ['name' => 'Analytics Student']);
        $otherStudent = $this->userWithRole('Student', ['name' => 'Private Peer']);
        [$course, $learningClass] = $this->courseAndClass();
        $competencies = Competency::factory(2)->for($course)->create();
        $module = Module::factory()->for($competencies->first())->create();
        Lesson::factory(2)->for($module)->create();
        $enrollment = Enrollment::factory()->for($learningClass)->for($student, 'student')->create();
        Enrollment::factory()->for($learningClass)->for($otherStudent, 'student')->create();
        $assignment = LearningClassAssessment::factory()->for($learningClass)->create([
            'assessment_id' => Assessment::factory()->for($competencies->first())->published(),
        ]);
        AssessmentAttempt::factory()->pendingGrading()->create([
            'learning_class_assessment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $response = $this->actingAs($student)->get(route('student.progress.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('student/progress/Index')
            ->where('insights.summary.completed_lessons', 0)
            ->where('insights.summary.total_lessons', 2)
            ->where('insights.summary.competencies_mastered', 0)
            ->where('insights.summary.competencies_total', 2)
            ->where('insights.summary.assessment_pending_grading', 1)
            ->where('insights.summary.assessment_average', null)
            ->where('insights.recent_assessments.0.status', 'pending_grading')
            ->where('insights.recent_assessments.0.percentage', null)
            ->has('insights.competencies.learning', 2));
        $response->assertDontSee('Private Peer');
        $response->assertDontSee('correct_option');
        $response->assertDontSee('accepted_answers');
    }

    public function test_role_access_and_csv_exports_are_scoped(): void
    {
        $admin = $this->userWithRole('Admin');
        $tutor = $this->userWithRole('Tutor');
        $student = $this->userWithRole('Student');
        $parent = $this->userWithRole('Parent');
        [, $learningClass] = $this->courseAndClass();
        $learningClass->tutors()->attach($tutor);
        Enrollment::factory()->for($learningClass)->for($student, 'student')->create();

        $this->actingAs($admin)->get(route('admin.analytics.csv'))
            ->assertOk()
            ->assertDownload('learning-class-analytics.csv');
        $this->actingAs($tutor)->get(route('tutor.analytics.csv'))
            ->assertOk()
            ->assertDownload('student-progress-analytics.csv');

        foreach ([$student, $parent, $tutor] as $user) {
            $this->actingAs($user)->get(route('admin.analytics.index'))->assertForbidden();
        }

        foreach ([$student, $parent] as $user) {
            $this->actingAs($user)->get(route('tutor.analytics.index'))->assertForbidden();
        }

        foreach ([$admin, $tutor, $parent] as $user) {
            $this->actingAs($user)->get(route('student.progress.index'))->assertForbidden();
        }
    }

    /** @return array{Course, LearningClass} */
    private function courseAndClass(): array
    {
        $program = Program::factory()->create(['status' => AcademicStatus::Active]);
        $course = Course::factory()->for($program)->create(['status' => AcademicStatus::Active]);
        $learningClass = LearningClass::factory()->for($course)->create(['status' => LearningClassStatus::Active]);

        return [$course, $learningClass];
    }

    /** @param array<string, mixed> $attributes */
    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
