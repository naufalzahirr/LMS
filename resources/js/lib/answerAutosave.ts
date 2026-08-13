export type AutosaveState = 'idle' | 'dirty' | 'saving' | 'saved' | 'error';

export type AnswerSnapshot = {
    answer_text: string;
    answer_boolean: boolean | null;
    selected_option_ids: number[];
};

export type AnswerAutosaveCoordinator = {
    getState(): AutosaveState;
    /** Records a new local answer revision. Never sends a request by itself. */
    update(snapshot: AnswerSnapshot): void;
    /** Persists the latest revision now, but only while a save is genuinely pending (state === 'dirty'). */
    flush(): void;
    /** Explicitly re-attempts persisting the latest revision after a failure (state === 'error'). */
    retry(): void;
    /** True when there is no unsaved, in-flight, queued, or failed local change. */
    isSafe(): boolean;
};

/**
 * Coordinates autosave for a single question: at most one in-flight request
 * at a time, the newest local edit always wins, and a stale request's
 * completion (success or failure) can never clobber a newer revision's
 * state. Transport-agnostic — `save` is injected so this stays framework
 * and endpoint independent, and trivially testable without mounting Vue.
 */
export function createAnswerAutosaveCoordinator(
    initialSnapshot: AnswerSnapshot,
    save: (snapshot: AnswerSnapshot) => Promise<void>,
    onStateChange?: (state: AutosaveState) => void,
): AnswerAutosaveCoordinator {
    let latestSnapshot = initialSnapshot;
    let latestRevision = 0;
    let confirmedRevision = 0;
    let state: AutosaveState = 'idle';

    function setState(next: AutosaveState): void {
        if (state === next) {
            return;
        }

        state = next;
        onStateChange?.(state);
    }

    function attemptSave(): void {
        if (state === 'saving' || latestRevision === confirmedRevision) {
            return;
        }

        const revisionToSend = latestRevision;
        const snapshotToSend = latestSnapshot;
        setState('saving');

        save(snapshotToSend).then(
            () => {
                confirmedRevision = revisionToSend;

                // A newer edit arrived while this request was in flight — the
                // just-confirmed revision is already stale, so immediately
                // chase the latest one instead of flashing "Saved".
                if (revisionToSend === latestRevision) {
                    setState('saved');
                } else {
                    setState('dirty');
                    attemptSave();
                }
            },
            () => {
                // Likewise, a failure for a now-superseded revision must not
                // surface as an error the Student has to manually retry —
                // their newer edit effectively retries it automatically.
                if (revisionToSend === latestRevision) {
                    setState('error');
                } else {
                    setState('dirty');
                    attemptSave();
                }
            },
        );
    }

    return {
        getState: () => state,
        update(snapshot) {
            latestSnapshot = snapshot;
            latestRevision += 1;

            if (state !== 'saving') {
                setState('dirty');
            }
        },
        flush() {
            if (state === 'dirty') {
                attemptSave();
            }
        },
        retry() {
            if (state === 'error') {
                attemptSave();
            }
        },
        isSafe: () => state === 'idle' || state === 'saved',
    };
}

/**
 * The single source of truth for whether it is safe to finalize submission:
 * every latest local answer must be confirmed persisted. A pending debounce,
 * an in-flight request, a queued newer edit, or a failed save all block
 * submission — an unanswered question does not, since that's a separate,
 * always-allowed concern (see the submit-confirmation dialog).
 */
export function hasUnsavedOrFailedAnswers(
    statuses: Record<number, AutosaveState>,
): boolean {
    return Object.values(statuses).some(
        (state) => state !== 'idle' && state !== 'saved',
    );
}
