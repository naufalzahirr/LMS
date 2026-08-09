import type { Paginated } from '@/types/academic';

export type LearningClassStatus = 'active' | 'inactive' | 'completed';
export type EnrollmentStatus = 'active' | 'completed' | 'withdrawn';
export type ParentRelationshipType = 'father' | 'mother' | 'guardian' | 'other';

export type SelectOption<T extends string = string> = {
    value: T;
    label: string;
};

export type DeliveryCourseOption = {
    id: number;
    program_id: number;
    name: string;
    program: string;
};

export type DeliveryProgramOption = {
    id: number;
    name: string;
};

export type DeliveryUserOption = {
    id: number;
    name: string;
    email: string;
};

export type DeliveryPaginator<T> = Paginated<T>;
