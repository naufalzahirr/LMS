<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StudentCompetencyStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LearningClass;
use App\Models\Program;
use App\Models\User;
use App\Services\ProgressReportQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgressReportController extends Controller
{
    public function __construct(private readonly ProgressReportQueryService $reports) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAllProgressReports', LearningClass::class);
        $filters = [
            'program_id' => $request->integer('program_id'),
            'course_id' => $request->integer('course_id'),
            'learning_class_id' => $request->integer('learning_class_id'),
            'student_id' => $request->integer('student_id'),
            'mastery_status' => $request->string('mastery_status')->toString(),
            'page' => max(1, $request->integer('page', 1)),
        ];

        if (! in_array($filters['mastery_status'], $this->masteryStatuses(), true)) {
            $filters['mastery_status'] = '';
        }

        return Inertia::render('admin/reports/Progress', [
            'report' => $this->reports->overview($filters),
            'filters' => array_map(
                fn (int|string $value): string => $value === 0 ? '' : (string) $value,
                array_diff_key($filters, ['page' => true]),
            ),
            'programs' => Program::query()->orderBy('name')->get(['id', 'name']),
            'courses' => Course::query()->with('program:id,name')->orderBy('name')->get(['id', 'program_id', 'name']),
            'classes' => LearningClass::query()->with('course:id,name')->orderBy('name')->get(['id', 'course_id', 'name']),
            'students' => $filters['student_id'] > 0
                ? User::role('Student')->whereKey($filters['student_id'])->get(['id', 'name', 'email'])
                : [],
            'studentSearchUrl' => route('admin.reports.students'),
            'masteryStatuses' => $this->masteryStatuses(),
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        $this->authorize('viewAllProgressReports', LearningClass::class);
        $search = trim($request->string('search')->toString());

        if (mb_strlen($search) < 2) {
            return response()->json(['students' => []]);
        }

        $students = User::role('Student')
            ->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json(['students' => $students]);
    }

    public function show(LearningClass $learningClass): Response
    {
        $this->authorize('viewAllProgressReports', LearningClass::class);

        return Inertia::render('reports/ClassProgress', [
            'report' => $this->reports->classReport($learningClass),
            'scope' => 'admin',
            'backUrl' => route('admin.reports.progress.index'),
            'csvUrl' => route('admin.reports.classes.progress.csv', $learningClass),
        ]);
    }

    public function csv(LearningClass $learningClass): StreamedResponse
    {
        $this->authorize('viewAllProgressReports', LearningClass::class);
        $rows = $this->reports->classCsvRows($learningClass);

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, ['Student', 'Email', 'Class', 'Competency', 'Status', 'Latest', 'Best', 'Required', 'Mastered At']);

            foreach ($rows as $row) {
                fputcsv($output, array_map($this->safeCsvValue(...), $row));
            }

            fclose($output);
        }, "{$learningClass->code}-progress.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<int, string> */
    private function masteryStatuses(): array
    {
        return [
            'locked',
            ...array_map(
                fn (StudentCompetencyStatus $status): string => $status->value,
                StudentCompetencyStatus::cases(),
            ),
        ];
    }

    private function safeCsvValue(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }
}
