<?php

namespace Database\Seeders;

use App\Enums\AcademicStatus;
use App\Enums\AssessmentFeedbackMode;
use App\Enums\AssessmentPurpose;
use App\Enums\AssessmentStatus;
use App\Enums\ClassAssessmentStatus;
use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\Competency;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use App\Models\Question;
use App\Models\QuestionBank;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssessmentSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('Development assessment sample was not seeded in production.');

            return;
        }

        DB::transaction(function (): void {
            $competency = Competency::query()->where('code', 'HTML-01')->firstOrFail();
            $bank = QuestionBank::withTrashed()->updateOrCreate(
                ['course_id' => $competency->course_id, 'code' => 'HTML-CORE'],
                [
                    'name' => 'HTML Core Question Bank',
                    'description' => 'Development sample questions for HTML Fundamentals.',
                    'status' => AcademicStatus::Active,
                ],
            );
            $bank->restore();

            $questions = [
                $this->question($bank, $competency, 1, QuestionType::MultipleChoice, 'Which element represents the main heading of a page?', [
                    'options' => [['<h1>', true], ['<head>', false], ['<title>', false]],
                ]),
                $this->question($bank, $competency, 2, QuestionType::MultipleSelect, 'Which elements are semantic sectioning elements?', [
                    'options' => [['<article>', true], ['<section>', true], ['<div>', false]],
                ]),
                $this->question($bank, $competency, 3, QuestionType::TrueFalse, 'The alt attribute gives an image a text alternative.', [
                    'correct_boolean' => true,
                ]),
                $this->question($bank, $competency, 4, QuestionType::ShortAnswer, 'Which HTML element creates a hyperlink?', [
                    'answers' => [['a', false], ['<a>', false]],
                ]),
                $this->question($bank, $competency, 5, QuestionType::Essay, 'Explain why semantic HTML improves accessibility.'),
            ];

            $existingAssessment = Assessment::withTrashed()
                ->where('competency_id', $competency->id)
                ->where('code', 'HTML-FORMATIVE-01')
                ->first();
            $assessmentUsed = $existingAssessment?->classAssignments()->whereHas('attempts')->exists() === true;
            $assessment = $assessmentUsed
                ? $existingAssessment
                : Assessment::withTrashed()->updateOrCreate(
                    ['competency_id' => $competency->id, 'code' => 'HTML-FORMATIVE-01'],
                    [
                        'title' => 'HTML Fundamentals Checkpoint',
                        'description' => 'A development sample assessment covering core HTML concepts.',
                        'purpose' => AssessmentPurpose::Formative,
                        'status' => AssessmentStatus::Published,
                        'instructions' => 'Answer every question using your understanding of semantic HTML.',
                        'shuffle_questions' => false,
                    ]);
            $assessment->restore();

            if (! $assessmentUsed) {
                foreach ($questions as $index => $question) {
                    AssessmentQuestion::query()->updateOrCreate(
                        ['assessment_id' => $assessment->id, 'question_id' => $question->id],
                        ['points' => $question->default_points, 'sort_order' => $index],
                    );
                }
            }

            $learningClass = LearningClass::query()->where('code', 'FE-BATCH-A')->first();

            if ($learningClass instanceof LearningClass) {
                LearningClassAssessment::query()->updateOrCreate(
                    ['learning_class_id' => $learningClass->id, 'assessment_id' => $assessment->id],
                    [
                        'opens_at' => null,
                        'closes_at' => null,
                        'max_attempts' => 2,
                        'status' => ClassAssessmentStatus::Active,
                        'feedback_mode' => AssessmentFeedbackMode::AfterFinalAttempt,
                    ],
                );
            }
        });
    }

    /**
     * @param  array{options?: array<int, array{0: string, 1: bool}>, answers?: array<int, array{0: string, 1: bool}>, correct_boolean?: bool}  $answerKey
     */
    private function question(
        QuestionBank $bank,
        Competency $competency,
        int $sortOrder,
        QuestionType $type,
        string $prompt,
        array $answerKey = [],
    ): Question {
        $existing = Question::withTrashed()
            ->where('question_bank_id', $bank->id)
            ->where('sort_order', $sortOrder)
            ->first();

        if ($existing?->attemptQuestions()->exists() === true) {
            return $existing;
        }

        $question = Question::withTrashed()->updateOrCreate(
            ['question_bank_id' => $bank->id, 'sort_order' => $sortOrder],
            [
                'competency_id' => $competency->id,
                'question_type' => $type,
                'prompt' => $prompt,
                'explanation' => 'Review the relevant HTML semantics in the course material.',
                'default_points' => $type === QuestionType::Essay ? '3.00' : '1.00',
                'correct_boolean' => $answerKey['correct_boolean'] ?? null,
                'status' => AcademicStatus::Active,
            ],
        );
        $question->restore();
        $question->options()->delete();
        $question->acceptedAnswers()->delete();

        foreach ($answerKey['options'] ?? [] as $index => [$text, $correct]) {
            $question->options()->create([
                'option_text' => $text,
                'is_correct' => $correct,
                'sort_order' => $index,
            ]);
        }

        foreach ($answerKey['answers'] ?? [] as [$text, $caseSensitive]) {
            $question->acceptedAnswers()->create([
                'answer_text' => $text,
                'case_sensitive' => $caseSensitive,
            ]);
        }

        return $question;
    }
}
