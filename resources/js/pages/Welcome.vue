<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpenCheck,
    ChartNoAxesCombined,
    Target,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard, login } from '@/routes';
</script>

<template>
    <Head title="Welcome" />
    <main class="min-h-screen bg-background text-foreground">
        <div
            class="mx-auto flex min-h-screen max-w-6xl flex-col px-6 py-8 lg:px-8"
        >
            <header class="flex items-center justify-between">
                <AppLogo />
                <Button as-child>
                    <Link :href="$page.props.auth.user ? dashboard() : login()">
                        {{
                            $page.props.auth.user ? 'Open dashboard' : 'Sign in'
                        }}
                        <ArrowRight />
                    </Link>
                </Button>
            </header>

            <section class="flex flex-1 items-center py-16">
                <div
                    class="grid w-full items-center gap-12 lg:grid-cols-[1.1fr_0.9fr]"
                >
                    <div>
                        <p
                            class="mb-4 text-sm font-semibold tracking-widest text-primary uppercase"
                        >
                            Mastery-based learning
                        </p>
                        <h1
                            class="max-w-3xl text-4xl font-bold tracking-tight sm:text-6xl"
                        >
                            Learn, assess, improve, and master each competency.
                        </h1>
                        <p
                            class="mt-6 max-w-2xl text-lg leading-8 text-muted-foreground"
                        >
                            One secure workspace for structured lessons,
                            assessment attempts, remedial learning, and clear
                            progress reporting.
                        </p>
                        <Button class="mt-8" size="lg" as-child>
                            <Link
                                :href="
                                    $page.props.auth.user
                                        ? dashboard()
                                        : login()
                                "
                            >
                                {{
                                    $page.props.auth.user
                                        ? 'Continue learning'
                                        : 'Sign in to continue'
                                }}
                                <ArrowRight />
                            </Link>
                        </Button>
                    </div>

                    <div class="grid gap-4">
                        <Card>
                            <CardContent class="flex items-start gap-4">
                                <BookOpenCheck
                                    class="mt-1 size-6 text-primary"
                                />
                                <div>
                                    <h2 class="font-semibold">
                                        Structured learning
                                    </h2>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        Move through focused lessons organized
                                        by competency.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent class="flex items-start gap-4">
                                <Target class="mt-1 size-6 text-primary" />
                                <div>
                                    <h2 class="font-semibold">
                                        Mastery assessment
                                    </h2>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        Demonstrate understanding with
                                        controlled attempts and feedback.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent class="flex items-start gap-4">
                                <ChartNoAxesCombined
                                    class="mt-1 size-6 text-primary"
                                />
                                <div>
                                    <h2 class="font-semibold">
                                        Actionable progress
                                    </h2>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        Students, tutors, administrators, and
                                        parents see the right next step.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
