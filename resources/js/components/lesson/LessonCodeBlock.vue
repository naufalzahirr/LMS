<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { common, createLowlight } from 'lowlight';
import { computed, ref } from 'vue';
import LessonHighlightNode from '@/components/lesson/LessonHighlightNode.vue';
import type { LessonNode } from '@/types/lesson-content';

const props = defineProps<{ node: LessonNode }>();
const copied = ref(false);
const lowlight = createLowlight(common);
const code = computed(() =>
    (props.node.content ?? []).map((node) => node.text ?? '').join(''),
);
const language = computed(() => String(props.node.attrs?.language ?? 'plain'));
const highlighted = computed(() => {
    const aliases: Record<string, string> = { plain: 'plaintext', html: 'xml' };
    const selected = aliases[language.value] ?? language.value;

    try {
        return lowlight.highlight(selected, code.value).children;
    } catch {
        return [{ type: 'text' as const, value: code.value }];
    }
});

async function copyCode(): Promise<void> {
    await navigator.clipboard.writeText(code.value);
    copied.value = true;
    window.setTimeout(() => (copied.value = false), 1600);
}
</script>

<template>
    <section
        class="my-8 overflow-hidden rounded-xl border bg-zinc-950 text-zinc-100"
    >
        <header
            class="flex items-center justify-between border-b border-zinc-800 px-4 py-2"
        >
            <span class="font-mono text-xs font-medium text-zinc-400">{{
                language
            }}</span>
            <button
                type="button"
                class="flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-zinc-300 hover:bg-zinc-800 hover:text-white"
                aria-label="Copy code"
                @click="copyCode"
            >
                <Check v-if="copied" class="size-3.5" />
                <Copy v-else class="size-3.5" />
                {{ copied ? 'Copied' : 'Copy code' }}
            </button>
        </header>
        <pre
            class="overflow-x-auto p-4 text-sm leading-6"
        ><code><LessonHighlightNode
            v-for="(child, index) in highlighted"
            :key="index"
            :node="child"
        /></code></pre>
    </section>
</template>
