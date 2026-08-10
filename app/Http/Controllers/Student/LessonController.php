<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Competency;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Services\LearningProgressQueryService;
use App\Services\LessonProgressService;
use App\Services\StudentLearningAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LessonController extends Controller
{
    public function __construct(
        private readonly StudentLearningAccessService $access,
        private readonly LessonProgressService $progressService,
        private readonly LearningProgressQueryService $progressQuery,
    ) {}

    public function show(Request $request, LearningClass $learningClass, Lesson $lesson): Response
    {
        [$user, $enrollment] = $this->context($request, $learningClass, $lesson);
        $canMutate = $this->access->mayMutateProgress($user, $enrollment, $lesson);
        $progress = $canMutate
            ? $this->progressService->view($user, $enrollment, $lesson)
            : LessonProgress::query()->where('enrollment_id', $enrollment->id)->where('lesson_id', $lesson->id)->first();

        $this->progressQuery->loadActiveHierarchy($learningClass);
        $lessons = $learningClass->course->competencies
            ->flatMap(fn (Competency $competency) => $competency->modules)
            ->flatMap(fn (Module $module) => $module->lessons)
            ->values();
        $lessonIndex = $lessons->search(fn (Lesson $candidate): bool => $candidate->is($lesson));
        abort_if($lessonIndex === false, 403);
        $allProgress = LessonProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('lesson_id', $lessons->pluck('id')->all())
            ->get()
            ->keyBy('lesson_id');

        return Inertia::render('student/lessons/Show', [
            'learningClass' => [
                'id' => $learningClass->id,
                'name' => $learningClass->name,
                'course' => $learningClass->course->name,
            ],
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'lesson_type' => $lesson->lesson_type->value,
                'content' => $lesson->content,
                'external_url' => $lesson->external_url,
                'embed_url' => $this->trustedVideoEmbedUrl($lesson->external_url),
                'duration_minutes' => $lesson->duration_minutes,
                'competency' => $lesson->module->competency->name,
                'module' => $lesson->module->name,
                'file_url' => $lesson->managedFilePath() === null
                    ? null
                    : route('student.lessons.file', [$learningClass, $lesson]),
                'file_download_url' => $lesson->managedFilePath() === null
                    ? null
                    : route('student.lessons.file', [$learningClass, $lesson, 'download' => 1]),
                'progress_status' => $progress?->status->value ?? 'not_started',
            ],
            'canMutate' => $canMutate,
            'previousLesson' => $lessonIndex > 0 ? $this->lessonLink($learningClass, $lessons[$lessonIndex - 1]) : null,
            'nextLesson' => $lessonIndex < $lessons->count() - 1 ? $this->lessonLink($learningClass, $lessons[$lessonIndex + 1]) : null,
            'competencies' => $learningClass->course->competencies->map(
                fn (Competency $competency): array => [
                    'id' => $competency->id,
                    'name' => $competency->name,
                    'modules' => $competency->modules->map(
                        fn (Module $module): array => [
                            'id' => $module->id,
                            'name' => $module->name,
                            'lessons' => $module->lessons->map(fn (Lesson $item): array => [
                                'id' => $item->id,
                                'title' => $item->title,
                                'progress_status' => $allProgress->get($item->id)?->status->value ?? 'not_started',
                                'url' => route('student.lessons.show', [$learningClass, $item]),
                            ])->values()->all(),
                        ],
                    )->values()->all(),
                ],
            )->values()->all(),
        ]);
    }

    public function file(Request $request, LearningClass $learningClass, Lesson $lesson): StreamedResponse
    {
        $this->context($request, $learningClass, $lesson);
        abort_unless($lesson->lesson_type->usesUploadedFile(), 404);
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

    /** @return array{User, Enrollment} */
    private function context(Request $request, LearningClass $learningClass, Lesson $lesson): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $enrollment = $this->access->enrollmentForViewing($user, $learningClass);
        abort_unless($enrollment instanceof Enrollment, 403);
        abort_unless($this->access->lessonBelongsToActiveCourse($lesson, $learningClass), 403);
        $lesson->load('module.competency');

        return [$user, $enrollment];
    }

    /** @return array{id: int, title: string, url: string} */
    private function lessonLink(LearningClass $learningClass, Lesson $lesson): array
    {
        return [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'url' => route('student.lessons.show', [$learningClass, $lesson]),
        ];
    }

    private function trustedVideoEmbedUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            $id = str_starts_with($path, 'embed/') ? Str::after($path, 'embed/') : ($query['v'] ?? null);

            return is_string($id) && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) === 1
                ? "https://www.youtube-nocookie.com/embed/{$id}"
                : null;
        }

        if ($host === 'youtu.be' && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $path) === 1) {
            return "https://www.youtube-nocookie.com/embed/{$path}";
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            $id = Str::afterLast($path, '/');

            return ctype_digit($id) ? "https://player.vimeo.com/video/{$id}" : null;
        }

        return null;
    }
}
