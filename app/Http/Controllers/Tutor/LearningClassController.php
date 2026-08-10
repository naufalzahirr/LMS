<?php

namespace App\Http\Controllers\Tutor;

use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use App\Models\User;
use App\Services\LearningProgressQueryService;
use App\Services\MasteryProgressQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LearningClassController extends Controller
{
    public function __construct(
        private readonly LearningProgressQueryService $progressQuery,
        private readonly MasteryProgressQueryService $masteryProgress,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LearningClass::class);

        $search = trim($request->string('search')->toString());
        $status = LearningClassStatus::tryFrom($request->string('status')->toString());
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $query = LearningClass::query()
            ->whereHas('tutors', fn ($query) => $query->whereKey($user->id))
            ->with('course.program:id,name')
            ->withCount([
                'enrollments as active_students_count' => fn ($query) => $query->where('status', EnrollmentStatus::Active->value),
            ]);

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('learning_classes.name', 'like', "%{$search}%")
                    ->orWhere('learning_classes.code', 'like', "%{$search}%");
            });
        }

        if ($status !== null) {
            $query->where('learning_classes.status', $status->value);
        }

        $paginator = $query->orderByDesc('learning_classes.start_date')->paginate(10)->withQueryString();

        return Inertia::render('tutor/classes/Index', [
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
                ])->all(),
                'links' => $paginator->linkCollection()->all(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status->value ?? '',
            ],
            'statuses' => LearningClassStatus::options(),
        ]);
    }

    public function show(LearningClass $learningClass): Response
    {
        $this->authorize('view', $learningClass);
        $learningClass->load([
            'course.program:id,name',
            'course.competencies.modules.lessons',
            'enrollments.student:id,name,email',
            'assessmentAssignments' => fn ($query) => $query
                ->withCount('attempts')
                ->with(['assessment' => fn ($query) => $query
                    ->with('competency:id,course_id,name')
                    ->withCount('assessmentQuestions')
                    ->withSum('assessmentQuestions as total_points', 'points')]),
        ]);
        $progress = $this->progressQuery->summariesForEnrollments($learningClass->enrollments);

        return Inertia::render('tutor/classes/Show', [
            'learningClass' => [
                'id' => $learningClass->id,
                'name' => $learningClass->name,
                'code' => $learningClass->code,
                'description' => $learningClass->description,
                'course' => $learningClass->course->name,
                'program' => $learningClass->course->program->name,
                'status' => $learningClass->status->value,
                'start_date' => $learningClass->start_date?->toDateString(),
                'end_date' => $learningClass->end_date?->toDateString(),
            ],
            'enrollments' => $learningClass->enrollments->map(fn (Enrollment $enrollment): array => [
                'id' => $enrollment->id,
                'student' => [
                    'name' => $enrollment->student->name,
                    'email' => $enrollment->student->email,
                ],
                'status' => $enrollment->status->value,
                'completed_lessons' => $progress[$enrollment->id]['completed_lessons'],
                'total_lessons' => $progress[$enrollment->id]['total_lessons'],
                'progress_percentage' => $progress[$enrollment->id]['percentage'],
            ])->values()->all(),
            'contentSummary' => [
                'competencies' => $learningClass->course->competencies->count(),
                'modules' => $learningClass->course->competencies->sum(fn ($competency): int => $competency->modules->count()),
                'lessons' => $learningClass->course->competencies->sum(
                    fn ($competency): int => $competency->modules->sum(fn ($module): int => $module->lessons->count()),
                ),
            ],
            'assessmentAssignments' => $learningClass->assessmentAssignments->map(
                fn (LearningClassAssessment $assignment): array => [
                    'id' => $assignment->id,
                    'title' => $assignment->assessment->title,
                    'purpose' => $assignment->assessment->purpose->value,
                    'competency' => $assignment->assessment->competency->name,
                    'questions_count' => $assignment->assessment->assessment_questions_count ?? 0,
                    'total_points' => $assignment->assessment->getAttribute('total_points') ?? '0.00',
                    'opens_at' => $assignment->opens_at?->toDateTimeString(),
                    'closes_at' => $assignment->closes_at?->toDateTimeString(),
                    'max_attempts' => $assignment->max_attempts,
                    'status' => $assignment->status->value,
                    'feedback_mode' => $assignment->feedback_mode->value,
                    'attempts_count' => $assignment->attempts_count ?? 0,
                    'attempt_url' => route('tutor.class-assessment-attempts.index', [$learningClass, $assignment]),
                ],
            )->values()->all(),
            'masteryHeatmap' => $this->heatmap($learningClass),
        ]);
    }

    /** @return array<string, mixed> */
    private function heatmap(LearningClass $learningClass): array
    {
        $heatmap = $this->masteryProgress->heatmap($learningClass);

        foreach ($heatmap['students'] as &$student) {
            foreach ($student['competencies'] as &$cell) {
                $cell['remedial_url'] = $cell['remedial_assignment_id'] === null
                    ? null
                    : route('tutor.remedials.show', $cell['remedial_assignment_id']);
            }
        }

        return $heatmap;
    }
}
