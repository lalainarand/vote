<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    results:                       Array,
    total_candidates_pv:           Number,
    total_candidates_system:       Number,
    total_candidates_procuration:  Number,
    total_procuration:             Number,
    validated_bureaux:             Number,
    total_bureaux:                 Number,
    source_breakdown:              Object,
    status_counts:                 Object,
    scope:                         String,
})

// Vue active : 'pv' ou 'system'
const activeView = ref('pv')

const candidates = computed(() => props.results.filter(r => r.type === 'candidat'))
const others     = computed(() => props.results.filter(r => r.type !== 'candidat'))

const totalActif = computed(() =>
    activeView.value === 'pv'
        ? props.total_candidates_pv
        : props.total_candidates_system
)

const getVotes = (r) =>
    activeView.value === 'pv' ? r.pv_count : r.system_count

// Classement par votes décroissants
const candidatesRanked = computed(() =>
    [...candidates.value].sort((a, b) => getVotes(b) - getVotes(a))
)

// Scope (tous les bureaux vs validés uniquement)
const scopeLabel = computed(() =>
    props.scope === 'validated' ? 'Bureaux validés uniquement' : 'Tous les bureaux (y compris en cours)'
)

const switchScope = (newScope) => {
    router.get('/admin/resultats', { scope: newScope }, { preserveScroll: true, preserveState: true })
}

const statusLabels = {
    pending:   'En attente',
    counting:  'Comptage en cours',
    anomaly:   'Anomalie',
    validated: 'Validé',
}
</script>

        <template>
            <AuthenticatedLayout>
            <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">Résultats globaux</h1>
                <a :href="`/admin/resultats/export?scope=${scope}`"
   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
    Exporter Excel
