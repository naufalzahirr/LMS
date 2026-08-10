<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Models\Competency;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompetencyService
{
    /**
     * @param  array{course_id: int, code: string, name: string, slug: string, description: string|null, learning_objectives: string|null, sort_order: int, status: AcademicStatus}  $data
     */
    public function create(array $data): Competency
    {
        return DB::transaction(fn (): Competency => Competency::query()->create($data));
    }

    /**
     * @param  array{course_id: int, code: string, name: string, slug: string, description: string|null, learning_objectives: string|null, sort_order: int, status: AcademicStatus}  $data
     */
    public function update(Competency $competency, array $data): Competency
    {
        return DB::transaction(function () use ($competency, $data): Competency {
            $competency->update($data);

            return $competency->refresh();
        });
    }

    public function delete(Competency $competency): void
    {
        DB::transaction(function () use ($competency): void {
            if ($competency->modules()->exists() || $competency->questions()->exists() || $competency->assessments()->exists()) {
                throw ValidationException::withMessages([
                    'competency' => __('This competency cannot be deleted while it still has modules, questions, or assessments.'),
                ]);
            }

            $competency->delete();
        });
    }
}
