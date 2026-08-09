<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ProgramController from '@/actions/App/Http/Controllers/Admin/ProgramController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { create, index } from '@/routes/admin/programs';
import type { AcademicStatusOption } from '@/types/academic';

defineProps<{ statuses: AcademicStatusOption[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Programs', href: index() },
            { title: 'Create program', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Create program" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Create program"
            description="Add a top-level academic program."
        />

        <Card class="max-w-2xl">
            <CardContent>
                <Form
                    v-bind="ProgramController.store.form()"
                    reset-on-success
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input id="name" name="name" required autofocus />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="code">Code</Label>
                            <Input
                                id="code"
                                name="code"
                                placeholder="Optional"
                            />
                            <InputError :message="errors.code" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="slug">Slug</Label>
                            <Input
                                id="slug"
                                name="slug"
                                placeholder="Generated from name when blank"
                            />
                            <InputError :message="errors.slug" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <Textarea id="description" name="description" />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="status">Status</Label>
                        <Select name="status" default-value="active" required>
                            <SelectTrigger id="status" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="status in statuses"
                                    :key="status.value"
                                    :value="status.value"
                                >
                                    {{ status.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.status" />
                    </div>
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" as-child>
                            <Link :href="index()">Cancel</Link>
                        </Button>
                        <Button type="submit" :disabled="processing">
                            Create program
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
