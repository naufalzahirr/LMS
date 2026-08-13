export function percentageLabel(value: number | null): string {
    return value === null ? 'Not available' : `${value}%`;
}

export function metricCountLabel(
    percentage: number | null,
    numerator: number,
    denominator: number,
    unit: string,
): string {
    if (percentage === null || denominator === 0) {
        return `No eligible ${unit} data yet.`;
    }

    return `${percentage}% · ${numerator}/${denominator} ${unit}`;
}

export function assessmentPerformanceLabel(
    average: number | null,
    gradedStudents: number,
): string {
    return average === null || gradedStudents === 0
        ? 'No graded assessment data yet.'
        : `Average: ${average}% · ${gradedStudents} graded ${gradedStudents === 1 ? 'Student' : 'Students'}`;
}

export function attemptScoreLabel(
    status: string,
    percentage: string | number | null,
): string {
    if (status === 'pending_grading') {
        return 'Pending grading';
    }

    if (status === 'in_progress') {
        return 'In progress';
    }

    return percentage === null ? 'No final score' : `${percentage}%`;
}
