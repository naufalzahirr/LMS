<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AcademicStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCourseRequest;
use App\Http\Requests\Admin\UpdateCourseRequest;
use App\Models\Course;
use App\Models\Program;
use App\Services\CourseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function __construct(private readonly CourseService $courseService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Course::class);

        $search = trim($request->string('search')->toString());
        $programId = $request->integer('program_id');
        $status = AcademicStatus::tryFrom($request->string('status')->toString());
        $query = Course::query()->with('program:id,name')->withCount('competencies');

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($programId > 0) {
            $query->where('program_id', $programId);
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $paginator = $query
            ->orderBy('program_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/courses/Index', [
            'courses' => [
                'data' => $paginator->getCollection()->map(fn (Course $course): array => [
                    'id' => $course->id,
                    'name' => $course->name,
                    'program' => $course->program->name,
                    'code' => $course->code,
                    'competencies_count' => $course->competencies_count ?? 0,
                    'status' => $course->status->value,
                ])->all(),
                'links' => $paginator->linkCollection()->all(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'search' => $search,
                'program_id' => $programId > 0 ? (string) $programId : '',
                'status' => $status->value ?? '',
            ],
            'programs' => $this->programs(),
            'statuses' => AcademicStatus::options(),
            'canManage' => $request->user()?->can('create', Course::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Course::class);

        return Inertia::render('admin/courses/Create', [
            'programs' => $this->programs(),
            'statuses' => AcademicStatus::options(),
        ]);
    }

    public function store(StoreCourseRequest $request): RedirectResponse
    {
        $this->courseService->create($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Course created.')]);

        return to_route('admin.courses.index');
    }

    public function edit(Course $course): Response
    {
        $this->authorize('update', $course);

        return Inertia::render('admin/courses/Edit', [
            'course' => [
                'id' => $course->id,
                'program_id' => $course->program_id,
                'name' => $course->name,
                'slug' => $course->slug,
                'code' => $course->code,
                'description' => $course->description,
                'status' => $course->status->value,
                'sort_order' => $course->sort_order,
            ],
            'programs' => $this->programs(),
            'statuses' => AcademicStatus::options(),
        ]);
    }

    public function update(UpdateCourseRequest $request, Course $course): RedirectResponse
    {
        $this->courseService->update($course, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Course updated.')]);

        return to_route('admin.courses.index');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $this->authorize('delete', $course);

        $this->courseService->delete($course);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Course deleted.')]);

        return to_route('admin.courses.index');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function programs(): array
    {
        return Program::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Program $program): array => [
                'id' => $program->id,
                'name' => $program->name,
            ])
            ->all();
    }
}
