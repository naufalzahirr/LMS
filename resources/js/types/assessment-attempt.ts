import type {
    AssessmentPurpose,
    QuestionImage,
    QuestionType,
} from '@/types/assessment';

export type AssessmentAttemptStatus =
    'in_progress' | 'pending_grading' | 'graded';

export type AssessmentAttemptSummary = {
    attempt_number: number;
    status: AssessmentAttemptStatus;
    started_at: string;
    submitted_at: string | null;
    graded_at: string | null;
    earned_points: string | null;
    max_points: string;
    percentage: string | null;
};

export type StudentAssessmentCard = {
    id: number;
    title: string;
    competency: string;
    purpose: AssessmentPurpose;
    question_count: number;
    total_points: string;
    max_attempts: number;
    attempts_used: number;
    opens_at: string | null;
    closes_at: string | null;
    availability: string;
    can_start: boolean;
    start_label: string;
    start_url: string;
    intro_url: string;
    in_progress_url: string | null;
    latest_attempt_result_url: string | null;
    mastery: {
        can_start: boolean;
        status: string;
        required_score: string;
        latest_score: string | null;
        best_score: string | null;
        message: string | null;
        remedial_url: string | null;
    } | null;
};

export type StudentAssessmentIntro = StudentAssessmentCard & {
    description: string | null;
    instructions: string | null;
    attempts: (AssessmentAttemptSummary & { result_url: string | null })[];
};

export type AssessmentPlayerOption = {
    id: number;
    option_text: string;
    sort_order: number;
};

export type AssessmentPlayerQuestion = {
    id: number;
    question_type: QuestionType;
    prompt: string;
    image: QuestionImage | null;
    points: string;
    sort_order: number;
    options: AssessmentPlayerOption[];
    answer: {
        answer_text: string | null;
        answer_boolean: boolean | null;
        selected_option_ids: number[];
    };
    answer_url: string;
};

export type AssessmentPlayer = {
    id: number;
    assessment_title: string;
    attempt_number: number;
    status: AssessmentAttemptStatus;
    started_at: string;
    closes_at: string | null;
    questions: AssessmentPlayerQuestion[];
    submit_url: string;
    back_url: string;
};

export type AssessmentResultQuestion = {
    id: number;
    question_type: QuestionType;
    prompt: string;
    image: QuestionImage | null;
    question_points: string;
    points_earned: string | null;
    correct: boolean | null;
    student_answer: string | string[] | boolean | null;
    correct_answer: string | string[] | boolean | null;
    explanation: string | null;
    feedback: string | null;
};

export type AssessmentResult = AssessmentAttemptSummary & {
    id: number;
    assessment_title: string;
    max_attempts: number;
    detailed_feedback: boolean;
    assessment_url: string;
    questions?: AssessmentResultQuestion[];
};

export type AssessmentReviewAttempt = {
    id: number;
    student: string;
    email: string;
    attempt_number: number;
    submitted_at: string | null;
    status: AssessmentAttemptStatus;
    auto_points: string | null;
    earned_points: string | null;
    max_points: string;
    percentage: string | null;
    grade_url: string | null;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};
