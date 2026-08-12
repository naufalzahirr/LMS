import assert from 'node:assert/strict';
import test from 'node:test';
import { computed, nextTick, ref } from 'vue';
import {
    initialLessonCheckpointMastery,
    summarizeLessonCheckpointMastery,
    synchronizeLessonCheckpointMastery,
} from '../../resources/js/lib/lessonCheckpointMastery.ts';

// Mirrors the real component wiring end to end (rather than only the pure
// state-transition helpers) so a regression in the reactive graph between
// LessonCheckpointNode -> LessonContentRenderer -> the student lesson page
// would actually fail this test:
//   ref(checkpointMasteryState) [page]
//     -> computed state + update() [provide/inject context]
//       -> per-checkpoint computed mastered [checkpoint node]
function buildLessonMasterySimulation(document) {
    const checkpointMasteryState = ref(initialLessonCheckpointMastery(document));
    const checkpointSummary = computed(() =>
        summarizeLessonCheckpointMastery(checkpointMasteryState.value),
    );

    const masteryContext = {
        state: computed(() => checkpointMasteryState.value),
        update: (update) => {
            checkpointMasteryState.value = synchronizeLessonCheckpointMastery(
                checkpointMasteryState.value,
                update,
            );
        },
    };

    const checkpointNode = (checkpointId) => ({
        mastered: computed(() => masteryContext.state.value?.[checkpointId] ?? false),
        submitResult: (mastered) =>
            masteryContext.update({ checkpointId, mastered }),
    });

    return { checkpointSummary, checkpointNode };
}

const lessonCheckpointNode = (id, type) => ({
    type: 'lessonCheckpoint',
    attrs: {
        checkpointId: id,
        checkpoint: { id, type, interactive: true, mastered: false },
    },
});

const FOUR_TYPE_DOCUMENT = {
    type: 'doc',
    content: [
        lessonCheckpointNode(1, 'multiple_choice'),
        lessonCheckpointNode(2, 'multiple_select'),
        lessonCheckpointNode(3, 'true_false'),
        lessonCheckpointNode(4, 'fill_blank'),
    ],
};

test('a lesson with one of each checkpoint type reaches 4/4 without reload, in document order', async () => {
    const { checkpointSummary, checkpointNode } =
        buildLessonMasterySimulation(FOUR_TYPE_DOCUMENT);
    const nodes = {
        multiple_choice: checkpointNode(1),
        multiple_select: checkpointNode(2),
        true_false: checkpointNode(3),
        fill_blank: checkpointNode(4),
    };

    assert.deepEqual(checkpointSummary.value, { mastered: 0, total: 4 });

    const sequence = ['multiple_choice', 'multiple_select', 'true_false', 'fill_blank'];
    let expected = 0;

    for (const type of sequence) {
        nodes[type].submitResult(true);
        await nextTick();
        expected += 1;

        assert.equal(
            checkpointSummary.value.mastered,
            expected,
            `mastered count should be ${expected}/4 after mastering ${type}`,
        );
        assert.equal(nodes[type].mastered.value, true);
    }

    assert.deepEqual(checkpointSummary.value, { mastered: 4, total: 4 });
});

test('a different mastery order still reaches 4/4 without reload', async () => {
    const { checkpointSummary, checkpointNode } =
        buildLessonMasterySimulation(FOUR_TYPE_DOCUMENT);
    const nodes = {
        multiple_choice: checkpointNode(1),
        multiple_select: checkpointNode(2),
        true_false: checkpointNode(3),
        fill_blank: checkpointNode(4),
    };

    const sequence = ['fill_blank', 'true_false', 'multiple_choice', 'multiple_select'];
    let expected = 0;

    for (const type of sequence) {
        nodes[type].submitResult(true);
        await nextTick();
        expected += 1;

        assert.equal(checkpointSummary.value.mastered, expected);
    }

    assert.deepEqual(checkpointSummary.value, { mastered: 4, total: 4 });
});

test('mastering the same checkpoint again keeps the aggregate at 4/4', async () => {
    const { checkpointSummary, checkpointNode } =
        buildLessonMasterySimulation(FOUR_TYPE_DOCUMENT);
    const nodes = [1, 2, 3, 4].map((id) => checkpointNode(id));

    for (const node of nodes) {
        node.submitResult(true);
        await nextTick();
    }

    assert.deepEqual(checkpointSummary.value, { mastered: 4, total: 4 });

    nodes[3].submitResult(true);
    await nextTick();

    assert.deepEqual(checkpointSummary.value, { mastered: 4, total: 4 });
});

test('an incorrect retry after mastery does not reduce the aggregate below 4/4', async () => {
    const { checkpointSummary, checkpointNode } =
        buildLessonMasterySimulation(FOUR_TYPE_DOCUMENT);
    const nodes = [1, 2, 3, 4].map((id) => checkpointNode(id));

    for (const node of nodes) {
        node.submitResult(true);
        await nextTick();
    }

    assert.deepEqual(checkpointSummary.value, { mastered: 4, total: 4 });

    nodes[0].submitResult(false);
    await nextTick();

    assert.deepEqual(checkpointSummary.value, { mastered: 4, total: 4 });
    assert.equal(nodes[0].mastered.value, true);
});
