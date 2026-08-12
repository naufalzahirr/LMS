import assert from 'node:assert/strict';
import test from 'node:test';
import {
    initialLessonCheckpointMastery,
    summarizeLessonCheckpointMastery,
    synchronizeLessonCheckpointMastery,
} from '../../resources/js/lib/lessonCheckpointMastery.ts';

const checkpointNode = (id, mastered) => ({
    type: 'lessonCheckpoint',
    attrs: {
        checkpointId: id,
        checkpoint: {
            id,
            interactive: true,
            mastered,
        },
    },
});

test('mastery aggregate synchronizes by checkpoint identity without double counting or decrementing', () => {
    const document = {
        type: 'doc',
        content: [
            checkpointNode(10, false),
            checkpointNode(20, false),
            checkpointNode(30, false),
        ],
    };
    let state = initialLessonCheckpointMastery(document);

    assert.deepEqual(summarizeLessonCheckpointMastery(state), {
        mastered: 0,
        total: 3,
    });

    state = synchronizeLessonCheckpointMastery(state, {
        checkpointId: 10,
        mastered: true,
    });
    assert.equal(summarizeLessonCheckpointMastery(state).mastered, 1);

    state = synchronizeLessonCheckpointMastery(state, {
        checkpointId: 20,
        mastered: true,
    });
    assert.equal(summarizeLessonCheckpointMastery(state).mastered, 2);

    const afterSecondMastery = state;
    state = synchronizeLessonCheckpointMastery(state, {
        checkpointId: 10,
        mastered: true,
    });
    assert.strictEqual(state, afterSecondMastery);

    state = synchronizeLessonCheckpointMastery(state, {
        checkpointId: 10,
        mastered: false,
    });
    assert.strictEqual(state, afterSecondMastery);
    assert.equal(summarizeLessonCheckpointMastery(state).mastered, 2);

    state = synchronizeLessonCheckpointMastery(state, {
        checkpointId: 30,
        mastered: true,
    });
    assert.deepEqual(summarizeLessonCheckpointMastery(state), {
        mastered: 3,
        total: 3,
    });
});

test('persisted lesson payload rebuilds the same aggregate after reload or navigation', () => {
    const persisted = initialLessonCheckpointMastery({
        type: 'doc',
        content: [
            checkpointNode(10, true),
            checkpointNode(20, true),
            checkpointNode(30, true),
        ],
    });

    assert.deepEqual(summarizeLessonCheckpointMastery(persisted), {
        mastered: 3,
        total: 3,
    });
    assert.strictEqual(
        synchronizeLessonCheckpointMastery(persisted, {
            checkpointId: 999,
            mastered: true,
        }),
        persisted,
    );
});
