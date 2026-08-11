<script setup lang="ts">
import { Download, ExternalLink, FileText } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { LessonNode } from '@/types/lesson-content';

const props = defineProps<{ node: LessonNode }>();
const attrs = computed(() => props.node.attrs ?? {});
const fileSize = computed(() => {
    const bytes = Number(attrs.value.fileSize ?? 0);

    if (bytes <= 0) {
        return 'PDF';
    }

    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
});
</script>

<template>
    <section
        class="my-7 flex flex-col gap-4 rounded-xl border bg-muted/25 p-4 sm:flex-row sm:items-center"
    >
        <div
            class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-background text-primary shadow-sm"
        >
            <FileText class="size-5" />
        </div>
        <div class="min-w-0 flex-1">
            <p class="font-semibold">{{ attrs.title }}</p>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ attrs.caption || attrs.originalName }} · {{ fileSize }}
            </p>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2">
            <Button size="sm" as-child>
                <a
                    :href="attrs.url as string"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    View <ExternalLink />
                </a>
            </Button>
            <Button size="sm" variant="outline" as-child>
                <a :href="attrs.downloadUrl as string">
                    Download <Download />
                </a>
            </Button>
        </div>
    </section>
</template>
