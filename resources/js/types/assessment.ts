import type { AcademicStatus, ProgramOption } from '@/types/academic';

export type QuestionType =
    | 'multiple_choice'
    | 'multiple_select'
    | 'true_false'
    | 'short_answer'
    | 'essay';
export type AssessmentPurpose = 'practice' | 'formative' | 'mastery';
export type AssessmentStatus = 'draft' | 'published' | 'archived';
export type ClassAssessmentStatus = 'active' | 'inactive';

export type SelectOption<T extends string = string> = {
    value: T;
    label: string;
};

export type AssessmentCourseOption = {
    id: number;
    program_id: number;
    name: string;
    program: string;
};

export type AssessmentCompetencyOption = {
    id: number;
    course_id: number;
    code: string;
    name: string;
};

export type QuestionBankOption = {
    id: number;
    course_id: number;
    name: string;
};

export type AuthoringOptions = {
    programs: ProgramOption[];
    courses: AssessmentCourseOption[];
    competencies: AssessmentCompetencyOption[];
    questionBanks: QuestionBankOption[];
};

export type QuestionOptionValue = {
    id?: number;
    option_text: string;
    is_correct: boolean;
    sort_order: number;
};

export type AcceptedAnswerValue = {
    id?: number;
    answer_text: string;
    case_sensitive: boolean;
};

export type EditableQuestion = {
    id: number;
    question_bank_id: number;
    competency_id: number;
    question_type: QuestionType;
    prompt: string;
    explanation: string | null;
    default_points: string;
    correct_boolean: boolean | null;
    status: AcademicStatus;
    sort_order: number;
    options: QuestionOptionValue[];
    accepted_answers: AcceptedAnswerValue[];
};

export type ClassAssessmentAssignment = {
    id: number;
    assessment_id?: number;
    title: string;
    purpose: AssessmentPurpose;
    assessment_status?: AssessmentStatus;
    competency: string;
    questions_count: number;
    total_points: string;
    opens_at: string | null;
    closes_at: string | null;
    max_attempts: number;
    status: ClassAssessmentStatus;
};

export type ClassAssessmentOption = {
    id: number;
    title: string;
    purpose: AssessmentPurpose;
    competency: string;
};
