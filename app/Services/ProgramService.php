<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Models\Program;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgramService
{
    /**
     * @param  array{name: string, slug: string, code: string|null, description: string|null, status: AcademicStatus}  $data
     */
    public function create(array $data): Program
    {
        return DB::transaction(fn (): Program => Program::query()->create($data));
    }

    /**
     * @param  array{name: string, slug: string, code: string|null, description: string|null, status: AcademicStatus}  $data
     */
    public function update(Program $program, array $data): Program
    {
        return DB::transaction(function () use ($program, $data): Program {
            $program->update($data);

            return $program->refresh();
        });
    }

    /**
     * @throws ValidationException
     */
    public function delete(Program $program): void
    {
        DB::transaction(function () use ($program): void {
            if ($program->courses()->exists()) {
                throw ValidationException::withMessages([
                    'program' => __('This program cannot be deleted while it still has courses.'),
                ]);
            }

            $program->delete();
        });
    }
}
