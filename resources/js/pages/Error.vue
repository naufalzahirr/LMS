<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Home } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

defineProps<{
    status: number;
    title: string;
    description: string;
}>();

function goBack(): void {
    window.history.back();
}
</script>

<template>
    <Head :title="title" />
    <main class="flex min-h-screen items-center justify-center bg-muted/30 p-6">
        <section
            class="w-full max-w-lg rounded-xl border bg-card p-8 text-center shadow-sm"
        >
            <div class="mb-8 flex justify-center"><AppLogo /></div>
            <p
                class="text-sm font-semibold tracking-widest text-primary uppercase"
            >
                Error {{ status }}
            </p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight">{{ title }}</h1>
            <p class="mt-4 text-muted-foreground">{{ description }}</p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <Button variant="outline" type="button" @click="goBack">
                    <ArrowLeft /> Go back
                </Button>
                <Button as-child>
                    <Link :href="$page.props.auth.user ? dashboard() : '/'">
                        <Home />
                        {{ $page.props.auth.user ? 'Dashboard' : 'Home' }}
                    </Link>
                </Button>
            </div>
        </section>
    </main>
</template>
