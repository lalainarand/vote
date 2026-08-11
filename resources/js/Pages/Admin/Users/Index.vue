<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { ref, computed } from 'vue'

const props = defineProps({
    users:         Object,
    filters:       Object,
    pending_users: { type: Array, default: () => [] },
})

const search = ref(props.filters.search || '')

const roleLabel = {
    admin:    { label: 'Administrateur', cls: 'bg-amber-100 text-amber-700' },
    operator: { label: 'Opérateur',      cls: 'bg-green-100 text-green-700' },
    none:     { label: 'Aucun',          cls: 'bg-gray-100 text-gray-600' },
}

const filterByRole = (role) => {
    router.get('/admin/users', {
        ...props.filters,
        role: role || undefined,
    }, { preserveState: true })
}

const doSearch = () => {
    router.get('/admin/users', {
        ...props.filters,
        search: search.value || undefined,
    }, { preserveState: true })
}

const deleteUser = (id, name) => {
    if (confirm(`Supprimer l'utilisateur "${name}" ?`)) {
        router.delete(`/admin/users/${id}`)
    }
}

// Activation / désactivation du compte (bloque la connexion, ferme la session en cours)
const toggleActive = (u) => {
    const action = u.is_active ? 'désactiver' : 'activer'
    if (confirm(`Confirmer : ${action} le compte de "${u.name}" ?`)) {
        router.patch(`/admin/users/${u.id}/toggle-active`, {}, { preserveScroll: true })
    }
}

// Autorisation d'accès : tant qu'un compte n'est pas approuvé, il reste bloqué
// sur la page d'attente après connexion (voir EnsureUserIsApproved).
const toggleApproved = (u) => {
    if (u.is_approved) {
        if (confirm(`Révoquer l'autorisation d'accès de "${u.name}" ?`)) {
            router.patch(`/admin/users/${u.id}/toggle-approved`, {}, { preserveScroll: true })
        }
    } else {
        router.patch(`/admin/users/${u.id}/toggle-approved`, {}, { preserveScroll: true })
    }
}

// Mot de passe masqué par défaut, révélé au clic (évite l'affichage en clair permanent)
const revealed = ref({})
const toggleReveal = (id) => { revealed.value[id] = !revealed.value[id] }

