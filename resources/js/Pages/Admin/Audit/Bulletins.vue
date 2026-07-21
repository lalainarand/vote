<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
    logs:     Object,
    stats:    Object,
    filters:  Object,
    bureaux:  Array,
    users:    Array,
})

const filterBureau = (id) => {
    router.get('/admin/bulletins/audit', { ...props.filters, bureau_id: id || undefined }, { preserveState: true })
}
const filterUser = (id) => {
    router.get('/admin/bulletins/audit', { ...props.filters, user_id: id || undefined }, { preserveState: true })
}
const filterAction = (action) => {
    router.get('/admin/bulletins/audit', { ...props.filters, action: action || undefined }, { preserveState: true })
}
const filterManuel = (value) => {
    router.get('/admin/bulletins/audit', { ...props.filters, manuel: value || undefined }, { preserveState: true })
}
const filterDateFrom = (value) => {
    router.get('/admin/bulletins/audit', { ...props.filters, date_from: value || undefined }, { preserveState: true })
}
const filterDateTo = (value) => {
    router.get('/admin/bulletins/audit', { ...props.filters, date_to: value || undefined }, { preserveState: true })
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Journal d'audit — Bulletins</h1>
        </template>

        <!-- Filtres -->
        <div class="bg-white rounded-xl border border-gray-100 p-4 mb-4 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <select @change="filterBureau($event.target.value)"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Tous les bureaux</option>
                <option v-for="b in bureaux" :key="b.id" :value="b.id"
                        :selected="filters.bureau_id == b.id">
                    {{ b.code }} — {{ b.nom }}
                </option>
            </select>
            <select @change="filterUser($event.target.value)"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Tous les utilisateurs</option>
                <option v-for="u in users" :key="u.id" :value="u.id"
                        :selected="filters.user_id == u.id">
                    {{ u.name }}
                </option>
            </select>
            <select @change="filterAction($event.target.value)"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Toutes les actions</option>
                <option value="+1" :selected="filters.action === '+1'">+1</option>
                <option value="-1" :selected="filters.action === '-1'">-1</option>
            </select>
            <select @change="filterManuel($event.target.value)"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Tout (unitaire ou groupé)</option>
                <option value="1">Saisie groupée uniquement</option>
                <option value="0">Clic unitaire uniquement</option>
            </select>
            <input type="date"
                   :value="filters.date_from"
                   @change="filterDateFrom($event.target.value)"
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm"
                   placeholder="Du" />
            <input type="date"
                   :value="filters.date_to"
                   @change="filterDateTo($event.target.value)"
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm"
                   placeholder="Au" />
        </div>

        <div class="flex justify-end mb-4">
            <Link href="/admin/bulletins/audit"
                  class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium">
                Réinitialiser les filtres
            </Link>
        </div>

        <!-- Statistiques (mises à jour selon les filtres actifs) -->
        <!-- En-tête des statistiques -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    
    <!-- Carte 1 : Total Électeurs (NET) -->
    <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
        <div class="text-3xl font-bold text-blue-600">
            {{ (stats.net_electeurs || 0).toLocaleString('fr-FR') }}
        </div>
        <div class="text-sm text-gray-500 mt-1">Total électeurs (Net)</div>
        <div class="text-xs text-gray-400 mt-1">
            Résultat net : {{ (stats.plus || 0).toLocaleString('fr-FR') }} (+1) - {{ (stats.minus || 0).toLocaleString('fr-FR') }} (-1)
        </div>
    </div>

    <!-- Carte 2 : Nombre d'opérations -->
    <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
        <div class="text-3xl font-bold text-gray-900">
            {{ (stats.total_logs || 0).toLocaleString('fr-FR') }}
        </div>
        <div class="text-sm text-gray-500 mt-1">Total des opérations</div>
        <div class="text-xs text-gray-400 mt-1">
            Nombre de lignes d'audit enregistrées
        </div>
    </div>

    <!-- Carte 3 : Saisie manuelle vs Unitaire -->
    <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
        <div class="text-3xl font-bold text-amber-600">
            {{ (stats.manuel || 0).toLocaleString('fr-FR') }}
        </div>
        <div class="text-sm text-gray-500 mt-1">Saisie groupée (Manuel)</div>
        <div class="text-xs text-gray-400 mt-1">
            Unitaire : {{ (stats.unitaire || 0).toLocaleString('fr-FR') }}
        </div>
    </div>

    <!-- Carte 4 : Répartition + / - -->
    <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
        <div class="text-sm font-semibold text-gray-700 mb-3">Mouvements</div>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between items-center">
                <span class="flex items-center gap-2 text-green-600">
                    <span class="w-2 h-2 rounded-full bg-green-500"></span> Ajouts (+1)
                </span>
                <span class="font-bold">{{ (stats.plus || 0).toLocaleString('fr-FR') }}</span>
            </div>
            <div class="flex justify-between items-center">
                <span class="flex items-center gap-2 text-red-600">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span> Retraits (-1)
                </span>
                <span class="font-bold">{{ (stats.minus || 0).toLocaleString('fr-FR') }}</span>
            </div>
        </div>
    </div>

</div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date/Heure</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Bureau</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Action</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Quantité</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Type de saisie</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Utilisateur</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="logs.data.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">Aucun log</td>
                    </tr>
                    <tr v-for="log in logs.data" :key="log.id"
                        :class="log.is_manuel ? 'bg-amber-50/50 hover:bg-amber-50' : 'hover:bg-gray-50'">
                        <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ log.created_at }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            <span v-if="log.bureau" class="font-mono text-xs">{{ log.bureau.code }}</span>
                            <span v-if="log.bureau" class="ml-1">{{ log.bureau.nom }}</span>
                        </td>
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
                            <span v-if="log.is_manuel"
                                  class="bg-amber-100 text-amber-700 text-xs font-bold px-2.5 py-1 rounded-full">
                                Groupée
                            </span>
                            <span v-else class="text-xs text-gray-400">Unitaire</span>
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