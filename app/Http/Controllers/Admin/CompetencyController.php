<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AcademicStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompetencyRequest;
use App\Http\Requests\Admin\UpdateCompetencyRequest;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Program;
use App\Models\User;
use App\Services\CompetencyService;
use App\Services\TutorCourseAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompetencyController extends Controller
{
    public function __construct(
        private readonly CompetencyService $competencyService,
        private readonly TutorCourseAccessService $tutorAccess,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Competency::class);

        $search = trim($request->string('search')->toString());
        $programId = $request->integer('program_id');
        $courseId = $request->integer('course_id');
        $status = AcademicStatus::tryFrom($request->string('status')->toString());
        $query = Competency::query()->with('course.program:id,name');
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if ($user->hasRole('Tutor')) {
            $query->whereIn('course_id', $this->tutorAccess->manageableCourseIds($user));
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($programId > 0) {
            $query->whereHas('course', fn ($query) => $query->where('program_id', $programId));
        }

        if ($courseId > 0) {
            $query->where('course_id', $courseId);
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $paginator = $query
            ->orderBy('course_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/competencies/Index', [
            'competencies' => [
                'data' => $paginator->getCollection()->map(fn (Competency $competency): array => [
                    'id' => $competency->id,
                    'code' => $competency->code,
                    'name' => $competency->name,
                    'course' => $competency->course->name,
                    'program' => $competency->course->program->name,
                    'status' => $competency->status->value,
                    'sort_order' => $competency->sort_order,
                ])->all(),
                'links' => $paginator->linkCollection()->all(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'search' => $search,
                'program_id' => $programId > 0 ? (string) $programId : '',
                'course_id' => $courseId > 0 ? (string) $courseId : '',
                'status' => $status->value ?? '',
            ],
            'programs' => $this->programs(),
            'courses' => $this->courses(),
            'statuses' => AcademicStatus::options(),
            'canManage' => $request->user()?->can('create', Competency::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Competency::class);

        return Inertia::render('admin/competencies/Create', [
            'courses' => $this->courses(),
            'statuses' => AcademicStatus::options(),
        ]);
    }

    public function store(StoreCompetencyRequest $request): RedirectResponse
    {
        $this->competencyService->create($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Competency created.')]);

        return to_route('admin.competencies.index');
    }

    public function edit(Competency $competency): Response
    {
        $this->authorize('update', $competency);
        $competency->load('prerequisites:id,name,course_id');

        return Inertia::render('admin/competencies/Edit', [
            'competency' => [
                'id' => $competency->id,
                'course_id' => $competency->course_id,
                'code' => $competency->code,
                'name' => $competency->name,
                'slug' => $competency->slug,
                'description' => $competency->description,
                'learning_objectives' => $competency->learning_objectives,
                'sort_order' => $competency->sort_order,
                'status' => $competency->status->value,
            ],
            'courses' => $this->courses(),
            'statuses' => AcademicStatus::options(),
            'prerequisites' => $competency->prerequisites->map(fn (Competency $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'destroy_url' => route('admin.competencies.prerequisites.destroy', [$competency, $item]),
            ])->all(),
            'prerequisiteOptions' => Competency::query()
                ->where('course_id', $competency->course_id)
                ->whereKeyNot($competency->id)
                ->whereNotIn('id', $competency->prerequisites->pluck('id'))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Competency $item): array => ['id' => $item->id, 'name' => $item->name])
                ->all(),
            'prerequisiteStoreUrl' => route('admin.competencies.prerequisites.store', $competency),
        ]);
    }

    public function update(UpdateCompetencyRequest $request, Competency $competency): RedirectResponse
    {
        $this->competencyService->update($competency, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Competency updated.')]);

        return to_route('admin.competencies.index');
    }

    public function destroy(Competency $competency): RedirectResponse
    {
        $this->authorize('delete', $competency);

        $this->competencyService->delete($competency);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Competency deleted.')]);

        return to_route('admin.competencies.index');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function programs(): array
    {
        $query = Program::query()->orderBy('name');
        $user = request()->user();

        if ($user instanceof User && $user->hasRole('Tutor')) {
            $courseIds = $this->tutorAccess->manageableCourseIds($user);
            $query->whereHas('courses', fn ($query) => $query->whereIn('id', $courseIds));
        }

        return $query
            ->get(['id', 'name'])
            ->map(fn (Program $program): array => [
                'id' => $program->id,
                'name' => $program->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, program_id: int, name: string, program: string}>
     */
    private function courses(): array
    {
        $query = Course::query()->with('program:id,name')->orderBy('name');
        $user = request()->user();

        if ($user instanceof User && $user->hasRole('Tutor')) {
            $query->whereIn('id', $this->tutorAccess->manageableCourseIds($user));
        }

        return $query
            ->get(['id', 'program_id', 'name'])
            ->map(fn (Course $course): array => [
                'id' => $course->id,
                'program_id' => $course->program_id,
                'name' => $course->name,
                'program' => $course->program->name,
            ])
            ->all();
    }
}
