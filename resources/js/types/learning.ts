import type { EnrollmentStatus, LearningClassStatus } from '@/types/delivery';

export type LessonProgressStatus = 'not_started' | 'in_progress' | 'completed';

export type StudentClassDetails = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    course: string;
    program: string;
    status: LearningClassStatus;
    start_date: string | null;
    end_date: string | null;
};

export type StudentClassCard = StudentClassDetails & {
    enrollment_status: EnrollmentStatus;
    tutors: string[];
    completed_lessons: number;
    total_lessons: number;
    percentage: number;
    read_only: boolean;
    continue_url: string;
};

export type PlayerLesson = {
    id: number;
    title: string;
    lesson_type: string;
    duration_minutes: number | null;
    progress_status: LessonProgressStatus;
    url: string | null;
};

export type PlayerModule = {
    id: number;
    name: string;
    description?: string | null;
    lessons: PlayerLesson[];
};

export type PlayerCompetency = {
    id: number;
    name: string;
    description?: string | null;
    unlocked: boolean;
    mastery_status:
        | 'locked'
        | 'learning'
        | 'ready_for_assessment'
        | 'needs_remedial'
        | 'mastered';
    prerequisites: string[];
    missing_prerequisites: string[];
    latest_score: string | null;
    best_score: string | null;
    required_score: string | null;
    remedial_url: string | null;
    modules: PlayerModule[];
};