const exportUrl = computed(() => {
    const params = new URLSearchParams()
    if (props.filters.role) params.set('role', props.filters.role)
    if (props.filters.search) params.set('search', props.filters.search)
    const qs = params.toString()
    return `/admin/users/export${qs ? '?' + qs : ''}`
})
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">Utilisateurs</h1>
                <div class="flex items-center gap-2">
                    <a :href="exportUrl"
                       class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                        Exporter (avec mots de passe)
                    </a>
                    <Link :href="`/admin/users/create`"
                          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        + Nouvel utilisateur
                    </Link>
                </div>
            </div>
        </template>

        <!-- Comptes en attente d'autorisation -->
        <div v-if="pending_users.length > 0" class="mb-4 bg-amber-50 border border-amber-200 rounded-xl p-4">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                <h2 class="text-sm font-semibold text-amber-900">
                    {{ pending_users.length }} compte{{ pending_users.length > 1 ? 's' : '' }} en attente d'autorisation
                </h2>
            </div>
            <!-- <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div v-for="u in pending_users" :key="u.id"
                     class="bg-white border border-amber-200 rounded-lg p-4">
                    <div class="font-medium text-gray-900 text-sm">{{ u.name }}</div>
                    <div class="border-t border-gray-100 my-2"></div>
                    <div class="text-xs text-gray-500 mb-3">
                        Statut : <span class="text-amber-700 font-medium">En attente</span>
                    </div>
                    <button @click="toggleApproved(u)"
                            class="w-full flex items-center justify-center gap-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-semibold py-2 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        Autoriser l'accès
                    </button>
                </div>
            </div> -->
        </div>

        <!-- Filtres -->
        <div class="bg-white rounded-xl border border-gray-100 p-4 mb-4 flex flex-wrap gap-2 items-center">
            <button @click="filterByRole(null)"
                    :class="!filters.role ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors">
                Tous
            </button>
            <button @click="filterByRole('admin')"
                    :class="filters.role === 'admin' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-700'"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors">
                Administrateurs
            </button>
            <button @click="filterByRole('operator')"
                    :class="filters.role === 'operator' ? 'bg-green-500 text-white' : 'bg-gray-100 text-gray-700'"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors">
                Opérateurs
            </button>

            <div class="ml-auto flex items-center gap-2">
                <input v-model="search" type="text" placeholder="Rechercher..."
                       @keyup.enter="doSearch"
                       class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                <button @click="doSearch"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg text-sm font-medium">
                    Rechercher
                </button>
            </div>
        </div>

        <!-- Tableau -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Nom</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Rôle</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Bureau assigné</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Mot de passe</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Statut</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Autorisation</th>
                        <!-- <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Votes saisis</th> -->
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="users.data.length === 0">
                        <td colspan="9" class="px-4 py-8 text-center text-sm text-gray-400">
                            Aucun utilisateur trouvé
                        </td>
                    </tr>
                    <tr v-for="u in users.data" :key="u.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ u.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ u.email }}</td>
                        <td class="px-4 py-3">
                            <span :class="roleLabel[u.role]?.cls ?? 'bg-gray-100 text-gray-600'"
                                  class="text-xs font-medium px-2.5 py-1 rounded-full">
                                {{ roleLabel[u.role]?.label ?? u.role }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            <template v-if="u.bureau">
                                <span class="font-mono text-xs text-gray-500">
                                    {{ u.bureau.code }} -
                                </span>

                                <span
                                    class="ml-2 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="u.bureau.is_procuration
                                        ? 'bg-purple-100 text-purple-700'
                                        : 'bg-blue-100 text-blue-700'"
                                >
                                    {{ u.bureau.is_procuration ? 'Procuration' : 'Individuelle' }}
                                </span>
                            </template>

                            <span v-else class="text-gray-400 italic">—</span>
                        </td>
                        <!-- Mot de passe : masqué par défaut, révélé au clic -->
                        <td class="px-4 py-3">
                            <div v-if="u.password_plain" class="flex items-center gap-2">
                                <span class="font-mono text-xs text-gray-800">
                                    {{ revealed[u.id] ? u.password_plain : '••••••••••••' }}
                                </span>
                                <button @click="toggleReveal(u.id)"
                                        class="text-gray-400 hover:text-gray-600"
                                        :title="revealed[u.id] ? 'Masquer' : 'Afficher'">
                                    <svg v-if="!revealed[u.id]" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                    </svg>
                                </button>
                            </div>
                            <span v-else class="text-gray-400 italic text-xs" title="Mot de passe antérieur à cette fonctionnalité, non récupérable">—</span>
                        </td>
                        <!-- Statut du compte : actif / désactivé -->
                        <td class="px-4 py-3 text-center">
                            <button @click="toggleActive(u)"
                                    class="text-xs font-semibold px-2.5 py-1 rounded-full transition-colors"
                                    :class="u.is_active
                                        ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                        : 'bg-red-100 text-red-700 hover:bg-red-200'"
                                    :title="u.is_active ? 'Cliquer pour désactiver' : 'Cliquer pour activer'">
                                {{ u.is_active ? 'Actif' : 'Désactivé' }}
                            </button>
                        </td>
                        <!-- Autorisation d'accès (is_approved) -->
                        <td class="px-4 py-3 text-center">
                            <button @click="toggleApproved(u)"
                                    class="text-xs font-semibold px-2.5 py-1 rounded-full transition-colors"
                                    :class="u.is_approved
                                        ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                        : 'bg-amber-100 text-amber-700 hover:bg-amber-200'"
                                    :title="u.is_approved ? 'Cliquer pour révoquer' : 'Cliquer pour autoriser'">
                                {{ u.is_approved ? 'Approuvé' : 'En attente' }}
                            </button>
                        </td>
                        <!-- <td class="px-4 py-3 text-center">
                            <span :class="u.vote_logs_count > 0 ? 'text-orange-600' : 'text-gray-400'"
                                  class="text-sm font-semibold">
                                {{ u.vote_logs_count }}
                            </span>
                        </td> -->
                        <td class="px-4 py-3 text-right">
    <div class="flex items-center justify-end gap-2">
        <!-- Modifier -->
        <Link
            :href="`/admin/users/${u.id}/edit`"
            class="inline-flex items-center justify-center w-8 h-8 rounded-lg
                   text-blue-600 hover:text-blue-800 hover:bg-blue-50
                   transition-colors"
            title="Modifier"
        >
            <PencilSquareIcon class="w-5 h-5" />
        </Link>

        <!-- Supprimer -->
        <button
            v-if="u.vote_logs_count === 0"
            @click="deleteUser(u.id, u.name)"
            class="inline-flex items-center justify-center w-8 h-8 rounded-lg
                   text-red-600 hover:text-red-800 hover:bg-red-50
                   transition-colors"
            title="Supprimer"
        >
            <TrashIcon class="w-5 h-5" />
        </button>

        <!-- Suppression impossible -->
        <span
            v-else
            class="inline-flex items-center justify-center w-8 h-8 rounded-lg
                   text-gray-400 bg-gray-50 cursor-not-allowed"
            title="Impossible de supprimer : des votes existent"
        >
            🔒
        </span>
    </div>
</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="users.links.length > 3" class="px-4 py-3 border-t border-gray-100">
                <div class="flex justify-center gap-1">
                    <Link v-for="link in users.links" :key="link.label"
                          :href="link.url || '#'"
                          :class="link.active ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                          class="px-3 py-1 rounded text-sm"
                          v-html="link.label">
                    </Link>
                </div>
            </div>
        </div>

        <!-- Info contraintes -->
        <div class="mt-4 bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
            <strong>Contraintes :</strong>
            <ul class="list-disc list-inside mt-1 space-y-0.5">
                <li>Un opérateur ne peut être assigné qu'à un seul bureau</li>
                <li>Un utilisateur ayant participé à un comptage ne peut pas être supprimé (traçabilité)</li>
                <li>Mot de passe : uniquement chiffres, lettres et les symboles # * . " @ -</li>
                <li>Un compte désactivé ne peut plus se connecter (session en cours fermée immédiatement) et ne peut pas se désactiver lui-même</li>
                <li>Un compte non autorisé peut se connecter mais reste bloqué sur une page d'attente jusqu'à autorisation</li>
            </ul>
        </div>

        <!-- Confidentialité -->
        <div class="mt-3 bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-800">
            🔒 Les mots de passe affichés/exportés ici sont confidentiels. Ne partagez le fichier exporté
            que par un canal sécurisé, et supprimez-le une fois les identifiants transmis aux opérateurs.
        </div>
    </AuthenticatedLayout>
</template>