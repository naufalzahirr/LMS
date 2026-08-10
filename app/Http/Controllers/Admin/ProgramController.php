<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AcademicStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProgramRequest;
use App\Http\Requests\Admin\UpdateProgramRequest;
use App\Models\Program;
use App\Services\ProgramService;
use App\Services\TutorCourseAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    public function __construct(
        private readonly ProgramService $programService,
        private readonly TutorCourseAccessService $tutorAccess,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Program::class);

        $search = trim($request->string('search')->toString());
        $status = AcademicStatus::tryFrom($request->string('status')->toString());
        $courseIds = $this->tutorAccess->manageableCourseIds($request->user());
        $query = Program::query();

        if ($request->user()->hasRole('Tutor')) {
            $query->whereHas('courses', fn ($query) => $query->whereIn('id', $courseIds))
                ->withCount(['courses' => fn ($query) => $query->whereIn('id', $courseIds)]);
        } else {
            $query->withCount('courses');
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $paginator = $query->orderBy('name')->paginate(10)->withQueryString();

        return Inertia::render('admin/programs/Index', [
            'programs' => [
                'data' => $paginator->getCollection()->map(fn (Program $program): array => [
                    'id' => $program->id,
                    'name' => $program->name,
                    'code' => $program->code,
                    'courses_count' => $program->courses_count ?? 0,
                    'status' => $program->status->value,
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
            'statuses' => AcademicStatus::options(),
            'canManage' => $request->user()?->can('create', Program::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Program::class);

        return Inertia::render('admin/programs/Create', [
            'statuses' => AcademicStatus::options(),
        ]);
    }

    public function store(StoreProgramRequest $request): RedirectResponse
    {
        $this->programService->create($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Program created.')]);

        return to_route('admin.programs.index');
    }

    public function edit(Program $program): Response
    {
        $this->authorize('update', $program);

        return Inertia::render('admin/programs/Edit', [
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'slug' => $program->slug,
                'code' => $program->code,
                'description' => $program->description,
                'status' => $program->status->value,
            ],
            'statuses' => AcademicStatus::options(),
        ]);
    }

    public function update(UpdateProgramRequest $request, Program $program): RedirectResponse
    {
        $this->programService->update($program, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Program updated.')]);

        return to_route('admin.programs.index');
    }

    public function destroy(Program $program): RedirectResponse
    {
        $this->authorize('delete', $program);

        $this->programService->delete($program);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Program deleted.')]);

        return to_route('admin.programs.index');
    }
}
