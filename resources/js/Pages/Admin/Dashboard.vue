<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    stats:            Object,
    national_results: Array,
    status_breakdown: Object,
    alerts:           Array,
})


const statusLabel = {
    pending:   { label: 'En attente',  cls: 'bg-gray-100 text-gray-700' },
    counting:  { label: 'Comptage',    cls: 'bg-blue-100 text-blue-700' },
    pv_entry:  { label: 'Saisie PV',   cls: 'bg-yellow-100 text-yellow-700' },
    pv_admin:  { label: 'PV Admin',    cls: 'bg-purple-100 text-purple-700' },
    validated: { label: 'Validé',      cls: 'bg-green-100 text-green-700' },
    anomaly:   { label: 'Anomalie',    cls: 'bg-red-100 text-red-700' },
}

const statusBarColor = {
    pending:   'bg-gray-400',
    counting:  'bg-blue-500',
    pv_entry:  'bg-yellow-400',
    pv_admin:  'bg-purple-500',
    validated: 'bg-green-500',
    anomaly:   'bg-red-500',
}

const candidates = computed(() =>
    props.national_results.filter(r => r.type === 'candidat')
)
const others = computed(() =>
    props.national_results.filter(r => r.type !== 'candidat')
)

const totalSysteme = computed(() =>
    candidates.value.reduce((s, r) => s + r.system_count, 0)
)
const totalPv = computed(() =>
    candidates.value.reduce((s, r) => s + r.pv_count, 0)
)
const totalEcart = computed(() => totalPv.value - totalSysteme.value)

