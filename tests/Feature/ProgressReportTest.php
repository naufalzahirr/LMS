<?php

namespace Tests\Feature;

use App\Enums\AssessmentPurpose;
use App\Enums\StudentCompetencyStatus;
use App\Models\Assessment;
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
use App\Models\StudentCompetencyProgress;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProgressReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_overview_supports_all_filters_and_calculates_clear_counts(): void
    {
        $context = $this->reportContext();
        $admin = $this->userWithRole('Admin');
        $query = http_build_query([
            'program_id' => $context['course']->program_id,
            'course_id' => $context['course']->id,
            'learning_class_id' => $context['class']->id,
            'student_id' => $context['student']->id,
            'mastery_status' => StudentCompetencyStatus::NeedsRemedial->value,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.progress.index').'?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/reports/Progress')
                ->has('report.rows', 1)
                ->where('report.summary.students', 1)
                ->where('report.summary.classes', 1)
                ->where('report.summary.needs_remedial', 1)
                ->where('report.summary.average_best_score', 65)
                ->where('report.rows.0.lesson_percentage', 50)
                ->where('report.rows.0.competencies_mastered', 0));

        $this->actingAs($admin)
            ->get(route('admin.reports.progress.index', [
                'mastery_status' => StudentCompetencyStatus::Mastered->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('report.rows', 0));
    }

    public function test_admin_and_exact_class_tutor_get_the_same_class_report(): void
    {
        $context = $this->reportContext();
        $admin = $this->userWithRole('Admin');
        $assignedTutor = $this->userWithRole('Tutor');
        $unassignedTutor = $this->userWithRole('Tutor');
        $context['class']->tutors()->attach($assignedTutor);

        $assertReport = fn (Assert $page): Assert => $page
            ->component('reports/ClassProgress')
            ->where('report.summary.students', 1)
            ->where('report.summary.competencies', 1)
            ->where('report.summary.mastery_rate', 0)
            ->where('report.summary.needs_remedial', 1)
            ->where('report.students.0.lesson_percentage', 50)
            ->where('report.assessments.0.pending_grading', 1)
            ->where('report.attention.0.reasons.0', 'Needs remedial learning')
            ->where('report.attention.0.reasons.1', 'Maximum mastery attempts reached');

        $this->actingAs($admin)
            ->get(route('admin.reports.classes.show', $context['class']))
            ->assertOk()
            ->assertInertia($assertReport);
        $this->actingAs($assignedTutor)
            ->get(route('tutor.reports.classes.show', $context['class']))
            ->assertOk()
            ->assertInertia($assertReport);
        $this->actingAs($unassignedTutor)
            ->get(route('tutor.reports.classes.show', $context['class']))
            ->assertForbidden();
    }

    public function test_students_and_parents_cannot_open_reports(): void
    {
        $context = $this->reportContext();

        foreach ([$this->userWithRole('Student'), $this->userWithRole('Parent')] as $user) {
            $this->actingAs($user)->get(route('admin.reports.progress.index'))->assertForbidden();
            $this->actingAs($user)
                ->get(route('admin.reports.classes.show', $context['class']))
                ->assertForbidden();
            $this->actingAs($user)
                ->get(route('tutor.reports.classes.show', $context['class']))
                ->assertForbidden();
            $this->actingAs($user)
                ->get(route('admin.reports.classes.progress.csv', $context['class']))
                ->assertForbidden();
        }
    }

    public function test_admin_csv_contains_the_required_mastery_columns_and_values(): void
    {
        $context = $this->reportContext();
        $admin = $this->userWithRole('Admin');

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.classes.progress.csv', $context['class']))
            ->assertOk()
            ->assertDownload($context['class']->code.'-progress.csv');
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Student,Email,Class,Competency,Status,Latest,Best,Required,"Mastered At"', $csv);
        $this->assertStringContainsString('Report Student', $csv);
        $this->assertStringContainsString('Report Cohort', $csv);
        $this->assertStringContainsString('Core Competency', $csv);
        $this->assertStringContainsString('needs_remedial,60.00,65.00,80.00', $csv);
    }

    public function test_reading_reports_does_not_mutate_learning_records(): void
    {
        $context = $this->reportContext();
        $admin = $this->userWithRole('Admin');
        $before = $context['progress']->fresh()->getAttributes();

        $this->actingAs($admin)->get(route('admin.reports.progress.index'))->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.reports.classes.show', $context['class']))
            ->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.reports.classes.progress.csv', $context['class']))
            ->assertOk();

        $this->assertSame($before, $context['progress']->fresh()->getAttributes());
    }

    /** @return array<string, mixed> */
    private function reportContext(): array
    {
        $student = User::factory()->create(['name' => 'Report Student']);
        $student->assignRole('Student');
        $course = Course::factory()->create(['name' => 'Reporting Course']);
        $competency = Competency::factory()->for($course)->create(['name' => 'Core Competency']);
        $module = Module::factory()->for($competency)->create();
        $completedLesson = Lesson::factory()->for($module)->create();
        Lesson::factory()->for($module)->create();
        $learningClass = LearningClass::factory()->for($course)->create(['name' => 'Report Cohort']);
        $enrollment = Enrollment::factory()->for($learningClass)->for($student, 'student')->create();
        LessonProgress::factory()->for($enrollment)->for($completedLesson)->completed()->create();
        $assessment = Assessment::factory()->for($competency)->published()->create([
            'title' => 'Core Mastery Check',
            'purpose' => AssessmentPurpose::Mastery,
        ]);
        $assignment = LearningClassAssessment::factory()->for($learningClass)->for($assessment)->create([
            'max_attempts' => 1,
        ]);
        MasteryRule::factory()->create([
            'learning_class_id' => $learningClass->id,
            'competency_id' => $competency->id,
            'learning_class_assessment_id' => $assignment->id,
            'mastery_score' => '80.00',
        ]);
        AssessmentAttempt::factory()->pendingGrading()->create([
            'learning_class_assessment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
            'attempt_number' => 1,
        ]);
        $progress = StudentCompetencyProgress::factory()->needsRemedial()->create([
            'enrollment_id' => $enrollment->id,
            'competency_id' => $competency->id,
            'latest_score' => '60.00',
            'best_score' => '65.00',
            'total_mastery_attempts' => 1,
        ]);

        return [
            'student' => $student,
            'course' => $course,
            'competency' => $competency,
            'class' => $learningClass,
            'enrollment' => $enrollment,
            'assignment' => $assignment,
            'progress' => $progress,
        ];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
