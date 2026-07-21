<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SearchableSelect from '@/Components/Searchableselect.vue'
import { Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    logs:      Object,
    stats:     Object,
    filters:   Object,
    bureaux:   Array,
    candidats: Array,
})

const candidatOptions = computed(() =>
    props.candidats.map(c => ({ id: c.id, label: c.nom }))
)

const filterBureau = (id) => {
    router.get('/admin/audit', { ...props.filters, bureau_id: id || undefined }, { preserveState: true })
}
const filterCandidat = (id) => {
    router.get('/admin/audit', { ...props.filters, option_id: id || undefined }, { preserveState: true })
}
const filterAction = (action) => {
    router.get('/admin/audit', { ...props.filters, action: action || undefined }, { preserveState: true })
}
const filterProcuration = (value) => {
    router.get('/admin/audit', { ...props.filters, procuration: value || undefined }, { preserveState: true })
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Journal d'audit</h1>
        </template>

        <!-- Filtres -->
        <div class="bg-white rounded-xl border border-gray-100 p-4 mb-4 grid grid-cols-1 md:grid-cols-5 gap-3">
            <select @change="filterBureau($event.target.value)"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Tous les bureaux</option>
                <option v-for="b in bureaux" :key="b.id" :value="b.id" :selected="filters.bureau_id == b.id">
                    {{ b.code }} — {{ b.nom }}
                </option>
            </select>

            <SearchableSelect
                :model-value="filters.option_id"
                :options="candidatOptions"
                placeholder="Tous les candidats"
                search-placeholder="Rechercher un candidat..."
                @update:model-value="filterCandidat"
            />

            <select @change="filterAction($event.target.value)"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Toutes les actions</option>
                <option value="+1" :selected="filters.action === '+1'">+1</option>
                <option value="-1" :selected="filters.action === '-1'">-1</option>
            </select>
            <select @change="filterProcuration($event.target.value)"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Tout (procuration ou non)</option>
                <option value="1" :selected="filters.procuration === '1'">Procuration uniquement</option>
                <option value="0" :selected="filters.procuration === '0'">Hors procuration</option>
            </select>
            <Link :href="`/admin/audit`"
                  class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium text-center">
                Réinitialiser
            </Link>
        </div>

        <!-- Statistiques (mises à jour selon les filtres actifs) -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-4">
            <div class="bg-white rounded-xl border border-gray-100 p-4">
                <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Total logs</div>
                <div class="text-xl font-bold text-gray-800">{{ stats.count }}</div>
            </div>
            <!-- <div class="bg-white rounded-xl border border-gray-100 p-4">
                <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Quantité totale</div>
                <div class="text-xl font-bold text-gray-800">{{ stats.total }}</div>
            </div>
            <div class="bg-purple-50 rounded-xl border border-purple-100 p-4">
                <div class="text-xs font-semibold text-purple-600 uppercase mb-1">Procuration</div>
                <div class="text-xl font-bold text-purple-700">{{ stats.procuration }}</div>
            </div>
            <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
                <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Hors procuration</div>
                <div class="text-xl font-bold text-gray-700">{{ stats.hors_procuration }}</div>
            </div> -->
            <div class="bg-green-50 rounded-xl border border-green-100 p-4">
                <div class="text-xs font-semibold text-green-600 uppercase mb-1">+1</div>
                <div class="text-xl font-bold text-green-700">{{ stats.plus }}</div>
            </div>
            <div class="bg-red-50 rounded-xl border border-red-100 p-4">
                <div class="text-xs font-semibold text-red-600 uppercase mb-1">-1</div>
                <div class="text-xl font-bold text-red-700">{{ stats.minus }}</div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date/Heure</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Bureau</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Candidat</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Action</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Quantité</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Utilisateur</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="logs.data.length === 0">
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-400">Aucun log</td>
                    </tr>
                    <tr v-for="log in logs.data" :key="log.id"
                        :class="log.is_procuration ? 'bg-purple-50/50 hover:bg-purple-50' : 'hover:bg-gray-50'">
                        <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ log.created_at }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            <span v-if="log.bureau" class="font-mono text-xs">{{ log.bureau.code }}</span>
                            <span v-if="log.bureau" class="ml-1">{{ log.bureau.nom }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 font-medium">{{ log.option }}</td>
                        <td class="px-4 py-3 text-center">
                            <span :class="log.action === '+1' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                  class="text-xs font-bold px-2.5 py-1 rounded-full">
                                {{ log.action }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-sm font-semibold text-gray-800">
                            {{ log.quantity }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span v-if="log.is_procuration"
                                  class="bg-purple-100 text-purple-700 text-xs font-bold px-2.5 py-1 rounded-full">
                                Procuration
                            </span>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ log.user }}</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="logs.links.length > 3" class="px-4 py-3 border-t border-gray-100">
                <div class="flex justify-center gap-1">
                    <Link v-for="link in logs.links" :key="link.label"
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