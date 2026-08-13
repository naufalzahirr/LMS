<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
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
        items.push({
            title: 'My Progress',
            href: '/student/progress',
            icon: ChartNoAxesCombined,
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
            title: 'Learning analytics',
            href: '/admin/analytics',
            icon: ChartNoAxesCombined,
        });
        items.push({
            title: 'Progress reports',
            href: '/admin/reports/progress',
            icon: NotebookText,
        });
    }

    if (
        page.props.auth.roles.includes('Tutor') &&
        page.props.auth.permissions.includes('view-class-progress')
    ) {
        items.push({
            title: 'Learning analytics',
            href: '/tutor/analytics',
            icon: ChartNoAxesCombined,
        });
    }

    return items;
});

const academicNavItems = computed<NavItem[]>(() => {
    const tutorCanAuthor =
        page.props.auth.roles.includes('Tutor') &&
        page.props.auth.has_active_teaching_course;

    if (!page.props.auth.roles.includes('Admin') && !tutorCanAuthor) {
        return [];
    }

    const items: NavItem[] = [];

    if (
        tutorCanAuthor ||
        page.props.auth.permissions.includes('manage-programs')
    ) {
        items.push({
            title: 'Programs',
            href: programsIndex(),
            icon: GraduationCap,
        });
    }

    if (
        tutorCanAuthor ||
        page.props.auth.permissions.includes('manage-courses')
    ) {
        items.push({
            title: 'Courses',
            href: coursesIndex(),
            icon: BookOpenCheck,
        });
    }

    if (
        tutorCanAuthor ||
        page.props.auth.permissions.includes('manage-competencies')
    ) {
        items.push({
            title: 'Competencies',
            href: competenciesIndex(),
            icon: Target,
        });
    }

    if (
        tutorCanAuthor ||
        page.props.auth.permissions.includes('manage-modules')
    ) {
        items.push({
            title: 'Modules',
            href: modulesIndex(),
            icon: LibraryBig,
        });
    }

    if (
        tutorCanAuthor ||
        page.props.auth.permissions.includes('manage-lessons')
    ) {
        items.push({
            title: 'Lessons',
            href: lessonsIndex(),
            icon: NotebookText,
        });
    }

    return items;
});

const assessmentNavItems = computed<NavItem[]>(() => {
    const tutorCanAuthor =
        page.props.auth.roles.includes('Tutor') &&
        page.props.auth.has_active_teaching_course;
    const adminCanAuthor =
        page.props.auth.roles.includes('Admin') &&
        page.props.auth.permissions.includes('manage-assessments');

    if (!tutorCanAuthor && !adminCanAuthor) {
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
    const isAdmin = page.props.auth.roles.includes('Admin');
    const isTutor = page.props.auth.roles.includes('Tutor');

    if (
        (!isAdmin && !isTutor) ||
        (isAdmin && !page.props.auth.permissions.includes('manage-classes')) ||
        (isTutor &&
            !page.props.auth.permissions.includes('view-class-progress'))
    ) {
        return [];
    }

    return [
        {
            title: isAdmin ? 'Classes' : 'My classes',
            href: isAdmin ? adminClassesIndex() : tutorClassesIndex(),
            icon: UsersRound,
        },
    ];
});
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
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
