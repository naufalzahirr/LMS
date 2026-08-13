import assert from 'node:assert/strict';
import test from 'node:test';
import {
    createAnswerAutosaveCoordinator,
    hasUnsavedOrFailedAnswers,
} from '../../resources/js/lib/answerAutosave.ts';

const snapshot = (overrides = {}) => ({
    answer_text: '',
    answer_boolean: null,
    selected_option_ids: [],
    ...overrides,
});

/** A controllable fake transport: resolves/rejects only when the test tells it to. */
function deferredTransport() {
    const calls = [];
    const pending = [];

    const save = (sentSnapshot) =>
        new Promise((resolve, reject) => {
            const call = { snapshot: sentSnapshot, resolve, reject };
            calls.push(call);
            pending.push(call);
        });

    return {
        save,
        calls,
        resolveNext(index = 0) {
            const call = pending.splice(index, 1)[0];
            call.resolve();
        },
        rejectNext(index = 0) {
            const call = pending.splice(index, 1)[0];
            call.reject(new Error('save failed'));
        },
    };
}

test('successful save goes dirty -> saving -> saved', async () => {
    const transport = deferredTransport();
    const states = [];
    const coordinator = createAnswerAutosaveCoordinator(snapshot(), transport.save, (s) => states.push(s));

    coordinator.update(snapshot({ answer_text: 'Paris' }));
    assert.equal(coordinator.getState(), 'dirty');

    coordinator.flush();
    assert.equal(coordinator.getState(), 'saving');

    transport.resolveNext();
    await Promise.resolve();
    await Promise.resolve();

    assert.equal(coordinator.getState(), 'saved');
    assert.equal(coordinator.isSafe(), true);
    assert.deepEqual(states, ['dirty', 'saving', 'saved']);
});

test('failed save goes dirty -> saving -> error and submission stays unsafe', async () => {
    const transport = deferredTransport();
    const coordinator = createAnswerAutosaveCoordinator(snapshot(), transport.save);

    coordinator.update(snapshot({ answer_text: 'Paris' }));
    coordinator.flush();
    transport.rejectNext();
    await Promise.resolve();
    await Promise.resolve();

    assert.equal(coordinator.getState(), 'error');
    assert.equal(coordinator.isSafe(), false);
});

test('retry after a failure saves the current latest answer and can become safe', async () => {
    const transport = deferredTransport();
    const coordinator = createAnswerAutosaveCoordinator(snapshot(), transport.save);

    coordinator.update(snapshot({ answer_text: 'Pariss' }));
    coordinator.flush();
    transport.rejectNext();
    await Promise.resolve();
    await Promise.resolve();
    assert.equal(coordinator.getState(), 'error');

    coordinator.retry();
    assert.equal(coordinator.getState(), 'saving');
    assert.equal(transport.calls[1].snapshot.answer_text, 'Pariss');

    transport.resolveNext();
    await Promise.resolve();
    await Promise.resolve();

    assert.equal(coordinator.getState(), 'saved');
    assert.equal(coordinator.isSafe(), true);
});

test('an edit made while a save is still running is not lost — the newer revision is sent next', async () => {
    const transport = deferredTransport();
    const coordinator = createAnswerAutosaveCoordinator(snapshot(), transport.save);

    coordinator.update(snapshot({ answer_text: 'revision one' }));
    coordinator.flush();
    assert.equal(coordinator.getState(), 'saving');

    // A second edit arrives while the first request is still in flight.
    coordinator.update(snapshot({ answer_text: 'revision two' }));
    assert.equal(coordinator.getState(), 'saving');

    // The first (now-stale) request finishes successfully.
    transport.resolveNext(0);
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();

    // It must not report "saved" for stale content — it should already be
    // chasing revision two.
    assert.equal(coordinator.getState(), 'saving');
    assert.equal(transport.calls.length, 2);
    assert.equal(transport.calls[1].snapshot.answer_text, 'revision two');

    transport.resolveNext(0);
    await Promise.resolve();
    await Promise.resolve();

    assert.equal(coordinator.getState(), 'saved');
});

