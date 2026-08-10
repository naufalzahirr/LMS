<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BookOpenCheck, ShieldCheck, UsersRound } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as adminClassesIndex } from '@/routes/admin/classes';
import { index as studentClassesIndex } from '@/routes/student/classes';
import { index as tutorClassesIndex } from '@/routes/tutor/classes';

const page = usePage();
const primaryEntry = computed(() => {
    if (page.props.auth.roles.includes('Admin')) {
        return {
            title: 'Manage classes',
            description:
                'Manage class delivery, enrollments, Tutor assignments, and progress.',
            href: adminClassesIndex(),
            icon: ShieldCheck,
        };
    }

    if (page.props.auth.roles.includes('Tutor')) {
        return {
            title: 'My classes',
            description:
                'Open an assigned class to review content and Student progress.',
            href: tutorClassesIndex(),
            icon: UsersRound,
        };
    }

    if (page.props.auth.roles.includes('Student')) {
        return {
            title: 'My Learning',
            description:
                'Continue active lessons or revisit completed classes.',
            href: studentClassesIndex(),
            icon: BookOpenCheck,
        };
    }

    return null;
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <p class="text-sm font-medium text-muted-foreground">
                Mastery Learning Center
            </p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                Welcome, {{ page.props.auth.user.name }}
            </h1>
        </div>

        <Card v-if="primaryEntry" class="max-w-2xl">
            <CardHeader>
                <div
                    class="mb-2 flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <component :is="primaryEntry.icon" class="size-5" />
                </div>
                <CardTitle>{{ primaryEntry.title }}</CardTitle>
            </CardHeader>
            <CardContent
                class="space-y-4 text-sm leading-6 text-muted-foreground"
            >
                <p>{{ primaryEntry.description }}</p>
                <Button as-child>
                    <Link :href="primaryEntry.href"
                        >Open {{ primaryEntry.title }}</Link
                    >
                </Button>
            </CardContent>
        </Card>
        <Card v-else class="max-w-2xl">
            <CardHeader><CardTitle>Parent dashboard</CardTitle></CardHeader>
            <CardContent class="text-sm text-muted-foreground">
                Parent progress views will be available in a later release.
            </CardContent>
        </Card>
    </div>
</template>
