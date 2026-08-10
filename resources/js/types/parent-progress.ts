export type ParentMasteryStatus =
    | 'locked'
    | 'learning'
    | 'ready_for_assessment'
    | 'needs_remedial'
    | 'mastered';

export type ParentMasteryCell = {
    id: number;
    name: string;
    status: ParentMasteryStatus;
    latest_score: string | null;
    best_score: string | null;
    required_score: string | null;
    remedial_lessons: string[];
};

export type ParentAssessmentSummary = {
    assessment: string;
    purpose: string;
    attempt: number;
    status: 'in_progress' | 'pending_grading' | 'graded';
    score: string | null;
    percentage: string | null;
    submitted_at: string | null;
};

export type ParentClassProgress = {
    id: number;
    name: string;
    program: string;
    course: string;
    class_status: string;
    enrollment_status: string;
    tutors: string[];
    lesson_progress: { completed: number; total: number; percentage: number };
    mastery: ParentMasteryCell[];
    assessments: ParentAssessmentSummary[];
};

export type ParentChildSummary = {
    active_classes: number;
    lesson_percentage: number;
    competencies_mastered: number;
    competencies_total: number;
    needs_remedial: number;
};

export type ParentChildProgress = {
    id: number;
    name: string;
    email: string;
    summary: ParentChildSummary;
    current_classes: ParentClassProgress[];
    history_classes: ParentClassProgress[];
    url: string;
};
