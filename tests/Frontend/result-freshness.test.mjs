import assert from 'node:assert/strict';
import test from 'node:test';
import { createResultFreshnessController } from '../../resources/js/lib/resultFreshness.ts';

test('mounting Result revalidates its server-authoritative props once', () => {
    let reloads = 0;
    const freshness = createResultFreshnessController(() => reloads++);

    freshness.onMount();

    assert.equal(reloads, 1);
});

test('only persisted pageshow triggers an additional BFCache revalidation', () => {
    let reloads = 0;
    const freshness = createResultFreshnessController(() => reloads++);

    freshness.onPageShow(false);
    assert.equal(reloads, 0);

    freshness.onPageShow(true);
    assert.equal(reloads, 1);
});
