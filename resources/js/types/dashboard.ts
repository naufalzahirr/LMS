export type DashboardAssessmentAvailability =
    | 'Available'
    | 'In Progress'
    | 'Not Open Yet'
    | 'Closed'
    | 'Prerequisites Locked'
    | 'Remedial Required'
    | 'Mastered'
    | 'Attempts Exhausted'
    | 'Submitted / Pending Grading'
    | 'Graded';

export type DashboardAssessmentMastery = {
    can_start: boolean;
    status: string;
    required_score: string | null;
    latest_score: string | null;
    best_score: string | null;
    message: string | null;
    remedial_url: string | null;
} | null;

export type DashboardAssessmentAction = {
    label: string | null;
    url: string | null;
    method: 'get' | 'post' | null;
};

export type DashboardAssessmentCard = {
    id: number;
    title: string;
    class_name: string;
    competency: string;
    purpose: string;
    question_count: number;
    total_points: string;
    max_attempts: number;
    attempts_used: number;
    opens_at: string | null;
    closes_at: string | null;
    availability: DashboardAssessmentAvailability;
    can_start: boolean;
    start_label: string;
    start_url: string;
    intro_url: string;
    in_progress_url: string | null;
    latest_attempt_result_url: string | null;
    mastery: DashboardAssessmentMastery;
    action: DashboardAssessmentAction;
};

export type StudentContinueLearningItem = {
    enrollment_id: number;
    learning_class_id: number;
    name: string;
    course: string;
    program: string;
    completed_lessons: number;
    total_lessons: number;
    percentage: number;
    continue_lesson_title: string | null;
    continue_url: string;
    class_url: string;
};

export type StudentRemedialItem = {
    enrollment_id: number;
    competency_name: string;
    class_name: string | null;
    remedial_url: string;
};

export type StudentAvailableAssessmentItem = {
    title: string;
    class_name: string;
    start_url: string | null;
    method: 'get' | 'post' | null;
};

export type StudentDashboard = {
    has_any_enrollment_history: boolean;
    continue_learning: StudentContinueLearningItem[];
    needs_attention: {
        remedial: StudentRemedialItem[];
        assessments_available: {
            count: number;
            items: StudentAvailableAssessmentItem[];
        };
    };
    progress: {
        completed_lessons: number;
        total_lessons: number;
        competencies_mastered: number;
        competencies_total: number;
    };
    assessments: DashboardAssessmentCard[];
};

export type TutorClassSummary = {
    id: number;
    name: string;
    course: string;
    program: string;
    active_students_count: number;
    url: string;
};

export type TutorGradingQueueItem = {
    assignment_id: number;
    learning_class_id: number;
    title: string;
    class_name: string;
    count: number;
    review_url: string;
};

export type DashboardQuickAction = {
    label: string;
    url: string;
};

export type TutorDashboard = {
    my_classes: TutorClassSummary[];
    needs_attention: {
        needs_remedial_count: number;
        needs_remedial_url: string;
    };
    grading_queue: {
        count: number;
        items: TutorGradingQueueItem[];
    };
    quick_actions: DashboardQuickAction[];
};

export type AdminAttentionItem = {
    id: number;
    name: string;
    url: string;
};

export type AdminDashboard = {
    overview: {
        active_classes: number;
        active_students: number;
        tutors_with_assignments: number;
        active_courses: number;
        active_programs: number;
    };
    needs_attention: {
        classes_without_tutor: { items: AdminAttentionItem[]; total: number };
        classes_without_students: {
            items: AdminAttentionItem[];
            total: number;
        };
    };
    content: {
        programs: number;
        courses: number;
        lessons: number;
        assessments: number;
    };
    learning_status: {
        students_currently_learning: number;
        competencies_mastered: number;
        students_needing_remedial: number;
    };
    quick_actions: DashboardQuickAction[];
};