test('an older failure does not overwrite a newer revision already in flight or saved', async () => {
    const transport = deferredTransport();
    const coordinator = createAnswerAutosaveCoordinator(snapshot(), transport.save);

    coordinator.update(snapshot({ answer_text: 'revision one' }));
    coordinator.flush();
    coordinator.update(snapshot({ answer_text: 'revision two' }));

    // The stale first request fails, not succeeds.
    transport.rejectNext(0);
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();

    // The failure was for stale content — it must not surface as 'error';
    // it should already be retrying with the latest revision.
    assert.notEqual(coordinator.getState(), 'error');
    assert.equal(transport.calls.length, 2);
    assert.equal(transport.calls[1].snapshot.answer_text, 'revision two');

    transport.resolveNext(0);
    await Promise.resolve();
    await Promise.resolve();
    assert.equal(coordinator.getState(), 'saved');
});

test('rapid Multiple Select toggles [A] -> [A, B] -> [B] persist only the final state', async () => {
    const transport = deferredTransport();
    const coordinator = createAnswerAutosaveCoordinator(snapshot(), transport.save);

    coordinator.update(snapshot({ selected_option_ids: [1] }));
    coordinator.flush();
    coordinator.update(snapshot({ selected_option_ids: [1, 2] }));
    coordinator.flush();
    coordinator.update(snapshot({ selected_option_ids: [2] }));
    coordinator.flush();

    // Only the very first click actually started a request — the rest were
    // coalesced into "latest known revision" while it was in flight.
    assert.equal(transport.calls.length, 1);
    assert.deepEqual(transport.calls[0].snapshot.selected_option_ids, [1]);

    transport.resolveNext();
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();

    assert.equal(transport.calls.length, 2);
    assert.deepEqual(transport.calls[1].snapshot.selected_option_ids, [2]);

    transport.resolveNext();
    await Promise.resolve();
    await Promise.resolve();

    assert.equal(coordinator.getState(), 'saved');
    assert.equal(transport.calls.length, 2, 'the intermediate [A, B] state was never sent');
});

test('flush() only acts while dirty — it never silently retries an existing failure', async () => {
    const transport = deferredTransport();
    const coordinator = createAnswerAutosaveCoordinator(snapshot(), transport.save);

    coordinator.update(snapshot({ answer_text: 'first' }));
    coordinator.flush();
    transport.rejectNext();
    await Promise.resolve();
    await Promise.resolve();
    assert.equal(coordinator.getState(), 'error');

    coordinator.flush();
    assert.equal(transport.calls.length, 1, 'flush() must not re-send a failed save on its own');
    assert.equal(coordinator.getState(), 'error');
});

test('a newly constructed coordinator with no local edits is idle and safe', () => {
    const transport = deferredTransport();
    const coordinator = createAnswerAutosaveCoordinator(
        snapshot({ answer_text: 'already saved on the server' }),
        transport.save,
    );

    assert.equal(coordinator.getState(), 'idle');
    assert.equal(coordinator.isSafe(), true);
    assert.equal(transport.calls.length, 0);
});

test('hasUnsavedOrFailedAnswers: all saved/idle is safe', () => {
    assert.equal(hasUnsavedOrFailedAnswers({ 1: 'idle', 2: 'saved' }), false);
});

test('hasUnsavedOrFailedAnswers: a pending debounce (dirty) blocks submission', () => {
    assert.equal(hasUnsavedOrFailedAnswers({ 1: 'saved', 2: 'dirty' }), true);
});

test('hasUnsavedOrFailedAnswers: an in-flight save blocks submission', () => {
    assert.equal(hasUnsavedOrFailedAnswers({ 1: 'saved', 2: 'saving' }), true);
});

test('hasUnsavedOrFailedAnswers: a failed save blocks submission', () => {
    assert.equal(hasUnsavedOrFailedAnswers({ 1: 'saved', 2: 'error' }), true);
});
