<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AcademicStatus;
use App\Enums\AssessmentFeedbackMode;
use App\Enums\AssessmentPurpose;
use App\Enums\AssessmentStatus;
use App\Enums\ClassAssessmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Enums\MasteryRuleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLearningClassRequest;
use App\Http\Requests\Admin\UpdateLearningClassRequest;
use App\Models\Assessment;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Program;
use App\Models\User;
use App\Services\LearningClassService;
use App\Services\LearningProgressQueryService;
use App\Services\MasteryProgressQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LearningClassController extends Controller
{
    public function __construct(
        private readonly LearningClassService $learningClassService,
        private readonly LearningProgressQueryService $progressQuery,
        private readonly MasteryProgressQueryService $masteryProgress,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('manage', LearningClass::class);

        $search = trim($request->string('search')->toString());
        $courseId = $request->integer('course_id');
        $programId = $request->integer('program_id');
        $status = LearningClassStatus::tryFrom($request->string('status')->toString());
        $query = LearningClass::query()
            ->with('course.program:id,name')
            ->withCount([
                'enrollments as active_students_count' => fn ($query) => $query->where('status', EnrollmentStatus::Active->value),
                'tutors',
            ]);

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($courseId > 0) {
            $query->where('course_id', $courseId);
        }

        if ($programId > 0) {
            $query->whereHas('course', fn ($query) => $query->where('program_id', $programId));
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $paginator = $query->orderByDesc('start_date')->orderBy('name')->paginate(10)->withQueryString();

        return Inertia::render('admin/classes/Index', [
            'classes' => [
                'data' => $paginator->getCollection()->map(fn (LearningClass $learningClass): array => [
                    'id' => $learningClass->id,
                    'name' => $learningClass->name,
                    'code' => $learningClass->code,
                    'course' => $learningClass->course->name,
                    'program' => $learningClass->course->program->name,
                    'status' => $learningClass->status->value,
                    'start_date' => $learningClass->start_date?->toDateString(),
                    'end_date' => $learningClass->end_date?->toDateString(),
                    'active_students_count' => $learningClass->active_students_count ?? 0,
                    'tutors_count' => $learningClass->tutors_count ?? 0,
                ])->all(),
                'links' => $paginator->linkCollection()->all(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'search' => $search,
                'course_id' => $courseId > 0 ? (string) $courseId : '',
                'program_id' => $programId > 0 ? (string) $programId : '',
                'status' => $status->value ?? '',
            ],
            'courses' => $this->courseOptions(),
            'programs' => $this->programOptions(),
            'statuses' => LearningClassStatus::options(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', LearningClass::class);

        return Inertia::render('admin/classes/Create', [
            'courses' => $this->courseOptions(),
            'programs' => $this->programOptions(),
            'statuses' => LearningClassStatus::options(),
        ]);
    }

    public function store(StoreLearningClassRequest $request): RedirectResponse
    {
        $learningClass = $this->learningClassService->create($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Class created.')]);

        return to_route('admin.classes.show', $learningClass);
    }

    public function show(LearningClass $learningClass): Response
    {
        $this->authorize('manage', $learningClass);
        $learningClass->load([
            'course.program:id,name',
            'tutors:id,name,email',
            'enrollments.student:id,name,email',
            'assessmentAssignments' => fn ($query) => $query
                ->withCount('attempts')
                ->with(['assessment' => fn ($query) => $query
                    ->with('competency:id,course_id,name')
                    ->withCount('assessmentQuestions')
                    ->withSum('assessmentQuestions as total_points', 'points')]),
        ]);
        $assignedTutorIds = $learningClass->tutors->pluck('id');
        $enrolledStudentIds = $learningClass->enrollments->pluck('student_id');
        $progress = $this->progressQuery->summariesForEnrollments($learningClass->enrollments);

        return Inertia::render('admin/classes/Show', [
            'learningClass' => $this->classDetails($learningClass),
            'enrollments' => $learningClass->enrollments->map(fn (Enrollment $enrollment): array => [
                'id' => $enrollment->id,
                'student' => [
                    'id' => $enrollment->student->id,
                    'name' => $enrollment->student->name,
                    'email' => $enrollment->student->email,
                ],
                'status' => $enrollment->status->value,
                'enrolled_at' => $enrollment->enrolled_at->toDateTimeString(),
                'completed_at' => $enrollment->completed_at?->toDateTimeString(),
                'completed_lessons' => $progress[$enrollment->id]['completed_lessons'],
                'total_lessons' => $progress[$enrollment->id]['total_lessons'],
                'progress_percentage' => $progress[$enrollment->id]['percentage'],
            ])->values()->all(),
            'tutors' => $learningClass->tutors->map(fn (User $tutor): array => [
                'id' => $tutor->id,
                'name' => $tutor->name,
                'email' => $tutor->email,
            ])->values()->all(),
            'studentOptions' => User::role('Student')->whereNotIn('id', $enrolledStudentIds)->orderBy('name')->get(['id', 'name', 'email'])
                ->map(fn (User $student): array => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                ])->all(),
            'tutorOptions' => User::role('Tutor')->whereNotIn('id', $assignedTutorIds)->orderBy('name')->get(['id', 'name', 'email'])
                ->map(fn (User $tutor): array => [
                    'id' => $tutor->id,
                    'name' => $tutor->name,
                    'email' => $tutor->email,
                ])->all(),
            'assessmentAssignments' => $learningClass->assessmentAssignments->map(
                fn (LearningClassAssessment $assignment): array => $this->assessmentAssignment($assignment),
            )->values()->all(),
            'assessmentOptions' => Assessment::query()
                ->where('status', AssessmentStatus::Published->value)
                ->whereHas('competency', fn ($query) => $query->where('course_id', $learningClass->course_id))
                ->whereNotIn('id', $learningClass->assessmentAssignments->pluck('assessment_id'))
                ->with('competency:id,course_id,name')
                ->orderBy('title')
                ->get()
                ->map(fn (Assessment $assessment): array => [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'purpose' => $assessment->purpose->value,
                    'competency' => $assessment->competency->name,
                ])->all(),
            'classAssessmentStatuses' => ClassAssessmentStatus::options(),
            'assessmentFeedbackModes' => AssessmentFeedbackMode::options(),
            'masteryConfiguration' => $this->masteryConfiguration($learningClass),
            'masteryRuleStatuses' => MasteryRuleStatus::options(),
            'masteryHeatmap' => $this->heatmapWithRemedialUrls($learningClass, 'admin'),
        ]);
    }

    public function edit(LearningClass $learningClass): Response
    {
        $this->authorize('update', $learningClass);

        return Inertia::render('admin/classes/Edit', [
            'learningClass' => [
                'id' => $learningClass->id,
                'course_id' => $learningClass->course_id,
                'name' => $learningClass->name,
                'code' => $learningClass->code,
                'description' => $learningClass->description,
                'start_date' => $learningClass->start_date?->toDateString(),
                'end_date' => $learningClass->end_date?->toDateString(),
                'status' => $learningClass->status->value,
            ],
            'courses' => $this->courseOptions(),
            'programs' => $this->programOptions(),
            'statuses' => LearningClassStatus::options(),
        ]);
    }

    public function update(UpdateLearningClassRequest $request, LearningClass $learningClass): RedirectResponse
    {
        $this->learningClassService->update($learningClass, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Class updated.')]);

        return to_route('admin.classes.show', $learningClass);
    }

    public function destroy(LearningClass $learningClass): RedirectResponse
    {
        $this->authorize('delete', $learningClass);
        $this->learningClassService->delete($learningClass);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Class deleted.')]);

        return to_route('admin.classes.index');
    }

    /**
     * @return array<int, array{id: int, program_id: int, name: string, program: string}>
     */
    private function courseOptions(): array
    {
        return Course::query()->with('program:id,name')->orderBy('name')->get(['id', 'program_id', 'name'])
            ->map(fn (Course $course): array => [
                'id' => $course->id,
                'program_id' => $course->program_id,
                'name' => $course->name,
                'program' => $course->program->name,
            ])->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function programOptions(): array
    {
        return Program::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Program $program): array => [
                'id' => $program->id,
                'name' => $program->name,
            ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function classDetails(LearningClass $learningClass): array
    {
        return [
            'id' => $learningClass->id,
            'name' => $learningClass->name,
            'code' => $learningClass->code,
            'description' => $learningClass->description,
            'course' => $learningClass->course->name,
            'program' => $learningClass->course->program->name,
            'status' => $learningClass->status->value,
            'start_date' => $learningClass->start_date?->toDateString(),
            'end_date' => $learningClass->end_date?->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    private function assessmentAssignment(LearningClassAssessment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'assessment_id' => $assignment->assessment_id,
            'title' => $assignment->assessment->title,
            'purpose' => $assignment->assessment->purpose->value,
            'assessment_status' => $assignment->assessment->status->value,
            'competency' => $assignment->assessment->competency->name,
            'questions_count' => $assignment->assessment->assessment_questions_count ?? 0,
            'total_points' => $assignment->assessment->getAttribute('total_points') ?? '0.00',
            'opens_at' => $assignment->opens_at?->format('Y-m-d\TH:i'),
            'closes_at' => $assignment->closes_at?->format('Y-m-d\TH:i'),
            'max_attempts' => $assignment->max_attempts,
            'status' => $assignment->status->value,
            'feedback_mode' => $assignment->feedback_mode->value,
            'attempts_count' => $assignment->attempts_count ?? 0,
            'attempt_url' => route('admin.class-assessment-attempts.index', [$assignment->learning_class_id, $assignment]),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function masteryConfiguration(LearningClass $learningClass): array
    {
        $competencies = Competency::query()
            ->where('course_id', $learningClass->course_id)
            ->where('status', AcademicStatus::Active->value)
            ->with([
                'prerequisites:id,name',
                'modules' => fn ($query) => $query
                    ->where('status', AcademicStatus::Active->value)
                    ->with(['lessons' => fn ($query) => $query->where('status', AcademicStatus::Active->value)]),
                'masteryRules' => fn ($query) => $query
                    ->where('learning_class_id', $learningClass->id)
                    ->with('remedialLessons:id,title'),
            ])
            ->orderBy('sort_order')
            ->get();
        $assignments = LearningClassAssessment::query()
            ->where('learning_class_id', $learningClass->id)
            ->whereHas('assessment', fn ($query) => $query
                ->where('purpose', AssessmentPurpose::Mastery->value)
                ->where('status', AssessmentStatus::Published->value))
            ->with('assessment:id,competency_id,title')
            ->get()
            ->groupBy(fn (LearningClassAssessment $assignment): int => $assignment->assessment->competency_id);

        return $competencies->map(function (Competency $competency) use ($learningClass, $assignments): array {
            $rule = $competency->masteryRules->first();

            return [
                'id' => $competency->id,
                'name' => $competency->name,
                'prerequisites' => $competency->prerequisites->pluck('name')->values()->all(),
                'assessment_options' => $assignments->get($competency->id, collect())->map(
                    fn (LearningClassAssessment $assignment): array => [
                        'id' => $assignment->id,
                        'title' => $assignment->assessment->title,
                    ],
                )->values()->all(),
                'lesson_options' => $competency->modules->flatMap(
                    fn (Module $module) => $module->lessons,
                )->map(fn (Lesson $lesson): array => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                ])->values()->all(),
                'rule' => $rule === null ? null : [
                    'learning_class_assessment_id' => $rule->learning_class_assessment_id,
                    'mastery_score' => $rule->mastery_score,
                    'require_remedial' => $rule->require_remedial,
                    'status' => $rule->status->value,
                    'remedial_lesson_ids' => $rule->remedialLessons->pluck('id')->all(),
                ],
                'save_url' => route('admin.classes.mastery-rules.update', [$learningClass, $competency]),
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    private function heatmapWithRemedialUrls(LearningClass $learningClass, string $prefix): array
    {
        $heatmap = $this->masteryProgress->heatmap($learningClass);

        foreach ($heatmap['students'] as &$student) {
            foreach ($student['competencies'] as &$cell) {
                $cell['remedial_url'] = $cell['remedial_assignment_id'] === null
                    ? null
                    : route("{$prefix}.remedials.show", $cell['remedial_assignment_id']);
            }
        }

        return $heatmap;
    }
}
