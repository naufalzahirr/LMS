import type { ComputedRef, InjectionKey } from 'vue';
import type { LessonDocument, LessonNode } from '@/types/lesson-content';

export type LessonCheckpointMasteryState = Readonly<Record<number, boolean>>;

export type LessonCheckpointMasteryUpdate = {
    checkpointId: number;
    mastered: boolean;
};

export type LessonCheckpointMasteryContext = {
    state: ComputedRef<LessonCheckpointMasteryState | undefined>;
    update: (update: LessonCheckpointMasteryUpdate) => void;
};

export const lessonCheckpointMasteryKey: InjectionKey<LessonCheckpointMasteryContext> =
    Symbol('lesson-checkpoint-mastery');

export function initialLessonCheckpointMastery(
    document: LessonDocument,
): LessonCheckpointMasteryState {
    const state: Record<number, boolean> = {};

    for (const node of document.content) {
        collectCheckpointMastery(node, state);
    }

    return state;
}

export function synchronizeLessonCheckpointMastery(
    state: LessonCheckpointMasteryState,
    update: LessonCheckpointMasteryUpdate,
): LessonCheckpointMasteryState {
    if (
        !update.mastered ||
        state[update.checkpointId] === undefined ||
        state[update.checkpointId]
    ) {
        return state;
    }

    return { ...state, [update.checkpointId]: true };
}

export function summarizeLessonCheckpointMastery(
    state: LessonCheckpointMasteryState,
): { mastered: number; total: number } {
    const values = Object.values(state);

    return {
        mastered: values.filter(Boolean).length,
        total: values.length,
    };
}

function collectCheckpointMastery(
    node: LessonNode,
    state: Record<number, boolean>,
): void {
    if (node.type === 'lessonCheckpoint') {
        const checkpoint = node.attrs?.checkpoint;

        if (
            typeof checkpoint === 'object' &&
            checkpoint !== null &&
            Number.isInteger((checkpoint as { id?: unknown }).id) &&
            Number((checkpoint as { id: number }).id) > 0
        ) {
            const id = (checkpoint as { id: number }).id;
            const mastered =
                'interactive' in checkpoint &&
                checkpoint.interactive === true &&
                'mastered' in checkpoint &&
                checkpoint.mastered === true;

            state[id] = state[id] === true || mastered;
        }
    }

    for (const child of node.content ?? []) {
        collectCheckpointMastery(child, state);
    }
}
