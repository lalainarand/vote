<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    stats:            Object,
    national_results: Array,
    status_breakdown: Object,
    alerts:           Array,
})

const statusLabel = {
    pending:   { label: 'En attente',  cls: 'bg-gray-100 text-gray-600' },
    counting:  { label: 'Comptage',    cls: 'bg-blue-100 text-blue-700' },
    pv_entry:  { label: 'Saisie PV',   cls: 'bg-yellow-100 text-yellow-700' },
    pv_admin:  { label: 'PV Admin',    cls: 'bg-purple-100 text-purple-700' },
    validated: { label: 'Validé',      cls: 'bg-green-100 text-green-700' },
    anomaly:   { label: 'Anomalie',    cls: 'bg-red-100 text-red-700' },
}

const totalGlobal = props.national_results
    .filter(r => r.type === 'candidate')
    .reduce((sum, r) => sum + r.total, 0)
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Tableau de bord</h1>
        </template>

        <!-- Alertes -->
        <div v-if="alerts.length" class="mb-6 space-y-2">
            <div v-for="alert in alerts" :key="alert.message"
                 :class="alert.type === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-amber-50 border-amber-200 text-amber-800'"
                 class="flex items-center justify-between border rounded-xl px-4 py-3 text-sm">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    {{ alert.message }}
                </div>
                <Link v-if="alert.link" :href="alert.link" class="font-semibold underline text-xs">Voir →</Link>
            </div>
        </div>

        <!-- Stats cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
                <div class="text-3xl font-bold text-gray-900">{{ stats.total_bureaux }}</div>
                <div class="text-sm text-gray-500 mt-1">Bureaux total</div>
            </div>
            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
                <div class="text-3xl font-bold text-green-600">{{ stats.validated_bureaux }}</div>
                <div class="text-sm text-gray-500 mt-1">Validés</div>
                <div class="mt-2 h-1.5 bg-gray-100 rounded-full">
                    <div class="h-full bg-green-500 rounded-full transition-all" :style="`width:${stats.progression}%`"></div>
                </div>
                <div class="text-xs text-gray-400 mt-1">{{ stats.progression }}% terminés</div>
            </div>
            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
                <div class="text-3xl font-bold text-red-600">{{ stats.anomaly_bureaux }}</div>
                <div class="text-sm text-gray-500 mt-1">Anomalies</div>
            </div>
            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
                <div class="text-3xl font-bold text-purple-600">{{ stats.admin_pv_bureaux }}</div>
                <div class="text-sm text-gray-500 mt-1">Saisies admin</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Résultats nationaux -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-800">Résultats globaux (bureaux validés)</h2>
                </div>
                <div class="p-6 space-y-4">
                    <div v-if="national_results.length === 0" class="text-sm text-gray-400 text-center py-4">
                        Aucun bureau validé pour l'instant
                    </div>
                    <div v-for="result in national_results" :key="result.id">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm font-medium text-gray-700">{{ result.nom }}</span>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-gray-900">{{ result.total.toLocaleString('fr-FR') }}</span>
                                <span v-if="result.type === 'candidate' && totalGlobal > 0"
                                      class="text-xs text-gray-400">
                                    {{ ((result.total / totalGlobal) * 100).toFixed(1) }}%
                                </span>
                            </div>
                        </div>
                        <div v-if="result.type === 'candidate' && totalGlobal > 0"
                             class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-600 rounded-full transition-all"
                                 :style="`width:${(result.total / totalGlobal) * 100}%`"></div>
                        </div>
                    </div>
                </div>
                <div class="px-6 pb-4">
                    <Link href="/admin/resultats" class="text-sm text-blue-600 font-medium hover:underline">
                        Voir le détail complet →
                    </Link>
                </div>
            </div>

            <!-- Répartition des statuts -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-800">État des bureaux</h2>
                </div>
                <div class="p-6 space-y-3">
                    <div v-for="(count, status) in status_breakdown" :key="status"
                         class="flex items-center justify-between">
                        <span :class="statusLabel[status]?.cls ?? 'bg-gray-100 text-gray-600'"
                              class="text-xs font-medium px-2.5 py-1 rounded-full">
                            {{ statusLabel[status]?.label ?? status }}
                        </span>
                        <span class="text-sm font-semibold text-gray-700">{{ count }} bureau{{ count > 1 ? 'x' : '' }}</span>
                    </div>
                    <div v-if="!Object.keys(status_breakdown).length" class="text-sm text-gray-400 text-center py-4">
                        Aucun bureau créé
                    </div>
                </div>
                <div class="px-6 pb-4">
                    <Link href="/admin/bureaux" class="text-sm text-blue-600 font-medium hover:underline">
                        Gérer les bureaux →
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>