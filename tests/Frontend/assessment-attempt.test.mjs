import assert from 'node:assert/strict';
import test from 'node:test';
import {
    countAnswered,
    isQuestionAnswered,
    statusLabel,
    unansweredQuestionIds,
} from '../../resources/js/lib/assessmentAttempt.ts';

const blank = () => ({ answer_text: '', answer_boolean: null, selected_option_ids: [] });

test('multiple choice is answered only with exactly one selection', () => {
    assert.equal(isQuestionAnswered('multiple_choice', blank()), false);
    assert.equal(
        isQuestionAnswered('multiple_choice', { ...blank(), selected_option_ids: [1] }),
        true,
    );
    assert.equal(
        isQuestionAnswered('multiple_choice', { ...blank(), selected_option_ids: [1, 2] }),
        false,
    );
});

test('multiple select is answered with at least one selection', () => {
    assert.equal(isQuestionAnswered('multiple_select', blank()), false);
    assert.equal(
        isQuestionAnswered('multiple_select', { ...blank(), selected_option_ids: [1] }),
        true,
    );
    assert.equal(
        isQuestionAnswered('multiple_select', { ...blank(), selected_option_ids: [1, 2] }),
        true,
    );
});

test('true/false is answered once a boolean is set, including false', () => {
    assert.equal(isQuestionAnswered('true_false', blank()), false);
    assert.equal(
        isQuestionAnswered('true_false', { ...blank(), answer_boolean: false }),
        true,
    );
    assert.equal(
        isQuestionAnswered('true_false', { ...blank(), answer_boolean: true }),
        true,
    );
});

test('short answer and essay require non-blank trimmed text', () => {
    assert.equal(isQuestionAnswered('short_answer', blank()), false);
    assert.equal(
        isQuestionAnswered('short_answer', { ...blank(), answer_text: '   ' }),
        false,
    );
    assert.equal(
        isQuestionAnswered('short_answer', { ...blank(), answer_text: 'Paris' }),
        true,
    );
    assert.equal(isQuestionAnswered('essay', blank()), false);
    assert.equal(
        isQuestionAnswered('essay', { ...blank(), answer_text: 'An essay response.' }),
        true,
    );
});

test('unansweredQuestionIds and countAnswered reflect a mixed set', () => {
    const questions = [
        { id: 1, question_type: 'multiple_choice' },
        { id: 2, question_type: 'essay' },
        { id: 3, question_type: 'true_false' },
    ];
    const answers = {
        1: { ...blank(), selected_option_ids: [10] },
        2: blank(),
        3: blank(),
    };

    assert.deepEqual(unansweredQuestionIds(questions, answers), [2, 3]);
    assert.equal(countAnswered(questions, answers), 1);
});

test('statusLabel renders the canonical Title Case wording for each backend status', () => {
    assert.equal(statusLabel('in_progress'), 'In Progress');
    assert.equal(statusLabel('pending_grading'), 'Pending Grading');
    assert.equal(statusLabel('graded'), 'Graded');
});
