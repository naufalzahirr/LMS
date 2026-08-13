<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\LearningClassStatus;
use App\Models\LearningClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class TutorLearningAnalyticsQueryService
{
    private const PER_PAGE = 25;

    public function __construct(private readonly LearningAnalyticsMetricService $metrics) {}

    /**
     * @param  array{course_id: int, learning_class_id: int, page: int}  $filters
     * @return array<string, mixed>
     */
    public function forTutor(User $tutor, array $filters): array
    {
        $metrics = $this->metrics->forClasses($this->classes($tutor, $filters));
        $studentRows = $metrics['student_rows'];
        $paginator = new LengthAwarePaginator(
            array_slice($studentRows, ($filters['page'] - 1) * self::PER_PAGE, self::PER_PAGE),
            count($studentRows),
            self::PER_PAGE,
            $filters['page'],
            ['path' => route('tutor.analytics.index')],
        );
        $paginator->withQueryString();

        return [
            'overview' => $metrics['overview'],
            'classes' => $metrics['class_rows'],
            'students' => [
                'data' => array_map(
                    fn (array $row): array => [
                        ...$row,
                        'url' => route('tutor.reports.classes.show', $row['class_id']),
                    ],
                    $paginator->items(),
                ),
                'links' => $paginator->linkCollection()->all(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'competencies' => array_slice($metrics['competencies'], 0, 12),
            'assessments' => array_map(
                fn (array $row): array => [
                    ...$row,
                    'url' => route('tutor.class-assessment-attempts.index', [
                        $row['class_id'],
                        $row['assignment_id'],
                    ]),
                ],
                array_slice($metrics['assessments'], 0, 20),
            ),
        ];
    }

    /**
     * @param  array{course_id: int, learning_class_id: int, page?: int}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function studentRows(User $tutor, array $filters): array
    {
        return $this->metrics->forClasses($this->classes($tutor, [
            ...$filters,
            'page' => $filters['page'] ?? 1,
        ]))['student_rows'];
    }

    /**
     * @param  array{course_id: int, learning_class_id: int, page: int}  $filters
     * @return Collection<int, LearningClass>
     */
    private function classes(User $tutor, array $filters): Collection
    {
        return LearningClass::query()
            ->where('status', LearningClassStatus::Active->value)
            ->whereHas('course', fn (Builder $query) => $query
                ->where('status', AcademicStatus::Active->value)
                ->whereHas('program', fn (Builder $query) => $query->where('status', AcademicStatus::Active->value)))
            ->whereHas('tutors', fn (Builder $query) => $query->whereKey($tutor->id))
            ->when($filters['course_id'] > 0, fn (Builder $query) => $query->where('course_id', $filters['course_id']))
            ->when($filters['learning_class_id'] > 0, fn (Builder $query) => $query->whereKey($filters['learning_class_id']))
            ->with([
                'course.program:id,name',
                'tutors:id,name',
            ])
            ->orderBy('name')
            ->get();
    }
}