</a>
            </div>
        </template>

        <!-- Bandeau statut global -->
        <div class="mb-4 flex items-center justify-between rounded-lg border px-4 py-2.5"
             :class="scope === 'validated'
                ? 'bg-green-50 border-green-200'
                : 'bg-amber-50 border-amber-200'">
            <div class="flex items-center gap-2 text-sm">
                <span class="w-2 h-2 rounded-full"
                      :class="scope === 'validated' ? 'bg-green-500' : 'bg-amber-500'"></span>
                <span class="font-medium" :class="scope === 'validated' ? 'text-green-800' : 'text-amber-800'">
                    {{ scopeLabel }}
                </span>
                <span v-if="scope !== 'validated'" class="text-amber-700">
                    — résultats provisoires, susceptibles d'évoluer
                </span>
            </div>

            <div class="flex rounded-lg border border-white/60 overflow-hidden text-xs bg-white">
                <button
                    @click="switchScope('all')"
                    class="px-3 py-1.5 font-medium transition-colors"
                    :class="scope === 'all' ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-50'"
                >
                    Tous ({{ total_bureaux }})
                </button>
                <button
                    @click="switchScope('validated')"
                    class="px-3 py-1.5 font-medium transition-colors border-l border-gray-200"
                    :class="scope === 'validated' ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-50'"
                >
                    Validés ({{ validated_bureaux }})
                </button>
            </div>
        </div>

        <!-- Détail des statuts -->
        <div class="mb-6 flex flex-wrap gap-3 text-xs">
            <span v-for="(count, status) in status_counts" :key="status"
                  class="px-2.5 py-1 rounded-full font-medium"
                  :class="{
                      'bg-gray-100 text-gray-600':   status === 'pending',
                      'bg-blue-100 text-blue-700':   status === 'counting',
                      'bg-red-100 text-red-700':     status === 'anomaly',
                      'bg-green-100 text-green-700': status === 'validated',
                  }">
                {{ statusLabels[status] || status }} : {{ count }}
            </span>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="text-3xl font-bold text-gray-900">
                    {{ validated_bureaux }} / {{ total_bureaux }}
                </div>
                <div class="text-sm text-gray-500 mt-1">Bureaux validés</div>
                <div class="mt-2 h-1.5 bg-gray-100 rounded-full">
                    <div class="h-full bg-green-500 rounded-full transition-all"
                         :style="`width:${total_bureaux > 0 ? (validated_bureaux / total_bureaux) * 100 : 0}%`">
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="text-3xl font-bold text-green-600">
                    {{ total_candidates_pv.toLocaleString('fr-FR') }}
                </div>
                <div class="text-sm text-gray-500 mt-1">Votes validés</div>
                <div class="text-xs text-gray-400 mt-1">
                    Syst. : {{ total_candidates_system.toLocaleString('fr-FR') }}
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="text-3xl font-bold text-purple-600">
                    {{ total_procuration.toLocaleString('fr-FR') }}
                </div>
                <div class="text-sm text-gray-500 mt-1">Votes par procuration</div>
                <div class="text-xs text-gray-400 mt-1">
                    Candidats : {{ total_candidates_procuration.toLocaleString('fr-FR') }}
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="text-sm text-gray-500 mb-2">Sources (bureaux validés)</div>
                <div class="text-xs space-y-1.5">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Opérateur</span>
                        <span class="font-semibold text-gray-800">
                            {{ source_breakdown.counting || 0 }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Admin (PV)</span>
                        <span class="font-semibold text-gray-800">
                            {{ source_breakdown.manual_pv || 0 }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Admin (override)</span>
                        <span class="font-semibold text-gray-800">
                            {{ source_breakdown.admin_override || 0 }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toggle PV / Système -->
        <div class="flex items-center gap-2 mb-4">
            <span class="text-sm text-gray-500">Affichage :</span>
            <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm">
                <button
                    @click="activeView = 'pv'"
                    class="px-4 py-1.5 font-medium transition-colors"
                    :class="activeView === 'pv'
                        ? 'bg-gray-800 text-white'
                        : 'bg-white text-gray-600 hover:bg-gray-50'"
                >
                    PV papier
                </button>
                <button
                    @click="activeView = 'system'"
                    class="px-4 py-1.5 font-medium transition-colors border-l border-gray-200"
                    :class="activeView === 'system'
                        ? 'bg-gray-800 text-white'
                        : 'bg-white text-gray-600 hover:bg-gray-50'"
                >
                    Compteur système
                </button>
            </div>
        </div>

        <!-- Résultats candidats -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">
                    Résultats par candidat
                </h2>
            </div>

            <!-- Tableau comparatif complet -->
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 w-8">#</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500">Candidat</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">Syst.</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">Procuration</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">PV papier</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">Écart</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 w-16">%</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 w-40"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="(r, idx) in candidatesRanked" :key="r.id"
                        class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 text-xs text-gray-400 font-mono">{{ idx + 1 }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-800">
                            <span v-if="r.numero" class="text-gray-500 font-mono mr-1.5">N°{{ r.numero }}</span>- {{ r.nom }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-mono text-gray-400">
                            {{ r.system_count.toLocaleString('fr-FR') }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-mono"
                            :class="r.procuration > 0 ? 'text-purple-600 font-semibold' : 'text-gray-300'">
                            {{ r.procuration > 0 ? r.procuration.toLocaleString('fr-FR') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-mono font-semibold text-gray-900">
                            {{ r.pv_count.toLocaleString('fr-FR') }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-mono font-semibold"
                            :class="{
                                'text-green-600': r.ecart === 0,
                                'text-amber-600': r.ecart > 0,
                                'text-red-600':   r.ecart < 0,
                            }">
                            {{ r.ecart > 0 ? '+' : '' }}{{ r.ecart.toLocaleString('fr-FR') }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-semibold text-blue-600">
                            <span v-if="totalActif > 0">
                                {{ ((getVotes(r) / totalActif) * 100).toFixed(1) }}%
                            </span>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-blue-500 rounded-full transition-all"
                                     :style="`width:${totalActif > 0 ? (getVotes(r) / totalActif) * 100 : 0}%`">
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Blanc / Nul -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">
                    Bulletins blancs et nuls
                </h2>
            </div>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500">Type</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">Syst.</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">Procuration</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">PV papier</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">Écart</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in others" :key="r.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 text-sm font-medium text-gray-700">{{ r.nom }}</td>
                        <td class="px-4 py-3 text-right text-sm font-mono text-gray-400">
                            {{ r.system_count.toLocaleString('fr-FR') }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-mono"
                            :class="r.procuration > 0 ? 'text-purple-600 font-semibold' : 'text-gray-300'">
                            {{ r.procuration > 0 ? r.procuration.toLocaleString('fr-FR') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-mono font-semibold text-gray-900">
                            {{ r.pv_count.toLocaleString('fr-FR') }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-mono font-semibold"
                            :class="{
                                'text-green-600': r.ecart === 0,
                                'text-amber-600': r.ecart > 0,
                                'text-red-600':   r.ecart < 0,
                            }">
                            {{ r.ecart > 0 ? '+' : '' }}{{ r.ecart.toLocaleString('fr-FR') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </AuthenticatedLayout>
</template>