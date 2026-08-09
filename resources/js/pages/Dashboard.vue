<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

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
                Welcome, {{ usePage().props.auth.user.name }}
            </h1>
        </div>

        <Card class="max-w-2xl">
            <CardHeader>
                <div
                    class="mb-2 flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <ShieldCheck class="size-5" />
                </div>
                <CardTitle>Foundation is ready</CardTitle>
            </CardHeader>
            <CardContent class="text-sm leading-6 text-muted-foreground">
                Authentication and role-based access are configured. Academic
                modules will be added in a later phase.
            </CardContent>
        </Card>
    </div>
</template>
