<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AcademicStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreModuleRequest;
use App\Http\Requests\Admin\UpdateModuleRequest;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Module;
use App\Models\Program;
use App\Models\User;
use App\Services\ModuleService;
use App\Services\TutorCourseAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    public function __construct(
        private readonly ModuleService $moduleService,
        private readonly TutorCourseAccessService $tutorCourseAccess,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Module::class);

        $search = trim($request->string('search')->toString());
        $programId = $request->integer('program_id');
        $courseId = $request->integer('course_id');
        $competencyId = $request->integer('competency_id');
        $status = AcademicStatus::tryFrom($request->string('status')->toString());
        $query = Module::query()
            ->with('competency.course.program:id,name')
            ->withCount('lessons');
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $manageableCourseIds = $this->tutorCourseAccess->manageableCourseIds($user);

        if ($user->hasRole('Tutor')) {
            $query->whereHas('competency', fn ($query) => $query->whereIn('course_id', $manageableCourseIds));
        }

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($programId > 0) {
            $query->whereHas(
                'competency.course',
                fn ($query) => $query->where('program_id', $programId),
            );
        }

        if ($courseId > 0) {
            $query->whereHas(
                'competency',
                fn ($query) => $query->where('course_id', $courseId),
            );
        }

        if ($competencyId > 0) {
            $query->where('competency_id', $competencyId);
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $paginator = $query
            ->orderBy('competency_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $adminCanManage = $user->hasRole('Admin') && $user->hasPermissionTo('manage-modules');

        return Inertia::render('admin/modules/Index', [
            'modules' => [
                'data' => $paginator->getCollection()->map(fn (Module $module): array => [
                    'id' => $module->id,
                    'name' => $module->name,
                    'competency' => $module->competency->name,
                    'course' => $module->competency->course->name,
                    'program' => $module->competency->course->program->name,
                    'status' => $module->status->value,
                    'sort_order' => $module->sort_order,
                    'lessons_count' => $module->lessons_count ?? 0,
                    'can_update' => $adminCanManage || in_array($module->competency->course_id, $manageableCourseIds, true),
                    'can_delete' => $adminCanManage || in_array($module->competency->course_id, $manageableCourseIds, true),
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
                'competency_id' => $competencyId > 0 ? (string) $competencyId : '',
                'status' => $status->value ?? '',
            ],
            ...$this->hierarchyOptions($user, true),
            'statuses' => AcademicStatus::options(),
            'canManage' => $adminCanManage || $manageableCourseIds !== [],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Module::class);
        $user = request()->user();
        abort_unless($user instanceof User, 401);

        return Inertia::render('admin/modules/Create', [
            ...$this->hierarchyOptions($user, true),
            'statuses' => AcademicStatus::options(),
        ]);
    }

    public function store(StoreModuleRequest $request): RedirectResponse
    {
        $this->moduleService->create($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Module created.')]);

        return to_route('admin.modules.index');
    }

    public function edit(Module $module): Response
    {
        $this->authorize('update', $module);
        $module->load('competency.course');
        $user = request()->user();
        abort_unless($user instanceof User, 401);

        return Inertia::render('admin/modules/Edit', [
            'module' => [
                'id' => $module->id,
                'program_id' => $module->competency->course->program_id,
                'course_id' => $module->competency->course_id,
                'competency_id' => $module->competency_id,
                'name' => $module->name,
                'slug' => $module->slug,
                'description' => $module->description,
                'sort_order' => $module->sort_order,
                'status' => $module->status->value,
            ],
            ...$this->hierarchyOptions($user, true),
            'statuses' => AcademicStatus::options(),
        ]);
    }

    public function update(UpdateModuleRequest $request, Module $module): RedirectResponse
    {
        $this->moduleService->update($module, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Module updated.')]);

        return to_route('admin.modules.index');
    }

    public function destroy(Module $module): RedirectResponse
    {
        $this->authorize('delete', $module);

        $this->moduleService->delete($module);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Module deleted.')]);

        return to_route('admin.modules.index');
    }

    /**
     * @return array{
     *     programs: array<int, array{id: int, name: string}>,
     *     courses: array<int, array{id: int, program_id: int, name: string}>,
     *     competencies: array<int, array{id: int, course_id: int, name: string, code: string, can_manage: bool}>
     * }
     */
    private function hierarchyOptions(User $user, bool $manageableOnly = false): array
    {
        $manageableCourseIds = $user->hasRole('Admin')
            ? null
            : $this->tutorCourseAccess->manageableCourseIds($user);
        $programQuery = Program::query()->orderBy('name');
        $courseQuery = Course::query()->orderBy('name');
        $competencyQuery = Competency::query()->orderBy('name');

        if ($manageableOnly && $manageableCourseIds !== null) {
            $programQuery->whereHas('courses', fn ($query) => $query->whereIn('id', $manageableCourseIds));
            $courseQuery->whereIn('id', $manageableCourseIds);
            $competencyQuery->whereIn('course_id', $manageableCourseIds);
        }

        return [
            'programs' => $programQuery
                ->get(['id', 'name'])
                ->map(fn (Program $program): array => [
                    'id' => $program->id,
                    'name' => $program->name,
                ])->all(),
            'courses' => $courseQuery
                ->get(['id', 'program_id', 'name'])
                ->map(fn (Course $course): array => [
                    'id' => $course->id,
                    'program_id' => $course->program_id,
                    'name' => $course->name,
                ])->all(),
            'competencies' => $competencyQuery
                ->get(['id', 'course_id', 'name', 'code'])
                ->map(fn (Competency $competency): array => [
                    'id' => $competency->id,
                    'course_id' => $competency->course_id,
                    'name' => $competency->name,
                    'code' => $competency->code,
                    'can_manage' => $manageableCourseIds === null || in_array($competency->course_id, $manageableCourseIds, true),
                ])->all(),
        ];
    }
}
