<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Competency;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\LessonAsset;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Services\CompetencyAccessService;
use App\Services\LearningProgressQueryService;
use App\Services\LessonContentMigrationService;
use App\Services\LessonContentService;
use App\Services\LessonProgressService;
use App\Services\MasteryProgressQueryService;
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
        private readonly CompetencyAccessService $competencyAccess,
        private readonly MasteryProgressQueryService $masteryProgress,
        private readonly LessonContentService $lessonContent,
        private readonly LessonContentMigrationService $contentMigration,
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
        $masteryStates = collect($this->masteryProgress->studentCompetencies($learningClass, $enrollment))->keyBy('id');
        $accessibleLessons = $lessons->filter(fn (Lesson $candidate): bool => (bool) ($masteryStates
            ->get($candidate->module->competency_id)['unlocked'] ?? true))->values();
        $lessonIndex = $accessibleLessons->search(fn (Lesson $candidate): bool => $candidate->is($lesson));
        abort_if($lessonIndex === false, 403);
        $allProgress = LessonProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('lesson_id', $lessons->pluck('id')->all())
            ->get()
            ->keyBy('lesson_id');
        $lesson = $this->contentMigration->migrateLesson($lesson);
        $lesson->load('module.competency');
        $completedLessons = $allProgress->filter(
            fn (LessonProgress $record): bool => $record->status->value === 'completed',
        )->count();
        $totalLessons = $lessons->count();

        return Inertia::render('student/lessons/Show', [
            'learningClass' => [
                'id' => $learningClass->id,
                'name' => $learningClass->name,
                'course' => $learningClass->course->name,
                'completed_lessons' => $completedLessons,
                'total_lessons' => $totalLessons,
                'progress_percentage' => $totalLessons === 0 ? 0 : (int) round(($completedLessons / $totalLessons) * 100),
            ],
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'lesson_type' => $lesson->lesson_type->value,
                'content' => $lesson->content,
                'external_url' => $lesson->external_url,
                'embed_url' => null,
                'duration_minutes' => $lesson->duration_minutes,
                'competency' => $lesson->module->competency->name,
                'module' => $lesson->module->name,
                'file_url' => $lesson->managedFilePath() === null
                    ? null
                    : route('student.lessons.file', [$learningClass, $lesson]),
                'file_download_url' => $lesson->managedFilePath() === null
                    ? null
                    : route('student.lessons.file', [$learningClass, $lesson, 'download' => 1]),
                'content_document' => $this->lessonContent->forRendering(
                    $lesson,
                    fn (LessonAsset $asset): array => [
                        'url' => route('student.lesson-assets.file', [$learningClass, $lesson, $asset]),
                        'downloadUrl' => route('student.lesson-assets.file', [$learningClass, $lesson, $asset, 'download' => 1]),
                    ],
                ),
                'progress_status' => $progress?->status->value ?? 'not_started',
            ],
            'canMutate' => $canMutate,
            'previousLesson' => $lessonIndex > 0 ? $this->lessonLink($learningClass, $accessibleLessons[$lessonIndex - 1]) : null,
            'nextLesson' => $lessonIndex < $accessibleLessons->count() - 1 ? $this->lessonLink($learningClass, $accessibleLessons[$lessonIndex + 1]) : null,
            'competencies' => $learningClass->course->competencies->map(
                function (Competency $competency) use ($masteryStates, $allProgress, $learningClass): array {
                    $mastery = $masteryStates->get($competency->id);
                    $unlocked = (bool) ($mastery['unlocked'] ?? true);

                    return [
                        'id' => $competency->id,
                        'name' => $competency->name,
                        'unlocked' => $unlocked,
                        'mastery_status' => $mastery['status'] ?? 'learning',
                        'prerequisites' => $mastery['prerequisites'] ?? [],
                        'missing_prerequisites' => $mastery['missing_prerequisites'] ?? [],
                        'latest_score' => $mastery['latest_score'] ?? null,
                        'best_score' => $mastery['best_score'] ?? null,
                        'required_score' => $mastery['required_score'] ?? null,
                        'remedial_url' => ($mastery['remedial_assignment_id'] ?? null) === null
                            ? null
                            : route('student.remedials.show', $mastery['remedial_assignment_id']),
                        'modules' => $competency->modules->map(
                            fn (Module $module): array => [
                                'id' => $module->id,
                                'name' => $module->name,
                                'lessons' => $module->lessons->map(fn (Lesson $item): array => [
                                    'id' => $item->id,
                                    'title' => $item->title,
                                    'progress_status' => $allProgress->get($item->id)?->status->value ?? 'not_started',
                                    'url' => $unlocked ? route('student.lessons.show', [$learningClass, $item]) : null,
                                ])->values()->all(),
                            ],
                        )->values()->all(),
                    ];
                },
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

        $headers = [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ];

        return $request->boolean('download')
            ? $disk->download($path, $filename, $headers)
            : $disk->response($path, $filename, $headers);
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
        abort_unless($this->competencyAccess->mayOpenLesson($enrollment, $lesson), 403);

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
}
