<?php

namespace Tests\Feature\Admin;

use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Models\Assessment;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Program;
use App\Models\StudentCompetencyProgress;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_overview_counts_active_classes_enrollments_tutors_courses_and_programs(): void
    {
        $admin = $this->userWithRole('Admin');
        $context = $this->classContext();
        $tutor = $this->userWithRole('Tutor');
        $context['learningClass']->tutors()->attach($tutor);

        LearningClass::factory()->for($context['course'])->create(['status' => LearningClassStatus::Inactive]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Dashboard')
                ->where('dashboard.overview.active_classes', 1)
                ->where('dashboard.overview.active_enrollments', 1)
                ->where('dashboard.overview.tutors_with_assignments', 1)
                ->where('dashboard.overview.active_courses', 1)
                ->where('dashboard.overview.active_programs', 1));
    }

    public function test_tutors_with_assignments_excludes_tutors_only_assigned_to_inactive_classes(): void
    {
        $admin = $this->userWithRole('Admin');
        $inactiveClass = LearningClass::factory()->create(['status' => LearningClassStatus::Inactive]);
        $idleTutor = $this->userWithRole('Tutor');
        $inactiveClass->tutors()->attach($idleTutor);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Dashboard')
                ->where('dashboard.overview.tutors_with_assignments', 0));
    }

    public function test_needs_attention_lists_active_classes_without_a_tutor(): void
    {
        $admin = $this->userWithRole('Admin');
        $unassignedClass = LearningClass::factory()->create([
            'name' => 'Unassigned Cohort',
            'status' => LearningClassStatus::Active,
        ]);
        $assignedContext = $this->classContext();
        $assignedContext['learningClass']->tutors()->attach($this->userWithRole('Tutor'));

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Dashboard')
                ->where('dashboard.needs_attention.classes_without_tutor.total', 1)
                ->has('dashboard.needs_attention.classes_without_tutor.items', 1));
    }

    public function test_needs_attention_lists_active_classes_without_enrolled_students(): void
    {
        $admin = $this->userWithRole('Admin');
        $emptyClass = LearningClass::factory()->create([
            'name' => 'Empty Cohort',
            'status' => LearningClassStatus::Active,
        ]);
        $this->classContext();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Dashboard')
                ->where('dashboard.needs_attention.classes_without_students.total', 1)
                ->where('dashboard.needs_attention.classes_without_students.items.0.id', $emptyClass->id));
    }

    public function test_needs_attention_items_cap_at_five_with_an_accurate_total_badge(): void
    {
        $admin = $this->userWithRole('Admin');

        for ($i = 0; $i < 7; $i++) {
            LearningClass::factory()->create(['status' => LearningClassStatus::Active]);
        }

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Dashboard')
                ->has('dashboard.needs_attention.classes_without_tutor.items', 5)
                ->where('dashboard.needs_attention.classes_without_tutor.total', 7));
    }

    public function test_content_overview_reports_counts_only(): void
    {
        $admin = $this->userWithRole('Admin');
        $context = $this->classContext();
        Assessment::factory()->for($context['competency'])->published()->create();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Dashboard')
                ->where('dashboard.content.programs', 1)
                ->where('dashboard.content.courses', 1)
                ->where('dashboard.content.lessons', 1)
                ->where('dashboard.content.assessments', 1));

        $response->assertDontSee($context['lesson']->content);
    }

    public function test_learning_status_reuses_existing_mastery_semantics(): void
    {
        $admin = $this->userWithRole('Admin');
        $context = $this->classContext();
        StudentCompetencyProgress::factory()->mastered()->create([
            'enrollment_id' => $context['enrollment']->id,
        ]);
        $secondContext = $this->classContext();
        StudentCompetencyProgress::factory()->needsRemedial()->create([
            'enrollment_id' => $secondContext['enrollment']->id,
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Dashboard')
                ->where('dashboard.learning_status.students_currently_learning', 2)
                ->where('dashboard.learning_status.competencies_mastered', 1)
                ->where('dashboard.learning_status.students_needing_remedial', 1));
    }

    public function test_quick_actions_link_to_existing_admin_routes(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Dashboard')
                ->where('dashboard.quick_actions.0.url', route('admin.classes.create'))
                ->where('dashboard.quick_actions.1.url', route('admin.users.index'))
                ->where('dashboard.quick_actions.2.url', route('admin.courses.create'))
                ->where('dashboard.quick_actions.3.url', route('admin.reports.progress.index')));
    }

    public function test_non_admin_can_never_reach_the_admin_dashboard(): void
    {
        $this->actingAs($this->userWithRole('Tutor'))->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->component('tutor/Dashboard'));

        $this->actingAs($this->userWithRole('Student'))->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->component('student/Dashboard'));
    }

    /** @return array<string, mixed> */
    private function classContext(): array
    {
        $program = Program::factory()->create();
        $course = Course::factory()->for($program)->create();
        $competency = Competency::factory()->for($course)->create();
        $module = Module::factory()->for($competency)->create();
        $lesson = Lesson::factory()->for($module)->create();
        $learningClass = LearningClass::factory()->for($course)->create(['status' => LearningClassStatus::Active]);
        $student = User::factory()->create();
        $student->assignRole('Student');
        $enrollment = Enrollment::factory()->for($learningClass)->for($student, 'student')->create([
            'status' => EnrollmentStatus::Active,
        ]);

        return compact('program', 'course', 'competency', 'module', 'lesson', 'learningClass', 'student', 'enrollment');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
