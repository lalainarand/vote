<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    users:   Object,
    filters: Object,
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
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">Utilisateurs</h1>
                <Link :href="`/admin/users/create`"
                      class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    + Nouvel utilisateur
                </Link>
            </div>
        </template>

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
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Votes saisis</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="users.data.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">
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
                                <span class="font-mono text-xs text-gray-500">{{ u.bureau.code }}</span>
                                <span class="ml-2">{{ u.bureau.nom }}</span>
                            </template>
                            <span v-else class="text-gray-400 italic">—</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span :class="u.vote_logs_count > 0 ? 'text-orange-600' : 'text-gray-400'"
                                  class="text-sm font-semibold">
                                {{ u.vote_logs_count }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-3">
                            <Link :href="`/admin/users/${u.id}/edit`"
                                  class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                Modifier
                            </Link>
                            <button v-if="u.vote_logs_count === 0"
                                    @click="deleteUser(u.id, u.name)"
                                    class="text-red-600 hover:text-red-800 text-sm font-medium">
                                Supprimer
                            </button>
                            <span v-else class="text-xs text-gray-400 italic" title="Votes existants">
                                🔒
                            </span>
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
            </ul>
        </div>
    </AuthenticatedLayout>
</template>