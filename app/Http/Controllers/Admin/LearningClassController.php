<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLearningClassRequest;
use App\Http\Requests\Admin\UpdateLearningClassRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\Program;
use App\Models\User;
use App\Services\LearningClassService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LearningClassController extends Controller
{
    public function __construct(private readonly LearningClassService $learningClassService) {}

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
        ]);
        $assignedTutorIds = $learningClass->tutors->pluck('id');
        $enrolledStudentIds = $learningClass->enrollments->pluck('student_id');

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
}
