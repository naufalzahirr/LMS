<?php

namespace Tests\Feature;

use App\Enums\AssessmentPurpose;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
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
use App\Models\ParentStudentRelationship;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\RemedialAssignment;
use App\Models\RemedialAssignmentLesson;
use App\Models\StudentCompetencyProgress;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ParentProgressMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_single_child_dashboard_shows_safe_current_and_historical_progress(): void
    {
        $context = $this->progressContext();
        $historicalClass = LearningClass::factory()->for($context['course'])->create([
            'name' => 'Archived Web Cohort',
            'status' => LearningClassStatus::Completed,
        ]);
        Enrollment::factory()->for($historicalClass)->for($context['student'], 'student')->withdrawn()->create();

        $response = $this->actingAs($context['parent'])->get(route('parent.dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('parent/Dashboard')
            ->has('children', 1)
            ->where('children.0.id', $context['student']->id)
            ->where('children.0.summary.active_classes', 1)
            ->where('children.0.summary.lesson_percentage', 50)
            ->where('children.0.summary.competencies_mastered', 0)
            ->where('children.0.summary.needs_remedial', 1)
            ->where('children.0.current_classes.0.mastery.0.status', StudentCompetencyStatus::NeedsRemedial->value)
            ->where('children.0.current_classes.0.mastery.0.best_score', '65.00')
            ->where('children.0.current_classes.0.mastery.0.required_score', '80.00')
            ->where('children.0.current_classes.0.mastery.0.remedial_lessons.0', 'Recovery Lesson')
            ->where('children.0.current_classes.0.assessments.0.status', 'pending_grading')
            ->where('children.0.current_classes.0.assessments.0.score', null)
            ->where('children.0.current_classes.0.assessments.1.status', 'graded')
            ->where('children.0.current_classes.0.assessments.1.score', '7.00 / 10.00')
            ->where('children.0.current_classes.0.assessments.1.percentage', '70.00')
            ->where('children.0.history_classes.0.name', 'Archived Web Cohort')
            ->where('children.0.history_classes.0.enrollment_status', EnrollmentStatus::Withdrawn->value));
        $response->assertDontSee('tutor-only secret note');
        $response->assertDontSee('private lesson body');
        $response->assertDontSee('secret assessment instructions');
        $response->assertDontSee('secret per-question prompt');
        $response->assertDontSee('parent-secret-correct-option');
        $response->assertDontSee('is_correct');
        $response->assertDontSee('accepted_answers');
        $response->assertDontSee('answer_text');
    }

    public function test_one_student_can_be_visible_to_multiple_linked_parents_but_not_an_unlinked_parent(): void
    {
        $context = $this->progressContext();
        $secondParent = $this->userWithRole('Parent');
        $unlinkedParent = $this->userWithRole('Parent');
        ParentStudentRelationship::factory()->create([
            'parent_id' => $secondParent->id,
            'student_id' => $context['student']->id,
        ]);

        foreach ([$context['parent'], $secondParent] as $parent) {
            $this->actingAs($parent)
                ->get(route('parent.students.show', $context['student']))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('parent/students/Show')
                    ->where('student.id', $context['student']->id));
        }

        $this->actingAs($unlinkedParent)
            ->get(route('parent.students.show', $context['student']))
            ->assertForbidden();
    }

    public function test_multiple_children_receive_separate_dashboard_cards(): void
    {
        $context = $this->progressContext();
        $secondStudent = $this->userWithRole('Student');
        ParentStudentRelationship::factory()->create([
            'parent_id' => $context['parent']->id,
            'student_id' => $secondStudent->id,
        ]);

        $this->actingAs($context['parent'])
            ->get(route('parent.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('parent/Dashboard')
                ->has('children', 2));
    }

    public function test_parent_dashboard_is_read_only_and_cannot_cross_into_learning_or_report_routes(): void
    {
        $context = $this->progressContext();

        $this->actingAs($context['parent'])
            ->get(route('dashboard'))
            ->assertRedirect(route('parent.dashboard'));
        $this->actingAs($context['parent'])
            ->patch(route('student.lesson-progress.update', [$context['class'], $context['lesson']]), [
                'status' => 'completed',
            ])->assertForbidden();
        $this->actingAs($context['parent'])
            ->get(route('student.lessons.show', [$context['class'], $context['lesson']]))
            ->assertForbidden();
        $this->actingAs($context['parent'])
            ->patch(route('admin.remedials.update', $context['remedial']), [
                'notes' => 'parent mutation',
            ])->assertForbidden();
        $this->actingAs($context['parent'])
            ->get(route('admin.reports.progress.index'))
            ->assertForbidden();
        $this->actingAs($context['parent'])
            ->get(route('tutor.reports.classes.show', $context['class']))
            ->assertForbidden();

        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $context['enrollment']->id,
            'lesson_id' => $context['lesson']->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('remedial_assignments', [
            'id' => $context['remedial']->id,
            'notes' => 'tutor-only secret note',
        ]);
    }

    public function test_parent_assessment_history_is_limited_to_ten_recent_attempts_per_enrollment(): void
    {
        $context = $this->progressContext();

        foreach (range(3, 14) as $attemptNumber) {
            AssessmentAttempt::factory()->graded()->create([
                'learning_class_assessment_id' => $context['assignment']->id,
                'enrollment_id' => $context['enrollment']->id,
                'attempt_number' => $attemptNumber,
                'submitted_at' => now()->addMinutes($attemptNumber),
            ]);
        }

        $this->actingAs($context['parent'])
            ->get(route('parent.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('children.0.current_classes.0.assessments', 10)
                ->where('children.0.current_classes.0.assessments.0.attempt', 14)
                ->where('children.0.current_classes.0.assessments.9.attempt', 5));
    }

    /** @return array<string, mixed> */
    private function progressContext(): array
    {
        $parent = $this->userWithRole('Parent');
        $student = $this->userWithRole('Student');
        ParentStudentRelationship::factory()->create([
            'parent_id' => $parent->id,
            'student_id' => $student->id,
        ]);
        $course = Course::factory()->create(['name' => 'Web Foundations']);
        $competency = Competency::factory()->for($course)->create(['name' => 'Semantic HTML']);
        $module = Module::factory()->for($competency)->create();
        $lesson = Lesson::factory()->for($module)->create([
            'title' => 'Recovery Lesson',
            'content' => 'private lesson body',
        ]);
        Lesson::factory()->for($module)->create();
        $learningClass = LearningClass::factory()->for($course)->create(['name' => 'Web Cohort A']);
        $enrollment = Enrollment::factory()->for($learningClass)->for($student, 'student')->create();
        LessonProgress::factory()->for($enrollment)->for($lesson)->completed()->create();
        $assessment = Assessment::factory()->for($competency)->published()->create([
            'title' => 'HTML Mastery',
            'purpose' => AssessmentPurpose::Mastery,
            'instructions' => 'secret assessment instructions',
        ]);
        $questionBank = QuestionBank::factory()->for($course)->create();
        $question = Question::factory()->for($questionBank)->for($competency)->multipleChoice()->create([
            'prompt' => 'secret per-question prompt',
        ]);
        $question->options()->where('is_correct', true)->update([
            'option_text' => 'parent-secret-correct-option',
        ]);
        $assessment->assessmentQuestions()->create([
            'question_id' => $question->id,
            'points' => '10.00',
            'sort_order' => 0,
        ]);
        $assignment = LearningClassAssessment::factory()->for($learningClass)->for($assessment)->create([
            'max_attempts' => 2,
        ]);
        $rule = MasteryRule::factory()->create([
            'learning_class_id' => $learningClass->id,
            'competency_id' => $competency->id,
            'learning_class_assessment_id' => $assignment->id,
            'mastery_score' => '80.00',
        ]);
        $attempt = AssessmentAttempt::factory()->pendingGrading()->create([
            'learning_class_assessment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
            'attempt_number' => 1,
            'max_points' => '10.00',
        ]);
        AssessmentAttempt::factory()->graded()->create([
            'learning_class_assessment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
            'attempt_number' => 2,
            'auto_points' => '7.00',
            'earned_points' => '7.00',
            'max_points' => '10.00',
            'percentage' => '70.00',
            'submitted_at' => now()->subDay(),
        ]);
        StudentCompetencyProgress::factory()->needsRemedial()->create([
            'enrollment_id' => $enrollment->id,
            'competency_id' => $competency->id,
            'latest_score' => '60.00',
            'best_score' => '65.00',
            'total_mastery_attempts' => 1,
        ]);
        $remedial = RemedialAssignment::factory()->create([
            'mastery_rule_id' => $rule->id,
            'enrollment_id' => $enrollment->id,
            'competency_id' => $competency->id,
            'source_assessment_attempt_id' => $attempt->id,
            'notes' => 'tutor-only secret note',
        ]);
        RemedialAssignmentLesson::factory()->create([
            'remedial_assignment_id' => $remedial->id,
            'lesson_id' => $lesson->id,
        ]);

        return [
            'parent' => $parent,
            'student' => $student,
            'course' => $course,
            'competency' => $competency,
            'class' => $learningClass,
            'enrollment' => $enrollment,
            'lesson' => $lesson,
            'assignment' => $assignment,
            'remedial' => $remedial,
        ];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
