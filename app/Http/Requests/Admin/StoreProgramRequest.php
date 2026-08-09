<?php

namespace App\Http\Requests\Admin;

use App\Enums\AcademicStatus;
use App\Models\Program;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Program::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique(Program::class)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique(Program::class)],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(AcademicStatus::class)],
        ];
    }

    /**
     * @return array{name: string, slug: string, code: string|null, description: string|null, status: AcademicStatus}
     */
    public function payload(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'slug' => $this->string('slug')->toString(),
            'code' => $this->filled('code') ? $this->string('code')->toString() : null,
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
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
