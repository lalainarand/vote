<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
    bureaux: Object,
    filters: Object,
})

const statusLabel = {
    pending:   { label: 'En attente',  cls: 'bg-gray-100 text-gray-600' },
    counting:  { label: 'Comptage',    cls: 'bg-blue-100 text-blue-700' },
    pv_entry:  { label: 'Saisie PV',   cls: 'bg-yellow-100 text-yellow-700' },
    pv_admin:  { label: 'PV Admin',    cls: 'bg-purple-100 text-purple-700' },
    validated: { label: 'Validé',      cls: 'bg-green-100 text-green-700' },
    anomaly:   { label: 'Anomalie',    cls: 'bg-red-100 text-red-700' },
}

const filterStatus = (status) => {
    router.get('/admin/bureaux', {
        ...props.filters,
        status: status || undefined,
    }, { preserveState: true })
}

const filterNoPv = () => {
    router.get('/admin/bureaux', {
        ...props.filters,
        no_pv: props.filters.no_pv ? undefined : 1,
    }, { preserveState: true })
}

const deleteBureau = (id) => {
    if (confirm('Supprimer ce bureau ?')) {
        router.delete(`/admin/bureaux/${id}`)
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">Bureaux de vote</h1>
                <Link href="/admin/bureaux/create"
                      class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    + Nouveau bureau
                </Link>
            </div>
        </template>

        <!-- Filtres -->
        <div class="bg-white rounded-xl border border-gray-100 p-4 mb-4 flex flex-wrap gap-2">
            <button @click="filterStatus(null)"
                    :class="!filters.status ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors">
                Tous
            </button>
            <button v-for="(label, key) in statusLabel" :key="key"
                    @click="filterStatus(key)"
                    :class="filters.status === key ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors">
                {{ label.label }}
            </button>
            <button @click="filterNoPv"
                    :class="filters.no_pv ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700'"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ml-auto">
                Sans PV saisi
            </button>
        </div>

        <!-- Tableau -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Nom</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Opérateur</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Statut</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Photos</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="bureau in bureaux.data" :key="bureau.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ bureau.code }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ bureau.nom }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            {{ bureau.users?.[0]?.name ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span :class="statusLabel[bureau.status]?.cls ?? 'bg-gray-100 text-gray-600'"
                                  class="text-xs font-medium px-2.5 py-1 rounded-full">
                                {{ statusLabel[bureau.status]?.label ?? bureau.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <Link :href="`/admin/bureaux/${bureau.id}/photos`"
                                  class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full transition-colors"
                                  :class="bureau.bulletin_images_count > 0
                                    ? 'bg-blue-50 text-blue-700 hover:bg-blue-100'
                                    : 'bg-gray-100 text-gray-400 hover:bg-gray-200'">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                    <circle cx="12" cy="13" r="3"/>
                                </svg>
                                {{ bureau.bulletin_images_count }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <Link v-if="bureau.status ==='validated'" :href="`/admin/bureaux/${bureau.id}/pv-manuel`"
                                  class="text-purple-600 hover:text-purple-800 text-sm font-medium">
                                Voir PV
                            </Link>
                            <Link :href="`/admin/bureaux/${bureau.id}/edit`"
                                  class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                Modifier
                            </Link>
                            <button v-if="bureau.status !== 'validated'"
                                    @click="deleteBureau(bureau.id)"
                                    class="text-red-600 hover:text-red-800 text-sm font-medium">
                                Supprimer
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="bureaux.links.length > 3" class="px-4 py-3 border-t border-gray-100">
                <div class="flex justify-center gap-1">
                    <Link v-for="link in bureaux.links" :key="link.label"
                          :href="link.url || '#'"
                          :class="link.active ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                          class="px-3 py-1 rounded text-sm"
                          v-html="link.label">
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>