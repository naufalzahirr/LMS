<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AcademicStatus;
use App\Enums\LearningClassStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LearningClass;
use App\Models\Program;
use App\Models\User;
use App\Services\AdminLearningAnalyticsQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LearningAnalyticsController extends Controller
{
    public function __construct(private readonly AdminLearningAnalyticsQueryService $analytics) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAllProgressReports', LearningClass::class);
        $filters = $this->filters($request);

        return Inertia::render('admin/analytics/Index', [
            'analytics' => $this->analytics->forAdmin($filters),
            'filters' => $this->filterPayload($filters),
            'programs' => Program::query()
                ->where('status', AcademicStatus::Active->value)
                ->orderBy('name')
                ->get(['id', 'name']),
            'courses' => Course::query()
                ->where('status', AcademicStatus::Active->value)
                ->whereHas('program', fn ($query) => $query->where('status', AcademicStatus::Active->value))
                ->orderBy('name')
                ->get(['id', 'program_id', 'name']),
            'classes' => LearningClass::query()
                ->where('status', LearningClassStatus::Active->value)
                ->whereHas('course', fn ($query) => $query
                    ->where('status', AcademicStatus::Active->value)
                    ->whereHas('program', fn ($query) => $query->where('status', AcademicStatus::Active->value)))
                ->orderBy('name')
                ->get(['id', 'course_id', 'name']),
            'tutors' => User::role('Tutor')
                ->whereHas('teachingClasses', fn ($query) => $query->where('learning_classes.status', LearningClassStatus::Active->value))
                ->orderBy('name')
                ->get(['id', 'name']),
            'csvUrl' => route('admin.analytics.csv', array_filter($this->filterPayload($filters))),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $this->authorize('viewAllProgressReports', LearningClass::class);
        $rows = $this->analytics->classRows($this->filters($request));

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'Learning Class',
                'Course',
                'Program',
                'Tutors',
                'Active Students',
                'Lessons Completed',
                'Accessible Lesson Cells',
                'Competencies Mastered',
                'Eligible Competency Cells',
                'Students Needing Remedial',
                'Remedial Competency Cases',
                'Assessment Submissions',
                'Eligible Assessment Cells',
                'Graded Students',
                'Assessment Average',
            ]);

            foreach ($rows as $row) {
                fputcsv($output, array_map($this->safeCsvValue(...), [
                    $row['name'],
                    $row['course'],
                    $row['program'],
                    implode('; ', $row['tutors']),
                    $row['active_students'],
                    $row['completed_lessons'],
                    $row['total_lessons'],
                    $row['competencies_mastered'],
                    $row['competencies_total'],
                    $row['students_needing_remedial'],
                    $row['remedial_cases'],
                    $row['assessment_submitted'],
                    $row['assessment_eligible'],
                    $row['assessment_graded'],
                    $row['assessment_average'],
                ]));
            }

            fclose($output);
        }, 'learning-class-analytics.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{program_id: int, course_id: int, learning_class_id: int, tutor_id: int, page: int} */
    private function filters(Request $request): array
    {
        return [
            'program_id' => max(0, $request->integer('program_id')),
            'course_id' => max(0, $request->integer('course_id')),
            'learning_class_id' => max(0, $request->integer('learning_class_id')),
            'tutor_id' => max(0, $request->integer('tutor_id')),
            'page' => max(1, $request->integer('page', 1)),
        ];
    }

    /**
     * @param  array{program_id: int, course_id: int, learning_class_id: int, tutor_id: int, page: int}  $filters
     * @return array<string, string>
     */
    private function filterPayload(array $filters): array
    {
        return [
            'program_id' => $filters['program_id'] === 0 ? '' : (string) $filters['program_id'],
            'course_id' => $filters['course_id'] === 0 ? '' : (string) $filters['course_id'],
            'learning_class_id' => $filters['learning_class_id'] === 0 ? '' : (string) $filters['learning_class_id'],
            'tutor_id' => $filters['tutor_id'] === 0 ? '' : (string) $filters['tutor_id'],
        ];
    }

    private function safeCsvValue(mixed $value): string
    {
        $value = $value === null ? '' : (string) $value;

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }
}
