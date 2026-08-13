import type { QuestionType } from '@/types/assessment';
import type { AssessmentAttemptStatus } from '@/types/assessment-attempt';

export type LocalAnswer = {
    answer_text: string;
    answer_boolean: boolean | null;
    selected_option_ids: number[];
};

export type AnswerSaveState = 'idle' | 'saving' | 'saved' | 'error';

/**
 * Whether a question counts as answered for navigation/submission UX only —
 * this never affects grading, which remains entirely server-side.
 */
export function isQuestionAnswered(
    type: QuestionType,
    answer: LocalAnswer,
): boolean {
    switch (type) {
        case 'multiple_choice':
            return answer.selected_option_ids.length === 1;
        case 'multiple_select':
            return answer.selected_option_ids.length >= 1;
        case 'true_false':
            return answer.answer_boolean !== null;
        case 'short_answer':
        case 'essay':
            return answer.answer_text.trim().length > 0;
    }
}

export function unansweredQuestionIds(
    questions: { id: number; question_type: QuestionType }[],
    answers: Record<number, LocalAnswer>,
): number[] {
    return questions
        .filter(
            (question) =>
                !isQuestionAnswered(
                    question.question_type,
                    answers[question.id],
                ),
        )
        .map((question) => question.id);
}

export function countAnswered(
    questions: { id: number; question_type: QuestionType }[],
    answers: Record<number, LocalAnswer>,
): number {
    return questions.length - unansweredQuestionIds(questions, answers).length;
}

const STATUS_LABELS: Record<AssessmentAttemptStatus, string> = {
    in_progress: 'In Progress',
    pending_grading: 'Pending Grading',
    graded: 'Graded',
};

/** The single canonical Title Case label for an attempt status, used everywhere it's rendered. */
export function statusLabel(status: AssessmentAttemptStatus): string {
    return STATUS_LABELS[status];
}
