<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\LearningClassStatus;
use App\Models\LearningClass;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminLearningAnalyticsQueryService
{
    private const PER_PAGE = 15;

    public function __construct(private readonly LearningAnalyticsMetricService $metrics) {}

    /**
     * @param  array{program_id: int, course_id: int, learning_class_id: int, tutor_id: int, page: int}  $filters
     * @return array<string, mixed>
     */
    public function forAdmin(array $filters): array
    {
        $metrics = $this->metrics->forClasses($this->classes($filters));
        $classRows = $metrics['class_rows'];
        $paginator = new LengthAwarePaginator(
            array_slice($classRows, ($filters['page'] - 1) * self::PER_PAGE, self::PER_PAGE),
            count($classRows),
            self::PER_PAGE,
            $filters['page'],
            ['path' => route('admin.analytics.index')],
        );
        $paginator->withQueryString();

        return [
            'overview' => $metrics['overview'],
            'classes' => [
                'data' => $paginator->items(),
                'links' => $paginator->linkCollection()->all(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'competencies' => array_slice($metrics['competencies'], 0, 10),
            'remedial_concentration' => collect($classRows)
                ->filter(fn (array $row): bool => $row['students_needing_remedial'] > 0)
                ->sortByDesc(fn (array $row): float => $row['active_students'] === 0
                    ? 0.0
                    : $row['students_needing_remedial'] / $row['active_students'])
                ->take(8)
                ->values()
                ->all(),
            'assessments' => array_slice($metrics['assessments'], 0, 12),
        ];
    }

    /**
     * @param  array{program_id: int, course_id: int, learning_class_id: int, tutor_id: int, page?: int}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function classRows(array $filters): array
    {
        return $this->metrics->forClasses($this->classes([
            ...$filters,
            'page' => $filters['page'] ?? 1,
        ]))['class_rows'];
    }

    /**
     * @param  array{program_id: int, course_id: int, learning_class_id: int, tutor_id: int, page: int}  $filters
     * @return Collection<int, LearningClass>
     */
    private function classes(array $filters): Collection
    {
        return LearningClass::query()
            ->where('status', LearningClassStatus::Active->value)
            ->whereHas('course', fn (Builder $query) => $query
                ->where('status', AcademicStatus::Active->value)
                ->whereHas('program', fn (Builder $query) => $query->where('status', AcademicStatus::Active->value)))
            ->when($filters['program_id'] > 0, fn (Builder $query) => $query
                ->whereHas('course', fn (Builder $query) => $query->where('program_id', $filters['program_id'])))
            ->when($filters['course_id'] > 0, fn (Builder $query) => $query->where('course_id', $filters['course_id']))
            ->when($filters['learning_class_id'] > 0, fn (Builder $query) => $query->whereKey($filters['learning_class_id']))
            ->when($filters['tutor_id'] > 0, fn (Builder $query) => $query
                ->whereHas('tutors', fn (Builder $query) => $query->whereKey($filters['tutor_id'])))
            ->with([
                'course.program:id,name',
                'tutors:id,name',
            ])
            ->orderBy('name')
            ->get();
    }
}
