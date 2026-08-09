<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, UsersRound } from '@lucide/vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { create, index } from '@/routes/admin/users';

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: string | null;
    created_at: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedUsers = {
    data: ManagedUser[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};

defineProps<{
    users: PaginatedUsers;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Users',
                href: index(),
            },
        ],
    },
});

const page = usePage();

function removeUser(user: ManagedUser): void {
    if (!window.confirm(`Delete ${user.name}? This action cannot be undone.`)) {
        return;
    }

    router.delete(UserController.destroy.url(user.id), {
        preserveScroll: true,
    });
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo; Previous', 'Previous')
        .replace('Next &raquo;', 'Next');
}
</script>

<template>
    <Head title="Users" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="User management"
                description="Create accounts and assign one primary role to each user."
            />

            <Button as-child>
                <Link :href="create()">
                    <Plus />
                    Add user
                </Link>
            </Button>
        </div>

        <Card class="gap-0 overflow-hidden py-0">
            <CardContent class="p-0">
                <div v-if="users.data.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-6 py-3 font-medium">User</th>
                                <th class="px-6 py-3 font-medium">Role</th>
                                <th class="px-6 py-3 font-medium">Created</th>
                                <th class="px-6 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="user in users.data"
                                :key="user.id"
                                class="transition-colors hover:bg-muted/30"
                            >
                                <td class="px-6 py-4">
                                    <p class="font-medium">{{ user.name }}</p>
                                    <p class="text-muted-foreground">
                                        {{ user.email }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <Badge v-if="user.role" variant="secondary">
                                        {{ user.role }}
                                    </Badge>
                                    <span v-else class="text-muted-foreground">
                                        Unassigned
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-muted-foreground">
                                    {{ user.created_at ?? '—' }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="outline"
                                            size="icon-sm"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    UserController.edit(user.id)
                                                "
                                                :aria-label="`Edit ${user.name}`"
                                            >
                                                <Pencil />
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="icon-sm"
                                            :disabled="
                                                user.id ===
                                                page.props.auth.user.id
                                            "
                                            :aria-label="`Delete ${user.name}`"
                                            :title="
                                                user.id ===
                                                page.props.auth.user.id
                                                    ? 'You cannot delete your own account.'
                                                    : `Delete ${user.name}`
                                            "
                                            @click="removeUser(user)"
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-else
                    class="flex flex-col items-center justify-center px-6 py-16 text-center"
                >
                    <UsersRound class="mb-4 size-10 text-muted-foreground" />
                    <p class="font-medium">No users found</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Create the first managed account to get started.
                    </p>
                </div>
            </CardContent>
        </Card>

        <div
            v-if="users.total > 0"
            class="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-muted-foreground">
                Showing {{ users.from }}–{{ users.to }} of
                {{ users.total }} users
            </p>
            <nav class="flex flex-wrap gap-1" aria-label="User pagination">
                <Button
                    v-for="link in users.links"
                    :key="link.label"
                    :variant="link.active ? 'default' : 'outline'"
                    size="sm"
                    :disabled="!link.url"
                    as-child
                >
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        :aria-current="link.active ? 'page' : undefined"
                    >
                        {{ paginationLabel(link.label) }}
                    </Link>
                    <span v-else>{{ paginationLabel(link.label) }}</span>
                </Button>
            </nav>
        </div>
    </div>
</template>
