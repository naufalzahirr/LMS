<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Models\Module;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ModuleService
{
    /**
     * @param  array{competency_id: int, name: string, slug: string, description: string|null, sort_order: int, status: AcademicStatus}  $data
     */
    public function create(array $data): Module
    {
        return DB::transaction(fn (): Module => Module::query()->create($data));
    }

    /**
     * @param  array{competency_id: int, name: string, slug: string, description: string|null, sort_order: int, status: AcademicStatus}  $data
     */
    public function update(Module $module, array $data): Module
    {
        return DB::transaction(function () use ($module, $data): Module {
            if ($module->competency_id !== $data['competency_id'] && $module->lessons()->exists()) {
                throw ValidationException::withMessages([
                    'competency_id' => __('A module with lessons cannot be moved to another competency.'),
                ]);
            }

            $module->update($data);

            return $module->refresh();
        });
    }

    public function delete(Module $module): void
    {
        DB::transaction(function () use ($module): void {
            if ($module->lessons()->exists()) {
                throw ValidationException::withMessages([
                    'module' => __('This module cannot be deleted while it still has lessons.'),
                ]);
            }

            $module->delete();
        });
    }
}
