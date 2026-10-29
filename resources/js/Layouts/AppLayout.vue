<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import {
    Box,
    ChevronDown,
    Coins,
    Folder,
    Home,
    Scan,
    LogOut,
    Menu,
    PanelLeftClose,
    PenTool,
    Settings,
    Sparkles,
} from 'lucide-vue-next'
import BrandLogo from '@/Components/Brand/BrandLogo.vue'

defineProps<{
    title?: string
    /** Pages with their own flash slot (Plans) suppress the global one. */
    hideFlash?: boolean
}>()

const navItems = [
    { href: '/app', label: 'Home', icon: Home },
    {
        href: '/app/photo-to-drawing',
        label: 'Photo to Drawing',
        icon: Sparkles,
    },
    {
        href: '/app/photo-to-model',
        label: 'Photo to Model',
        icon: Box,
    },
    {
        href: '/app/model-to-drawing',
        label: '3D Model to Drawing',
        icon: Scan,
    },
]

const pathLabels: Record<string, string> = {
    '/app': 'Home',
    '/app/photo-to-drawing': 'Photo to Drawing',
    '/app/photo-to-model': 'Photo to Model',
    '/app/model-to-drawing': '3D Model to Drawing',
    '/app/create-specification': 'Create Specification',
    '/app/projects': 'Projects',
    '/app/settings': 'Settings',
    '/app/plans': 'Plans',
}

const page = usePage()
const sidebarOpen = ref(false)
const profileOpen = ref(false)
const profileRef = ref<HTMLDivElement | null>(null)

const pathname = computed(() => page.url.split('?')[0].split('#')[0])

const pageTitle = computed(() => pathLabels[pathname.value] || 'Workspace')

const user = computed(() => page.props.auth.user)

const flash = computed(() => page.props.flash)

const initials = computed(() => {
    const current = user.value
    if (!current) return ''
    const fromName = current.name
        .split(/\s+/)
        .filter(Boolean)
        .map((word) => word[0])
        .join('')
        .toUpperCase()
    if (fromName) return fromName
    return current.email.slice(0, 2).toUpperCase()
})

const isActive = (href: string) =>
    href === '/app' ? pathname.value === href : pathname.value.startsWith(href)

const handleDocumentMouseDown = (event: MouseEvent) => {
    if (profileRef.value && !profileRef.value.contains(event.target as Node)) {
        profileOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('mousedown', handleDocumentMouseDown)
})

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleDocumentMouseDown)
})

const signOut = () => {
    profileOpen.value = false
    router.post('/signout')
}
</script>

<template>
    <div class="app-shell">
        <Head :title="title" />
        <aside :class="['app-sidebar', { 'is-open': sidebarOpen }]">
            <div class="app-sidebar-brand">
                <BrandLogo href="/" compact />
                <button
                    class="sidebar-close"
                    type="button"
                    aria-label="Close navigation"
                    @click="sidebarOpen = false"
                >
                    <PanelLeftClose :size="20" />
                </button>
            </div>
            <nav aria-label="Workspace">
                <div class="app-nav-group">
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :class="isActive(item.href) ? 'is-active' : ''"
                        :href="item.href"
                        @click="sidebarOpen = false"
                    >
                        <component :is="item.icon" :size="19" aria-hidden="true" />
                        {{ item.label }}
                    </Link>
                </div>
                <div class="app-nav-group">
                    <p>Projects</p>
                    <Link
                        :class="isActive('/app/projects') ? 'is-active' : ''"
                        href="/app/projects"
                        @click="sidebarOpen = false"
                    >
                        <Folder :size="19" aria-hidden="true" />
                        All Projects
                    </Link>
                </div>
                <Link v-if="user?.is_admin" href="/admin/posts" class="app-nav-link" @click="sidebarOpen = false">
                    <PenTool :size="19" aria-hidden="true" />
                    Editorial
                </Link>
            </nav>
            <Link
                class="primary-button app-credits-button"
                :href="route('billing.index')"
                @click="sidebarOpen = false"
            >
                <Coins :size="17" aria-hidden="true" />
                Get more credits
            </Link>
        </aside>
        <button
            v-if="sidebarOpen"
            class="sidebar-backdrop"
            type="button"
            aria-label="Close navigation overlay"
            @click="sidebarOpen = false"
        />
        <div class="app-main-area">
            <header class="app-topbar">
                <div class="app-topbar-title">
                    <button
                        class="app-menu-trigger"
                        type="button"
                        aria-label="Open navigation"
                        @click="sidebarOpen = true"
                    >
                        <Menu :size="22" />
                    </button>
                    <PenTool :size="18" aria-hidden="true" />
                    <span>{{ pageTitle }}</span>
                </div>
                <div ref="profileRef" class="profile-menu">
                    <button
                        class="profile-trigger"
                        type="button"
                        :aria-expanded="profileOpen"
                        aria-haspopup="menu"
                        @click="profileOpen = !profileOpen"
                    >
                        <span>{{ initials }}</span>
                        <ChevronDown :size="14" aria-hidden="true" />
                    </button>
                    <div v-if="profileOpen" class="profile-popover" role="menu">
                        <div class="profile-summary">
                            <span>{{ initials }}</span>
                            <div>
                                <strong>{{ user?.name }}</strong>
                                <small>{{ user?.email }}</small>
                            </div>
                        </div>
                        <Link :href="route('settings')" role="menuitem" @click="profileOpen = false">
                            <Settings :size="17" aria-hidden="true" />
                            Settings
                        </Link>
                        <Link
                            :href="route('billing.index')"
                            role="menuitem"
                            @click="profileOpen = false"
                        >
                            <Coins :size="17" aria-hidden="true" />
                            Plans
                        </Link>
                        <button type="button" role="menuitem" @click="signOut">
                            <LogOut :size="17" aria-hidden="true" />
                            Sign out
                        </button>
                    </div>
                </div>
            </header>
            <main id="main-content" class="app-content">
                <template v-if="!hideFlash">
                    <p v-if="flash.error" class="workflow-error error-text" role="status">
                        {{ flash.error }}
                    </p>
                    <p v-if="flash.success" class="settings-status" role="status">
                        {{ flash.success }}
                    </p>
                    <p v-if="flash.info" class="settings-status" role="status">
                        {{ flash.info }}
                    </p>
                </template>
                <slot />
            </main>
        </div>
    </div>
</template>
