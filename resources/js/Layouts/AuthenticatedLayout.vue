<script setup>
import { ref, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'

const page = usePage()

const sidebarOpen = ref(false)

const user = computed(() => page.props.auth?.user ?? {})
const flash = computed(() => page.props.flash ?? {})

const isAdmin = computed(() => {
    const roles = user.value?.roles ?? []
    return Array.isArray(roles) && roles.some(r => r.name === 'admin')
})

const nav = computed(() => (isAdmin.value ? adminNav : operatorNav))

const isActive = (item) => {
    if (!item.href) return false
    return page.url.startsWith(item.href)
}

const initials = computed(() => {
    const name = user.value?.name ?? ''
    return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2)
})

const logout = () => router.post('/logout')

const adminNav = [
    {
        label: 'Tableau de bord',
        href: '/admin/dashboard',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>`
    },
    {
        label: 'Bureaux de vote',
        href: '/admin/bureaux',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>`
    },
    {
        label: 'Candidats',
        href: '/admin/candidats',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>`
    },
    {
        label: 'Utilisateurs',
        href: '/admin/users',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>`
    },
    {
        label: 'Résultats',
        href: '/admin/resultats',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>`
    },
    {
        label: 'Audit',
        href: '/admin/audit',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>`
    },
]

const operatorNav = [
    {
        label: 'Mon bureau',
        href: '/operator/dashboard',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>`
    },
    {
        label: 'Comptage',
        href: '/operator/comptage',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>`
    },
    {
        label: 'Vérification PV',
        href: '/operator/pv',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>`
    },
    {
        label: 'Clôture',
        href: '/operator/cloture',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>`
    },
]
</script>

<template>
    <!--
        STRUCTURE CORRIGÉE
        ──────────────────
        Desktop : sidebar fixed 256px + contenu avec lg:pl-64
        Mobile  : sidebar fixed off-screen, overlay + hamburger
    -->
    <div class="min-h-screen bg-gray-50">

        <!-- ── Overlay mobile ────────────────────────────────────── -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="sidebarOpen"
                @click="sidebarOpen = false"
                class="fixed inset-0 bg-black/50 z-20 lg:hidden"
            />
        </Transition>

        <!-- ── Sidebar (fixed, toujours dans le DOM) ─────────────── -->
        <aside
            class="fixed top-0 left-0 h-screen w-64 bg-blue-950 text-white z-30 flex flex-col
                   transition-transform duration-200 ease-in-out
                   -translate-x-full lg:translate-x-0"
            :class="{ '!translate-x-0': sidebarOpen }"
        >
            <!-- Logo -->
            <div class="flex items-center gap-3 px-5 py-5 border-b border-blue-800/60 flex-shrink-0">
                <div class="w-9 h-9 bg-blue-600 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-sm leading-tight">Application Vote</div>
                </div>
            </div>

            <!-- Rôle badge -->
            <div class="px-5 py-3 border-b border-blue-800/40 flex-shrink-0">
                <span
                    :class="isAdmin
                        ? 'bg-amber-500/20 text-amber-300 border-amber-500/30'
                        : 'bg-green-500/20 text-green-300 border-green-500/30'"
                    class="inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-full border uppercase tracking-wider"
                >
                    <span class="w-1.5 h-1.5 rounded-full" :class="isAdmin ? 'bg-amber-400' : 'bg-green-400'"></span>
                    {{ isAdmin ? 'Administrateur' : 'Opérateur' }}
                </span>
            </div>

            <!-- Navigation (scrollable si beaucoup d'items) -->
            <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
                <Link
                    v-for="item in nav"
                    :key="item.href"
                    :href="item.href"
                    @click="sidebarOpen = false"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium
                           transition-colors duration-150"
                    :class="isActive(item)
                        ? 'bg-blue-600 text-white shadow-sm'
                        : 'text-blue-200 hover:bg-blue-800/50 hover:text-white'"
                >
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor"
                         stroke-width="1.8" viewBox="0 0 24 24" v-html="item.icon" />
                    {{ item.label }}
                </Link>
            </nav>

            <!-- Utilisateur + Logout -->
            <div class="border-t border-blue-800/60 px-3 py-4 flex-shrink-0">
                <div class="flex items-center gap-3 px-2 mb-3 min-w-0">
                    <div class="w-8 h-8 rounded-full bg-blue-600/60 border border-blue-500/50
                                flex items-center justify-center text-xs font-semibold flex-shrink-0">
                        {{ initials }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-white truncate">{{ user?.name }}</div>
                        <div class="text-xs text-blue-300 truncate">{{ user?.email }}</div>
                    </div>
                </div>
                <button
                    @click="logout"
                    class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm
                           text-blue-200 hover:bg-red-500/20 hover:text-red-300 transition-colors"
                >
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor"
                         stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Déconnexion
                </button>
            </div>
        </aside>

        <!-- ── Zone droite : topbar + contenu ────────────────────── -->
        <!--
            lg:pl-64  → pousse tout le contenu de 256px (= w-64) sur desktop
            pour éviter que la sidebar fixed ne le recouvre
        -->
        <div class="lg:pl-64 flex flex-col min-h-screen">

            <!-- Topbar sticky -->
            <header class="sticky top-0 z-10 bg-white border-b border-gray-200">
                <div class="flex items-center gap-4 px-4 py-3">

                    <!-- Hamburger mobile -->
                    <button
                        @click="sidebarOpen = true"
                        class="lg:hidden p-1.5 rounded-lg hover:bg-gray-100 text-gray-500 transition-colors"
                        aria-label="Ouvrir le menu"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                             stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    <!-- Titre de page (slot) -->
                    <div class="flex-1 min-w-0">
                        <slot name="header">
                            <h1 class="text-base font-semibold text-gray-800 truncate">Application Vote</h1>
                        </slot>
                    </div>

                    <!-- Utilisateur desktop -->
                    <div class="hidden sm:flex items-center gap-2 flex-shrink-0">
                        <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-700
                                    flex items-center justify-center text-xs font-semibold">
                            {{ initials }}
                        </div>
                        <span class="text-sm font-medium text-gray-700">{{ user?.name }}</span>
                    </div>
                </div>
            </header>

            <!-- Flash messages -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="flash.success || flash.error" class="px-6 pt-4">
                    <div v-if="flash.success"
                         class="flex items-center gap-3 bg-green-50 border border-green-200
                                text-green-800 px-4 py-3 rounded-xl text-sm">
                        <svg class="w-4 h-4 text-green-600 flex-shrink-0" fill="none"
                             stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ flash.success }}
                    </div>
                    <div v-if="flash.error"
                         class="flex items-center gap-3 bg-red-50 border border-red-200
                                text-red-800 px-4 py-3 rounded-xl text-sm">
                        <svg class="w-4 h-4 text-red-600 flex-shrink-0" fill="none"
                             stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        {{ flash.error }}
                    </div>
                </div>
            </Transition>

            <!-- Contenu principal -->
            <main class="flex-1 p-6">
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
/* Animations compteur */
@keyframes pulse-counter {
    0%, 100% { transform: scale(1); }
    50%       { transform: scale(1.05); }
}
.counter-update {
    animation: pulse-counter 0.3s ease;
}
</style>