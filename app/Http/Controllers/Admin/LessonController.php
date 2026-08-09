<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AcademicStatus;
use App\Enums\LessonType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLessonRequest;
use App\Http\Requests\Admin\UpdateLessonRequest;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Program;
use App\Services\LessonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LessonController extends Controller
{
    public function __construct(private readonly LessonService $lessonService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Lesson::class);

        $search = trim($request->string('search')->toString());
        $programId = $request->integer('program_id');
        $courseId = $request->integer('course_id');
        $competencyId = $request->integer('competency_id');
        $moduleId = $request->integer('module_id');
        $lessonType = LessonType::tryFrom($request->string('lesson_type')->toString());
        $status = AcademicStatus::tryFrom($request->string('status')->toString());
        $query = Lesson::query()->with('module.competency.course.program:id,name');

        if ($search !== '') {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($programId > 0) {
            $query->whereHas(
                'module.competency.course',
                fn ($query) => $query->where('program_id', $programId),
            );
        }

        if ($courseId > 0) {
            $query->whereHas(
                'module.competency',
                fn ($query) => $query->where('course_id', $courseId),
            );
        }

        if ($competencyId > 0) {
            $query->whereHas(
                'module',
                fn ($query) => $query->where('competency_id', $competencyId),
            );
        }

        if ($moduleId > 0) {
            $query->where('module_id', $moduleId);
        }

        if ($lessonType !== null) {
            $query->where('lesson_type', $lessonType->value);
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $paginator = $query
            ->orderBy('module_id')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/lessons/Index', [
            'lessons' => [
                'data' => $paginator->getCollection()->map(fn (Lesson $lesson): array => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'lesson_type' => $lesson->lesson_type->value,
                    'module' => $lesson->module->name,
                    'competency' => $lesson->module->competency->name,
                    'course' => $lesson->module->competency->course->name,
                    'status' => $lesson->status->value,
                    'duration_minutes' => $lesson->duration_minutes,
                    'sort_order' => $lesson->sort_order,
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
                'module_id' => $moduleId > 0 ? (string) $moduleId : '',
                'lesson_type' => $lessonType->value ?? '',
                'status' => $status->value ?? '',
            ],
            ...$this->hierarchyOptions(),
            'lessonTypes' => LessonType::options(),
            'statuses' => AcademicStatus::options(),
            'canManage' => $request->user()?->can('create', Lesson::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Lesson::class);

        return Inertia::render('admin/lessons/Create', [
            ...$this->hierarchyOptions(),
            'lessonTypes' => LessonType::options(),
            'statuses' => AcademicStatus::options(),
        ]);
    }

    public function store(StoreLessonRequest $request): RedirectResponse
    {
        $lesson = $this->lessonService->create($request->payload(), $request->uploadedFile());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lesson created.')]);

        return to_route('admin.lessons.show', $lesson);
    }

    public function show(Lesson $lesson): Response
    {
        $this->authorize('view', $lesson);
        $lesson->load('module.competency.course.program');
        $hasFile = $lesson->managedFilePath() !== null;

        return Inertia::render('admin/lessons/Show', [
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'lesson_type' => $lesson->lesson_type->value,
                'content' => $lesson->content,
                'external_url' => $lesson->external_url,
                'duration_minutes' => $lesson->duration_minutes,
                'status' => $lesson->status->value,
                'module' => $lesson->module->name,
                'competency' => $lesson->module->competency->name,
                'course' => $lesson->module->competency->course->name,
                'program' => $lesson->module->competency->course->program->name,
                'file_url' => $hasFile ? route('admin.lessons.file', $lesson) : null,
                'file_download_url' => $hasFile ? route('admin.lessons.file', [$lesson, 'download' => 1]) : null,
            ],
            'canManage' => request()->user()?->can('update', $lesson) ?? false,
        ]);
    }

    public function edit(Lesson $lesson): Response
    {
        $this->authorize('update', $lesson);
        $lesson->load('module.competency.course');

        return Inertia::render('admin/lessons/Edit', [
            'lesson' => [
                'id' => $lesson->id,
                'program_id' => $lesson->module->competency->course->program_id,
                'course_id' => $lesson->module->competency->course_id,
                'competency_id' => $lesson->module->competency_id,
                'module_id' => $lesson->module_id,
                'title' => $lesson->title,
                'slug' => $lesson->slug,
                'lesson_type' => $lesson->lesson_type->value,
                'content' => $lesson->content,
                'external_url' => $lesson->external_url,
                'has_file' => $lesson->managedFilePath() !== null,
                'duration_minutes' => $lesson->duration_minutes,
                'sort_order' => $lesson->sort_order,
                'status' => $lesson->status->value,
            ],
            ...$this->hierarchyOptions(),
            'lessonTypes' => LessonType::options(),
            'statuses' => AcademicStatus::options(),
        ]);
    }

    public function update(UpdateLessonRequest $request, Lesson $lesson): RedirectResponse
    {
        $this->lessonService->update($lesson, $request->payload(), $request->uploadedFile());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lesson updated.')]);

        return to_route('admin.lessons.show', $lesson);
    }

    public function destroy(Lesson $lesson): RedirectResponse
    {
        $this->authorize('delete', $lesson);

        $this->lessonService->delete($lesson);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lesson deleted.')]);

        return to_route('admin.lessons.index');
    }

    public function file(Request $request, Lesson $lesson): StreamedResponse
    {
        $this->authorize('view', $lesson);

        $path = $lesson->managedFilePath();
        abort_if($path === null, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $filename = Str::slug($lesson->title).($extension !== '' ? ".{$extension}" : '');

        return $request->boolean('download')
            ? $disk->download($path, $filename)
            : $disk->response($path, $filename);
    }

    /**
     * @return array{
     *     programs: array<int, array{id: int, name: string}>,
     *     courses: array<int, array{id: int, program_id: int, name: string}>,
     *     competencies: array<int, array{id: int, course_id: int, name: string, code: string}>,
     *     modules: array<int, array{id: int, competency_id: int, name: string}>
     * }
     */
    private function hierarchyOptions(): array
    {
        return [
            'programs' => Program::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Program $program): array => ['id' => $program->id, 'name' => $program->name])->all(),
            'courses' => Course::query()->orderBy('name')->get(['id', 'program_id', 'name'])
                ->map(fn (Course $course): array => ['id' => $course->id, 'program_id' => $course->program_id, 'name' => $course->name])->all(),
            'competencies' => Competency::query()->orderBy('name')->get(['id', 'course_id', 'name', 'code'])
                ->map(fn (Competency $competency): array => [
                    'id' => $competency->id,
                    'course_id' => $competency->course_id,
                    'name' => $competency->name,
                    'code' => $competency->code,
                ])->all(),
            'modules' => Module::query()->orderBy('name')->get(['id', 'competency_id', 'name'])
                ->map(fn (Module $module): array => [
                    'id' => $module->id,
                    'competency_id' => $module->competency_id,
                    'name' => $module->name,
                ])->all(),
        ];
    }
}
