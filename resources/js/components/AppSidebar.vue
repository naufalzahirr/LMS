<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    BookOpenCheck,
    ChartNoAxesCombined,
    CircleHelp,
    ClipboardCheck,
    Database,
    GraduationCap,
    LayoutGrid,
    LibraryBig,
    NotebookText,
    Target,
    UserRoundCog,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as assessmentsIndex } from '@/routes/admin/assessments';
import { index as adminClassesIndex } from '@/routes/admin/classes';
import { index as competenciesIndex } from '@/routes/admin/competencies';
import { index as coursesIndex } from '@/routes/admin/courses';
import { index as lessonsIndex } from '@/routes/admin/lessons';
import { index as modulesIndex } from '@/routes/admin/modules';
import { index as parentStudentsIndex } from '@/routes/admin/parent-students';
import { index as programsIndex } from '@/routes/admin/programs';
import { index as questionBanksIndex } from '@/routes/admin/question-banks';
import { index as questionsIndex } from '@/routes/admin/questions';
import { index as usersIndex } from '@/routes/admin/users';
import { index as studentClassesIndex } from '@/routes/student/classes';
import { index as tutorClassesIndex } from '@/routes/tutor/classes';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href:
                page.props.auth.roles.includes('Parent') &&
                !page.props.auth.roles.some((role) =>
                    ['Admin', 'Tutor', 'Student'].includes(role),
                )
                    ? '/parent/dashboard'
                    : dashboard(),
            icon: LayoutGrid,
        },
    ];

    if (page.props.auth.permissions.includes('manage-users')) {
        items.push({
            title: 'Users',
            href: usersIndex(),
            icon: UsersRound,
        });
    }

    if (page.props.auth.roles.includes('Student')) {
        items.push({
            title: 'My Learning',
            href: studentClassesIndex(),
            icon: BookOpenCheck,
        });
    }

    if (page.props.auth.permissions.includes('manage-parent-relationships')) {
        items.push({
            title: 'Parent–students',
            href: parentStudentsIndex(),
            icon: UserRoundCog,
        });
    }

    if (page.props.auth.permissions.includes('view-all-progress')) {
        items.push({
            title: 'Progress reports',
            href: '/admin/reports/progress',
            icon: ChartNoAxesCombined,
        });
    }

    return items;
});

const academicNavItems = computed<NavItem[]>(() => {
    if (
        !page.props.auth.roles.some((role) => ['Admin', 'Tutor'].includes(role))
    ) {
        return [];
    }

    const items: NavItem[] = [
        {
            title: 'Programs',
            href: programsIndex(),
            icon: GraduationCap,
        },
        {
            title: 'Courses',
            href: coursesIndex(),
            icon: BookOpenCheck,
        },
        {
            title: 'Competencies',
            href: competenciesIndex(),
            icon: Target,
        },
        {
            title: 'Modules',
            href: modulesIndex(),
            icon: LibraryBig,
        },
        {
            title: 'Lessons',
            href: lessonsIndex(),
            icon: NotebookText,
        },
    ];

    return items;
});

const assessmentNavItems = computed<NavItem[]>(() => {
    if (
        !page.props.auth.roles.some((role) => ['Admin', 'Tutor'].includes(role))
    ) {
        return [];
    }

    return [
        { title: 'Question banks', href: questionBanksIndex(), icon: Database },
        { title: 'Questions', href: questionsIndex(), icon: CircleHelp },
        {
            title: 'Assessments',
            href: assessmentsIndex(),
            icon: ClipboardCheck,
        },
    ];
});

const deliveryNavItems = computed<NavItem[]>(() => {
    if (
        !page.props.auth.roles.some((role) => ['Admin', 'Tutor'].includes(role))
    ) {
        return [];
    }

    return [
        {
            title: page.props.auth.roles.includes('Admin')
                ? 'Classes'
                : 'My classes',
            href: page.props.auth.roles.includes('Admin')
                ? adminClassesIndex()
                : tutorClassesIndex(),
            icon: UsersRound,
        },
    ];
});

const footerNavItems: NavItem[] = [
    {
        title: 'Laravel Documentation',
        href: 'https://laravel.com/docs/13.x',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain
                v-if="academicNavItems.length"
                label="Academic"
                :items="academicNavItems"
            />
            <NavMain
                v-if="assessmentNavItems.length"
                label="Assessment"
                :items="assessmentNavItems"
            />
            <NavMain
                v-if="deliveryNavItems.length"
                label="Delivery"
                :items="deliveryNavItems"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
