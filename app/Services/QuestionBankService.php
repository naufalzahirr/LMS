<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Models\QuestionBank;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestionBankService
{
    /** @param array{course_id: int, name: string, code: string|null, description: string|null, status: AcademicStatus} $data */
    public function create(array $data): QuestionBank
    {
        return DB::transaction(fn (): QuestionBank => QuestionBank::query()->create($data));
    }

    /** @param array{course_id: int, name: string, code: string|null, description: string|null, status: AcademicStatus} $data */
    public function update(QuestionBank $questionBank, array $data): QuestionBank
    {
        return DB::transaction(function () use ($questionBank, $data): QuestionBank {
            if ($questionBank->questions()->exists() && $questionBank->course_id !== $data['course_id']) {
                throw ValidationException::withMessages([
                    'course_id' => __('A question bank with questions cannot be moved to another course.'),
                ]);
            }

            $questionBank->update($data);

            return $questionBank->refresh();
        });
    }

    public function delete(QuestionBank $questionBank): void
    {
        DB::transaction(function () use ($questionBank): void {
            if ($questionBank->questions()->exists()) {
                throw ValidationException::withMessages([
                    'question_bank' => __('This question bank cannot be deleted while it contains questions.'),
                ]);
            }

            $questionBank->delete();
        });
    }
}
