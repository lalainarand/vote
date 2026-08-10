<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    bureau:      Object,
    counters:    Array,
    recent_logs: Array,
    statistics:  Object,
})

const statusLabel = {
    pending:   { label: 'En attente',  cls: 'bg-gray-100 text-gray-600' },
    counting:  { label: 'Comptage',    cls: 'bg-blue-100 text-blue-700' },
    pv_entry:  { label: 'Saisie PV',   cls: 'bg-yellow-100 text-yellow-700' },
    pv_admin:  { label: 'PV Admin',    cls: 'bg-purple-100 text-purple-700' },
    validated: { label: 'Validé',      cls: 'bg-green-100 text-green-700' },
    anomaly:   { label: 'Anomalie',    cls: 'bg-red-100 text-red-700' },
}

const candidates = computed(() => props.counters.filter(c => c.type === 'candidat'))
const totalSystem = computed(() => candidates.value.reduce((s, c) => s + c.system_count, 0))
const totalPv = computed(() => candidates.value.reduce((s, c) => s + (c.pv_count || 0), 0))

// Validation admin : confirmation de second niveau, une fois le bureau déjà
// validé par l'opérateur. N'affecte aucun chiffre, sert uniquement à l'affichage.
const adminValidateBureau = () => {
    if (confirm('Confirmer la validation admin de ce bureau ?')) {
        router.post(`/admin/bureaux/${props.bureau.id}/admin-validate`, {}, { preserveScroll: true })
    }
}

// Marquer en anomalie : exclut ce bureau des résultats généraux et de l'export Excel
// (ses voix restent visibles ici et dans les stats dashboard/résultats, mais ne sont
// plus comptées dans les totaux).
const markAnomaly = () => {
    if (confirm('Marquer ce bureau en anomalie ? Ses résultats ne seront plus comptés dans les résultats généraux ni dans l\'export Excel.')) {
        router.patch(`/admin/bureaux/${props.bureau.id}/lock`, {}, { preserveScroll: true })
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-base font-semibold text-gray-800">{{ bureau.nom }}</h1>
                    <p class="text-sm text-gray-500 mt-1">Code : {{ bureau.code }}</p>
                </div>
                <Link href="/admin/bureaux"
                      class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">
                    ← Retour
                </Link>
            </div>
        </template>

        <!-- Info bureau -->
        <div class="bg-white rounded-xl border border-gray-100 p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <span :class="statusLabel[bureau.status]?.cls ?? 'bg-gray-100 text-gray-600'"
                              class="text-sm font-medium px-3 py-1.5 rounded-full">
                            {{ statusLabel[bureau.status]?.label ?? bureau.status }}
                        </span>
                        <span class="text-sm text-gray-500">
                            Opérateur : {{ bureau.users?.[0]?.name ?? 'Non assigné' }}
                        </span>
                    </div>
                </div>

                <!-- 2 actions admin possibles : Valider (confirmation) ou Marquer anomalie (exclusion) -->
                <div class="flex items-center gap-2">
                    <span v-if="bureau.status === 'anomaly'"
                          class="inline-flex items-center gap-2 bg-red-100 text-red-700 px-4 py-2 rounded-lg text-sm font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        Exclu des résultats
                    </span>
                    <span v-else-if="bureau.admin_validated_at"
                          class="inline-flex items-center gap-2 bg-green-100 text-green-700 px-4 py-2 rounded-lg text-sm font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        Validé par {{ bureau.admin_validator?.name }}
                    </span>
                    <template v-else>
                        <button @click="markAnomaly"
                                class="bg-red-50 hover:bg-red-100 text-red-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            Marquer anomalie
                        </button>
                        <button v-if="bureau.status === 'validated'"
                                @click="adminValidateBureau"
                                class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                            Valider (admin)
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- Statistiques -->
        <div v-if="statistics" class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-100 p-5 text-center">
                <div class="text-3xl font-bold text-gray-900">{{ statistics.registered_voters }}</div>
                <div class="text-sm text-gray-500 mt-1">Inscrits</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-5 text-center">
                <div class="text-3xl font-bold text-gray-900">{{ statistics.voters }}</div>
                <div class="text-sm text-gray-500 mt-1">Votants</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-5 text-center">
                <div class="text-3xl font-bold text-gray-900">{{ statistics.ballots_found }}</div>
                <div class="text-sm text-gray-500 mt-1">Bulletins trouvés</div>
                <div class="text-xs text-gray-400 mt-1">Source : {{ statistics.pv_source }}</div>
            </div>
        </div>

        <!-- Résultats avec écarts -->
        <div class="bg-white rounded-xl border border-gray-100 p-6 mb-6">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 uppercase">Résultats par candidat</h2>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Candidat</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Compteur système</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">PV papier</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Écart</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Source</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="c in counters" :key="c.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ c.nom }}</td>
                        <td class="px-4 py-3 text-center font-mono text-gray-600">{{ c.system_count }}</td>
                        <td class="px-4 py-3 text-center font-mono text-gray-900">{{ c.pv_count ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span v-if="c.ecart !== null"
                                  :class="c.ecart === 0 ? 'text-green-600' : 'text-red-600'"
                                  class="font-mono font-bold">
                                {{ c.ecart > 0 ? '+' : '' }}{{ c.ecart }}
                            </span>
                            <span v-else class="text-gray-400">—</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span v-if="c.source"
                                  :class="{
                                      'bg-green-100 text-green-700': c.source === 'counting',
                                      'bg-purple-100 text-purple-700': c.source === 'manual_pv',
                                      'bg-amber-100 text-amber-700': c.source === 'admin_override',
                                  }"
                                  class="text-xs font-medium px-2 py-1 rounded-full">
                                {{ c.source === 'counting' ? 'Opérateur' : c.source === 'manual_pv' ? 'Admin PV' : 'Admin Override' }}
                            </span>
                            <span v-else class="text-gray-400 text-xs">—</span>
                        </td>
                    </tr>
                </tbody>
                <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                    <tr>
                        <td class="px-4 py-3 text-sm font-bold text-gray-900">Total</td>
                        <td class="px-4 py-3 text-center font-mono font-bold text-gray-900">{{ totalSystem }}</td>
                        <td class="px-4 py-3 text-center font-mono font-bold text-gray-900">{{ totalPv }}</td>
                        <td class="px-4 py-3 text-center">
                            <span :class="(totalPv - totalSystem) === 0 ? 'text-green-600' : 'text-red-600'"
                                  class="font-mono font-bold">
                                {{ (totalPv - totalSystem) > 0 ? '+' : '' }}{{ totalPv - totalSystem }}
                            </span>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Historique vote_logs -->
        <div class="bg-white rounded-xl border border-gray-100 p-6">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 uppercase">
                Historique récent ({{ recent_logs.length }} dernières actions)
            </h2>
            <div class="space-y-2 max-h-96 overflow-y-auto">
                <div v-for="log in recent_logs" :key="log.id"
                     class="flex items-center gap-3 py-2 border-b border-gray-100 last:border-0">
                    <span class="text-xs font-mono text-gray-500 w-16">{{ log.created_at }}</span>
                    <span :class="log.action === '+1' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                          class="text-xs font-bold px-2 py-0.5 rounded-full w-8 text-center">
                        {{ log.action }}
                    </span>
                    <span class="text-sm text-gray-900 flex-1">{{ log.option }}</span>
                    <span class="text-xs text-gray-500">{{ log.user }}</span>
                </div>
                <div v-if="recent_logs.length === 0" class="text-center py-8 text-sm text-gray-400">
                    Aucune action enregistrée
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>