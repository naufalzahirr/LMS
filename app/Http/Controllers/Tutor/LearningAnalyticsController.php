<?php

namespace App\Http\Controllers\Tutor;

use App\Enums\AcademicStatus;
use App\Enums\LearningClassStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LearningClass;
use App\Models\User;
use App\Services\TutorLearningAnalyticsQueryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LearningAnalyticsController extends Controller
{
    public function __construct(private readonly TutorLearningAnalyticsQueryService $analytics) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LearningClass::class);
        $tutor = $request->user();
        abort_unless($tutor instanceof User, 401);
        $filters = $this->filters($request);
        $classes = $this->assignedClasses($tutor);

        return Inertia::render('tutor/analytics/Index', [
            'analytics' => $this->analytics->forTutor($tutor, $filters),
            'filters' => $this->filterPayload($filters),
            'courses' => Course::query()
                ->whereIn('id', $classes->pluck('course_id')->unique())
                ->orderBy('name')
                ->get(['id', 'name']),
            'classes' => $classes,
            'csvUrl' => route('tutor.analytics.csv', array_filter($this->filterPayload($filters))),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', LearningClass::class);
        $tutor = $request->user();
        abort_unless($tutor instanceof User, 401);
        $rows = $this->analytics->studentRows($tutor, $this->filters($request));

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'Student',
                'Email',
                'Learning Class',
                'Course',
                'Lessons Completed',
                'Accessible Lessons',
                'Competencies Mastered',
                'Active Competencies',
                'Remedial Competency Cases',
                'Assessment Submissions',
                'Eligible Assessments',
                'Graded Assessments',
                'Assessment Average',
            ]);

            foreach ($rows as $row) {
                fputcsv($output, array_map($this->safeCsvValue(...), [
                    $row['student'],
                    $row['email'],
                    $row['class'],
                    $row['course'],
                    $row['completed_lessons'],
                    $row['total_lessons'],
                    $row['competencies_mastered'],
                    $row['competencies_total'],
                    $row['remedial_cases'],
                    $row['assessment_submitted'],
                    $row['assessment_eligible'],
                    $row['assessment_graded'],
                    $row['assessment_average'],
                ]));
            }

            fclose($output);
        }, 'student-progress-analytics.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return Collection<int, LearningClass> */
    private function assignedClasses(User $tutor)
    {
        return LearningClass::query()
            ->where('status', LearningClassStatus::Active->value)
            ->whereHas('course', fn ($query) => $query
                ->where('status', AcademicStatus::Active->value)
                ->whereHas('program', fn ($query) => $query->where('status', AcademicStatus::Active->value)))
            ->whereHas('tutors', fn ($query) => $query->whereKey($tutor->id))
            ->orderBy('name')
            ->get(['id', 'course_id', 'name']);
    }

    /** @return array{course_id: int, learning_class_id: int, page: int} */
    private function filters(Request $request): array
    {
        return [
            'course_id' => max(0, $request->integer('course_id')),
            'learning_class_id' => max(0, $request->integer('learning_class_id')),
            'page' => max(1, $request->integer('page', 1)),
        ];
    }

    /**
     * @param  array{course_id: int, learning_class_id: int, page: int}  $filters
     * @return array<string, string>
     */
    private function filterPayload(array $filters): array
    {
        return [
            'course_id' => $filters['course_id'] === 0 ? '' : (string) $filters['course_id'],
            'learning_class_id' => $filters['learning_class_id'] === 0 ? '' : (string) $filters['learning_class_id'],
        ];
    }

    private function safeCsvValue(mixed $value): string
    {
        $value = $value === null ? '' : (string) $value;

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }
}
