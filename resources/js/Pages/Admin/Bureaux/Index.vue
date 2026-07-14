<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

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
    if (confirm('Supprimer ce bureau ? Cette action est irréversible.')) {
        router.delete(`/admin/bureaux/${id}`)
    }
}

// --- Tooltip réinitialisations (Teleport pour échapper à l'overflow-hidden du wrapper) ---
const tooltipVisible = ref(false)
const tooltipStyle = ref({})
const tooltipData = ref(null)

const showTooltip = (event, resetInfo) => {
    const rect = event.currentTarget.getBoundingClientRect()
    tooltipStyle.value = {
        top: `${rect.top - 8}px`,
        left: `${rect.left + rect.width / 2}px`,
        transform: 'translate(-50%, -100%)',
    }
    tooltipData.value = resetInfo
    tooltipVisible.value = true
}

const hideTooltip = () => {
    tooltipVisible.value = false
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">Bureaux de vote</h1>
                <Link href="/admin/bureaux/create"
                      class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
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
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden shadow-sm">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Nom</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Opérateur</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Compteur</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Réinit.</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Photos</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="bureau in bureaux.data" :key="bureau.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 text-sm font-mono text-gray-900 font-medium">{{ bureau.code }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ bureau.nom }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ bureau.user_name }}</td>

                        <!-- Compteur en temps réel -->
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center gap-1.5 text-sm font-bold text-gray-900 bg-gray-100 px-2.5 py-1 rounded-md">
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                {{ bureau.live_votes }}
                            </span>
                        </td>

                        <!-- Réinitialisations avec Tooltip (Teleport) -->
                        <td class="px-4 py-3 text-center">
                            <div v-if="bureau.reset_count > 0"
                                 class="inline-block cursor-help"
                                 @mouseenter="showTooltip($event, bureau.latest_reset)"
                                 @mouseleave="hideTooltip">
                                <span class="bg-red-100 text-red-700 text-xs font-bold px-2.5 py-1 rounded-full border border-red-200">
                                    {{ bureau.reset_count }}
                                </span>
                            </div>
                            <span v-else class="text-gray-400 text-xs font-medium">0</span>
                        </td>

                        <!-- Photos -->
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

                        <!-- Actions -->
                        <td class="px-4 py-3 text-right space-x-2">
                            <Link :href="`/admin/bureaux/${bureau.id}/pv-manuel`"
                                  class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold px-2 py-1 rounded hover:bg-indigo-50 transition-colors"
                                  title="Voir ou saisir le PV (données en temps réel)">
                                PV
                            </Link>

                            <Link :href="`/admin/bureaux/${bureau.id}/edit`"
                                  class="text-blue-600 hover:text-blue-800 text-xs font-semibold px-2 py-1 rounded hover:bg-blue-50 transition-colors">
                                Modifier
                            </Link>

                            <button v-if="bureau.status !== 'validated'"
                                    @click="deleteBureau(bureau.id)"
                                    class="text-red-600 hover:text-red-800 text-xs font-semibold px-2 py-1 rounded hover:bg-red-50 transition-colors">
                                Suppr.
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="bureaux.links.length > 3" class="px-4 py-3 border-t border-gray-100 bg-gray-50">
                <div class="flex justify-center gap-1">
                    <Link v-for="link in bureaux.links" :key="link.label"
                          :href="link.url || '#'"
                          :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100'"
                          class="px-3 py-1.5 border rounded-md text-sm transition-colors"
                          v-html="link.label">
                    </Link>
                </div>
            </div>
        </div>

        <!-- Tooltip téléporté hors du tableau, donc plus jamais coupé par overflow-hidden -->
        <Teleport to="body">
            <div v-if="tooltipVisible && tooltipData"
                 :style="tooltipStyle"
                 class="fixed w-64 bg-gray-900 text-white text-xs rounded-lg p-3 z-50 shadow-xl pointer-events-none">
                <p class="font-semibold text-gray-200 mb-1 border-b border-gray-700 pb-1">Dernière réinitialisation</p>
                <p class="italic text-gray-300 mb-2">"{{ tooltipData.reason }}"</p>
                <p class="text-gray-400 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ tooltipData.created_at }}
                </p>
                <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>