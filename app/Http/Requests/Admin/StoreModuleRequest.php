<?php

namespace App\Http\Requests\Admin;

use App\Enums\AcademicStatus;
use App\Models\Competency;
use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user?->hasRole('Admin')) {
            return $user->can('create', Module::class);
        }

        $competency = Competency::query()->find($this->integer('competency_id'));

        return $competency !== null && ($user?->can('create', [Module::class, $competency]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $competencyId = $this->integer('competency_id');

        return [
            'competency_id' => ['required', 'integer', Rule::exists(Competency::class, 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique(Module::class)->where('competency_id', $competencyId)],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(AcademicStatus::class)],
        ];
    }

    /**
     * @return array{competency_id: int, name: string, slug: string, description: string|null, sort_order: int, status: AcademicStatus}
     */
    public function payload(): array
    {
        return [
            'competency_id' => $this->integer('competency_id'),
            'name' => $this->string('name')->toString(),
            'slug' => $this->string('slug')->toString(),
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'sort_order' => $this->integer('sort_order'),
            'status' => AcademicStatus::from($this->string('status')->toString()),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug($this->string('name')->toString())]);
        }
    }
}
