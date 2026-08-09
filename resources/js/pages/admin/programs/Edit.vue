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
import { index } from '@/routes/admin/programs';
import type { AcademicStatus, AcademicStatusOption } from '@/types/academic';

type EditableProgram = {
    id: number;
    name: string;
    slug: string;
    code: string | null;
    description: string | null;
    status: AcademicStatus;
};

defineProps<{
    program: EditableProgram;
    statuses: AcademicStatusOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Programs', href: index() },
            { title: 'Edit program', href: index() },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${program.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Edit program"
            description="Update program details and availability."
        />

        <Card class="max-w-2xl">
            <CardContent>
                <Form
                    v-bind="ProgramController.update.form(program.id)"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="program.name"
                            required
                            autofocus
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="code">Code</Label>
                            <Input
                                id="code"
                                name="code"
                                :default-value="program.code ?? ''"
                            />
                            <InputError :message="errors.code" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="slug">Slug</Label>
                            <Input
                                id="slug"
                                name="slug"
                                :default-value="program.slug"
                                required
                            />
                            <InputError :message="errors.slug" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <Textarea
                            id="description"
                            name="description"
                            :default-value="program.description ?? ''"
                        />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="status">Status</Label>
                        <Select
                            name="status"
                            :default-value="program.status"
                            required
                        >
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
                            Save changes
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
