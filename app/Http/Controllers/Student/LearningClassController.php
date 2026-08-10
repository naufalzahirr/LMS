<?php

namespace App\Http\Controllers\Student;

use App\Enums\AcademicStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Http\Controllers\Controller;
use App\Models\Competency;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Services\LearningProgressQueryService;
use App\Services\StudentLearningAccessService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LearningClassController extends Controller
{
    public function __construct(
        private readonly StudentLearningAccessService $access,
        private readonly LearningProgressQueryService $progressQuery,
    ) {}

    public function index(Request $request): Response
    {
        $user = $this->student($request);
        $enrollments = Enrollment::query()
            ->where('student_id', $user->id)
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->whereHas('learningClass', function ($query): void {
                $query->whereIn('status', [LearningClassStatus::Active->value, LearningClassStatus::Completed->value])
                    ->whereHas('course', function ($query): void {
                        $query->where('status', AcademicStatus::Active->value)
                            ->whereHas('program', fn ($query) => $query->where('status', AcademicStatus::Active->value));
                    });
            })
            ->with([
                'learningClass.course.program:id,name',
                'learningClass.tutors:id,name',
            ])
            ->orderByDesc('enrolled_at')
            ->get();
        $summaries = $this->progressQuery->summariesForEnrollments($enrollments);
        [$current, $history] = $enrollments->partition(
            fn (Enrollment $enrollment): bool => $enrollment->status === EnrollmentStatus::Active
                && $enrollment->learningClass->status === LearningClassStatus::Active,
        );

        return Inertia::render('student/classes/Index', [
            'currentClasses' => $this->classCards($current, $summaries),
            'historyClasses' => $this->classCards($history, $summaries),
        ]);
    }

    public function show(Request $request, LearningClass $learningClass): Response
    {
        $user = $this->student($request);
        $enrollment = $this->access->enrollmentForViewing($user, $learningClass);
        abort_unless($enrollment instanceof Enrollment, 403);
        $this->progressQuery->loadActiveHierarchy($learningClass);
        $summaries = $this->progressQuery->summariesForEnrollments(new Collection([$enrollment]));
        $lessonIds = $this->lessonIds($learningClass);
        $progress = LessonProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('lesson_id', $lessonIds)
            ->get()
            ->keyBy('lesson_id');

        return Inertia::render('student/classes/Show', [
            'learningClass' => $this->classDetails($learningClass),
            'enrollment' => [
                'id' => $enrollment->id,
                'status' => $enrollment->status->value,
                'read_only' => ! $this->mayMutate($enrollment, $learningClass),
            ],
            'progress' => $summaries[$enrollment->id],
            'competencies' => $learningClass->course->competencies->map(
                fn (Competency $competency): array => [
                    'id' => $competency->id,
                    'name' => $competency->name,
                    'description' => $competency->description,
                    'modules' => $competency->modules->map(
                        fn (Module $module): array => [
                            'id' => $module->id,
                            'name' => $module->name,
                            'description' => $module->description,
                            'lessons' => $module->lessons->map(function (Lesson $lesson) use ($progress, $learningClass): array {
                                $record = $progress->get($lesson->id);

                                return [
                                    'id' => $lesson->id,
                                    'title' => $lesson->title,
                                    'lesson_type' => $lesson->lesson_type->value,
                                    'duration_minutes' => $lesson->duration_minutes,
                                    'progress_status' => $record?->status->value ?? 'not_started',
                                    'url' => route('student.lessons.show', [$learningClass, $lesson]),
                                ];
                            })->values()->all(),
                        ],
                    )->values()->all(),
                ],
            )->values()->all(),
        ]);
    }

    /**
     * @param  Collection<int, Enrollment>  $enrollments
     * @param  array<int, array{completed_lessons: int, total_lessons: int, percentage: int, continue_lesson_id: int|null}>  $summaries
     * @return array<int, array<string, mixed>>
     */
    private function classCards(Collection $enrollments, array $summaries): array
    {
        return $enrollments->map(function (Enrollment $enrollment) use ($summaries): array {
            $learningClass = $enrollment->learningClass;
            $summary = $summaries[$enrollment->id];
            $continueLessonId = $summary['continue_lesson_id'];

            return [
                ...$this->classDetails($learningClass),
                'enrollment_status' => $enrollment->status->value,
                'tutors' => $learningClass->tutors->pluck('name')->values()->all(),
                'completed_lessons' => $summary['completed_lessons'],
                'total_lessons' => $summary['total_lessons'],
                'percentage' => $summary['percentage'],
                'read_only' => ! $this->mayMutate($enrollment, $learningClass),
                'continue_url' => $continueLessonId === null
                    ? route('student.classes.show', $learningClass)
                    : route('student.lessons.show', [$learningClass, $continueLessonId]),
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
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

    /** @return array<int, int> */
    private function lessonIds(LearningClass $learningClass): array
    {
        return $learningClass->course->competencies
            ->flatMap(fn (Competency $competency) => $competency->modules)
            ->flatMap(fn (Module $module) => $module->lessons)
            ->pluck('id')
            ->all();
    }

    private function mayMutate(Enrollment $enrollment, LearningClass $learningClass): bool
    {
        return $enrollment->status === EnrollmentStatus::Active
            && $learningClass->status === LearningClassStatus::Active;
    }

    private function student(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasRole('Student'), 403);

        return $user;
    }
}
