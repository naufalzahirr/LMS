import assert from 'node:assert/strict';
import test from 'node:test';
import {
    assessmentPerformanceLabel,
    attemptScoreLabel,
    metricCountLabel,
    percentageLabel,
} from '../../resources/js/lib/learningAnalytics.ts';

test('percentages include their numerator and denominator', () => {
    assert.equal(
        metricCountLabel(75, 15, 20, 'Students'),
        '75% · 15/20 Students',
    );
});

test('a zero denominator renders an unavailable empty state instead of 0%', () => {
    assert.equal(percentageLabel(null), 'Not available');
    assert.equal(
        metricCountLabel(null, 0, 0, 'competency cells'),
        'No eligible competency cells data yet.',
    );
});

test('assessment performance always includes the graded sample size', () => {
    assert.equal(
        assessmentPerformanceLabel(78, 12),
        'Average: 78% · 12 graded Students',
    );
    assert.equal(
        assessmentPerformanceLabel(null, 0),
        'No graded assessment data yet.',
    );
});

test('pending grading never renders as a zero score', () => {
    assert.equal(attemptScoreLabel('pending_grading', null), 'Pending grading');
    assert.equal(attemptScoreLabel('graded', 0), '0%');
});
