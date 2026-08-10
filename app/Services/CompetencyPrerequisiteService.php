<?php

namespace App\Services;

use App\Models\Competency;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompetencyPrerequisiteService
{
    public function add(Competency $competency, Competency $prerequisite): void
    {
        DB::transaction(function () use ($competency, $prerequisite): void {
            $lockedCompetency = Competency::query()->whereKey($competency->id)->lockForUpdate()->firstOrFail();
            $lockedPrerequisite = Competency::query()->whereKey($prerequisite->id)->lockForUpdate()->firstOrFail();

            if ($lockedCompetency->is($lockedPrerequisite)) {
                throw ValidationException::withMessages([
                    'prerequisite_competency_id' => __('A competency cannot be its own prerequisite.'),
                ]);
            }

            if ($lockedCompetency->course_id !== $lockedPrerequisite->course_id) {
                throw ValidationException::withMessages([
                    'prerequisite_competency_id' => __('Prerequisites must belong to the same course.'),
                ]);
            }

            if ($lockedCompetency->prerequisites()->whereKey($lockedPrerequisite->id)->exists()) {
                throw ValidationException::withMessages([
                    'prerequisite_competency_id' => __('This prerequisite is already configured.'),
                ]);
            }

            if ($this->wouldCreateCycle($lockedCompetency, $lockedPrerequisite)) {
                throw ValidationException::withMessages([
                    'prerequisite_competency_id' => __('This prerequisite would create a circular dependency.'),
                ]);
            }

            $lockedCompetency->prerequisites()->attach($lockedPrerequisite->id);
        });
    }

    public function remove(Competency $competency, Competency $prerequisite): void
    {
        DB::transaction(function () use ($competency, $prerequisite): void {
            $competency->prerequisites()->detach($prerequisite->id);
        });
    }

    private function wouldCreateCycle(Competency $competency, Competency $prerequisite): bool
    {
        $courseIds = Competency::query()->where('course_id', $competency->course_id)->pluck('id');
        $adjacency = [];

        foreach (DB::table('competency_prerequisites')
            ->whereIn('competency_id', $courseIds)
            ->get(['competency_id', 'prerequisite_competency_id']) as $edge) {
            $adjacency[(int) $edge->competency_id][] = (int) $edge->prerequisite_competency_id;
        }

        $adjacency[$competency->id][] = $prerequisite->id;
        $stack = [$prerequisite->id];
        $visited = [];

        while ($stack !== []) {
            $current = array_pop($stack);

            if ($current === $competency->id) {
                return true;
            }

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            foreach ($adjacency[$current] ?? [] as $next) {
                $stack[] = $next;
            }
        }

        return false;
    }
}
