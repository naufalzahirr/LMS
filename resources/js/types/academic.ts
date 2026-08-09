export type AcademicStatus = 'active' | 'inactive';

export type AcademicStatusOption = {
    value: AcademicStatus;
    label: string;
};

export type ProgramOption = {
    id: number;
    name: string;
};

export type CourseOption = {
    id: number;
    program_id: number;
    name: string;
    program: string;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};