const totalBureaux = computed(() =>
    Object.values(props.status_breakdown).reduce((s, n) => s + n, 0)
)
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Tableau de bord Administrateur</h1>
        </template>

        <!-- Alertes -->
        <div v-if="alerts.length" class="mb-5 space-y-2">
            <div
                v-for="alert in alerts"
                :key="alert.message"
                class="flex items-center justify-between rounded-xl border px-4 py-2.5 text-sm"
                :class="alert.type === 'error'
                    ? 'bg-red-50 border-red-200 text-red-800'
                    : 'bg-amber-50 border-amber-200 text-amber-800'"
            >
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    {{ alert.message }}
                </div>
                <Link v-if="alert.link" :href="alert.link" class="text-xs font-semibold underline">Voir →</Link>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
                <div class="text-3xl font-bold text-gray-900">{{ stats.total_bureaux }}</div>
                <div class="text-xs text-gray-500 mt-1">Bureaux total</div>
            </div>
            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
                <div class="text-3xl font-bold text-green-600">{{ stats.validated_bureaux }}</div>
                <div class="text-xs text-gray-500 mt-1">Validés</div>
                <div class="mt-2 h-1.5 bg-gray-100 rounded-full">
                    <div class="h-full bg-green-500 rounded-full transition-all"
                         :style="`width:${stats.progression}%`"></div>
                </div>
                <div class="text-xs text-gray-400 mt-1">{{ stats.progression }}% terminés</div>
            </div>
            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
                <div class="text-3xl font-bold text-red-600">{{ stats.anomaly_bureaux }}</div>
                <div class="text-xs text-gray-500 mt-1">Anomalies</div>
            </div>
            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
                <div class="text-3xl font-bold text-purple-600">{{ stats.admin_pv_bureaux }}</div>
                <div class="text-xs text-gray-500 mt-1">Saisies admin</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Résultats comparatifs -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-baseline justify-between">
                    <h2 class="text-sm font-semibold text-gray-800">Résultats globaux</h2>
                    <span class="text-xs text-gray-400">Bureaux validés uniquement</span>
                </div>

                <div v-if="national_results.length === 0"
                     class="p-6 text-sm text-gray-400 text-center">
                    Aucun bureau validé pour l'instant
                </div>

                <template v-else>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 w-2/5">
                                    Candidat
                                </th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500">
                                    Syst.
                                </th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500">
                                    PV papier
                                </th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500">
                                    Écart
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <!-- Candidats -->
                            <tr v-for="result in candidates" :key="result.id"
                                class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-2.5 font-medium text-gray-800 truncate">
                                    {{ result.nom }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono text-gray-400 text-xs">
                                    {{ result.system_count.toLocaleString('fr-FR') }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono font-semibold text-gray-900">
                                    {{ result.pv_count.toLocaleString('fr-FR') }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono text-xs font-semibold"
                                    :class="{
                                        'text-green-600': result.ecart === 0,
                                        'text-amber-600': result.ecart > 0,
                                        'text-red-600':   result.ecart < 0,
                                    }">
                                    {{ result.ecart > 0 ? '+' : '' }}{{ result.ecart.toLocaleString('fr-FR') }}
                                </td>
                            </tr>

                            <!-- Autres (Blanc, Nul…) -->
                            <tr v-for="result in others" :key="result.id"
                                class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-2.5 text-gray-500 italic truncate">{{ result.nom }}</td>
                                <td class="px-3 py-2.5 text-right font-mono text-gray-400 text-xs">
                                    {{ result.system_count.toLocaleString('fr-FR') }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono text-gray-600">
                                    {{ result.pv_count.toLocaleString('fr-FR') }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono text-xs font-semibold"
                                    :class="{
                                        'text-green-600': result.ecart === 0,
                                        'text-amber-600': result.ecart > 0,
                                        'text-red-600':   result.ecart < 0,
                                    }">
                                    {{ result.ecart > 0 ? '+' : '' }}{{ result.ecart.toLocaleString('fr-FR') }}
                                </td>
                            </tr>

                            <!-- Ligne total -->
                            <tr class="bg-gray-50">
                                <td class="px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                    Total
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono text-xs text-gray-400">
                                    {{ totalSysteme.toLocaleString('fr-FR') }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono text-xs font-semibold text-gray-700">
                                    {{ totalPv.toLocaleString('fr-FR') }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono text-xs font-semibold"
                                    :class="{
                                        'text-green-600': totalEcart === 0,
                                        'text-amber-600': totalEcart > 0,
                                        'text-red-600':   totalEcart < 0,
                                    }">
                                    {{ totalEcart > 0 ? '+' : '' }}{{ totalEcart.toLocaleString('fr-FR') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>

                <div class="px-5 py-3 border-t border-gray-50">
                    <Link href="/admin/resultats" class="text-xs text-blue-600 font-medium hover:underline">
                        Voir le détail complet →
                    </Link>
                </div>
            </div>

            <!-- État des bureaux -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-800">État des bureaux</h2>
                </div>

                <div v-if="!Object.keys(status_breakdown).length"
                     class="p-6 text-sm text-gray-400 text-center">
                    Aucun bureau créé
                </div>

                <div v-else class="divide-y divide-gray-50">
                    <div
                        v-for="(count, status) in status_breakdown"
                        :key="status"
                        class="flex items-center gap-3 px-5 py-3"
                    >
                        <span
                            class="text-xs font-medium px-2.5 py-1 rounded-full min-w-[90px] text-center"
                            :class="statusLabel[status]?.cls ?? 'bg-gray-100 text-gray-600'"
                        >
                            {{ statusLabel[status]?.label ?? status }}
                        </span>

                        <!-- Barre proportionnelle -->
                        <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="statusBarColor[status] ?? 'bg-gray-400'"
                                :style="`width:${totalBureaux > 0 ? (count / totalBureaux) * 100 : 0}%`"
                            ></div>
                        </div>

                        <span class="text-sm font-semibold text-gray-700 tabular-nums w-16 text-right">
                            {{ count }} bureau{{ count > 1 ? 'x' : '' }}
                        </span>
                    </div>
                </div>

                <div class="px-5 py-3 border-t border-gray-50">
                    <Link href="/admin/bureaux" class="text-xs text-blue-600 font-medium hover:underline">
                        Gérer les bureaux →
                    </Link>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>