<script setup lang="ts">
import LessonCallout from '@/components/lesson/LessonCallout.vue';
import LessonCheckpointNode from '@/components/lesson/LessonCheckpointNode.vue';
import LessonCodeBlock from '@/components/lesson/LessonCodeBlock.vue';
import LessonFileResource from '@/components/lesson/LessonFileResource.vue';
import LessonImageNode from '@/components/lesson/LessonImageNode.vue';
import LessonRichText from '@/components/lesson/LessonRichText.vue';
import LessonVideoNode from '@/components/lesson/LessonVideoNode.vue';
import type { LessonNode } from '@/types/lesson-content';

defineProps<{ node: LessonNode }>();
</script>

<template>
    <LessonRichText v-if="node.type === 'text'" :node="node" />
    <br v-else-if="node.type === 'hardBreak'" />
    <p v-else-if="node.type === 'paragraph'" class="my-4 leading-8">
        <LessonContentNode
            v-for="(child, index) in node.content ?? []"
            :key="index"
            :node="child"
        />
    </p>
    <component
        :is="`h${Number(node.attrs?.level ?? 2)}`"
        v-else-if="node.type === 'heading'"
        :class="[
            'font-semibold tracking-tight text-foreground',
            Number(node.attrs?.level) === 1
                ? 'mt-10 mb-4 text-3xl'
                : Number(node.attrs?.level) === 2
                  ? 'mt-9 mb-3 text-2xl'
                  : 'mt-7 mb-2 text-xl',
        ]"
    >
        <LessonContentNode
            v-for="(child, index) in node.content ?? []"
            :key="index"
            :node="child"
        />
    </component>
    <ul
        v-else-if="node.type === 'bulletList'"
        class="my-5 list-disc space-y-2 pl-7 leading-7"
    >
        <LessonContentNode
            v-for="(child, index) in node.content ?? []"
            :key="index"
            :node="child"
        />
    </ul>
    <ol
        v-else-if="node.type === 'orderedList'"
        :start="Number(node.attrs?.start ?? 1)"
        class="my-5 list-decimal space-y-2 pl-7 leading-7"
    >
        <LessonContentNode
            v-for="(child, index) in node.content ?? []"
            :key="index"
            :node="child"
        />
    </ol>
    <li v-else-if="node.type === 'listItem'">
        <LessonContentNode
            v-for="(child, index) in node.content ?? []"
            :key="index"
            :node="child"
        />
    </li>
    <blockquote
        v-else-if="node.type === 'blockquote'"
        class="my-7 border-l-4 pl-5 text-lg text-muted-foreground italic"
    >
        <LessonContentNode
            v-for="(child, index) in node.content ?? []"
            :key="index"
            :node="child"
        />
    </blockquote>
    <hr v-else-if="node.type === 'horizontalRule'" class="my-9" />
    <div v-else-if="node.type === 'table'" class="my-8 overflow-x-auto">
        <table class="w-full min-w-lg border-collapse text-left text-sm">
            <tbody>
                <LessonContentNode
                    v-for="(child, index) in node.content ?? []"
                    :key="index"
                    :node="child"
                />
            </tbody>
        </table>
    </div>
    <tr v-else-if="node.type === 'tableRow'">
        <LessonContentNode
            v-for="(child, index) in node.content ?? []"
            :key="index"
            :node="child"
        />
    </tr>
    <th
        v-else-if="node.type === 'tableHeader'"
        :colspan="Number(node.attrs?.colspan ?? 1)"
        :rowspan="Number(node.attrs?.rowspan ?? 1)"
        class="border bg-muted/60 px-3 py-2 font-semibold"
    >
        <LessonContentNode
            v-for="(child, index) in node.content ?? []"
            :key="index"
            :node="child"
        />
    </th>
    <td
        v-else-if="node.type === 'tableCell'"
        :colspan="Number(node.attrs?.colspan ?? 1)"
        :rowspan="Number(node.attrs?.rowspan ?? 1)"
        class="border px-3 py-2 align-top"
    >
        <LessonContentNode
            v-for="(child, index) in node.content ?? []"
            :key="index"
            :node="child"
        />
    </td>
    <LessonImageNode v-else-if="node.type === 'lessonImage'" :node="node" />
    <LessonVideoNode v-else-if="node.type === 'externalVideo'" :node="node" />
    <LessonCodeBlock v-else-if="node.type === 'codeBlock'" :node="node" />
    <LessonCallout v-else-if="node.type === 'callout'" :node="node">
        <LessonContentNode
            v-for="(child, index) in node.content ?? []"
            :key="index"
            :node="child"
        />
    </LessonCallout>
    <LessonFileResource v-else-if="node.type === 'lessonFile'" :node="node" />
    <LessonCheckpointNode
        v-else-if="node.type === 'lessonCheckpoint'"
        :node="node"
    />
</template>
