<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import CandidateResultsRanking from '@/Components/CandidateResultsRanking.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    results:                       Array,
    total_candidates_pv:           Number,
    total_candidates_system:       Number,
    total_candidates_procuration:  Number,

    // Chiffres réels et officiels (bulletins dépouillés)
    total_electeurs:                     { type: Number, default: 0 },
    total_electeurs_individuels:         { type: Number, default: 0 },
    total_electeurs_procuration:         { type: Number, default: 0 },
    total_bulletins_procuration_count:   { type: Number, default: 0 },
    total_voix_individuelles:      { type: Number, default: 0 },
    total_voix_procuration:        { type: Number, default: 0 },

    validated_bureaux:             Number,
    total_bureaux:                 Number,
    source_breakdown:              Object,
    status_counts:                 Object,
    scope:                         String,
    seats:                         { type: Number, default: 9 },

    // Bureaux marqués anomalie : exclus des totaux ci-dessus, affichés à part
    anomaly_bureaux_count:         { type: Number, default: 0 },
    anomaly_bureaux_votes:         { type: Number, default: 0 },
})

const activeView = ref('system')

const candidates = computed(() => props.results.filter(r => r.type === 'candidat'))
const others     = computed(() => props.results.filter(r => r.type !== 'candidat'))

const totalActif = computed(() =>
    activeView.value === 'pv' ? props.total_candidates_pv : props.total_candidates_system
)

const getVotes = (r) => activeView.value === 'pv' ? r.pv_count : r.system_count

const candidatesRanked = computed(() =>
    [...candidates.value].sort((a, b) => getVotes(b) - getVotes(a))
)

const rankingItems = computed(() =>
    candidatesRanked.value.map(r => ({
        id: r.id,
        nom: r.nom,
        numero: r.numero,
        photo: r.photo,
        value: getVotes(r),
        system_count: r.system_count,
        procuration: r.procuration,
        pv_count: r.pv_count,
        ecart: r.ecart,
    }))
)

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
                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    Exporter Excel
                </a>
            </div>
        </template>

        <!-- Bandeau statut global -->
<div class="mb-4 flex items-center justify-between rounded-lg border px-4 py-2.5"
     :class="scope === 'validated' ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200'">
    <div class="flex items-center gap-2 text-sm">
        <span class="w-2 h-2 rounded-full" :class="scope === 'validated' ? 'bg-green-500' : 'bg-amber-500'"></span>
        <span class="font-medium" :class="scope === 'validated' ? 'text-green-800' : 'text-amber-800'">
            {{ scopeLabel }}
        </span>
        <span v-if="scope !== 'validated'" class="text-amber-700">
            — résultats provisoires, susceptibles d'évoluer
        </span>
    </div>

    <div class="flex rounded-lg border border-white/60 overflow-hidden text-xs bg-white">
        <button @click="switchScope('all')"
                class="px-3 py-1.5 font-medium transition-colors"
                :class="scope === 'all' ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-50'">
            Tous ({{ total_bureaux }})
        </button>
        <button @click="switchScope('validated')"
                class="px-3 py-1.5 font-medium transition-colors border-l border-gray-200"
                :class="scope === 'validated' ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-50'">
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

<!-- Transparence : bureaux exclus des résultats car marqués anomalie -->
<div v-if="anomaly_bureaux_count > 0"
     class="mb-6 flex items-center gap-2 bg-red-50 border border-red-200 rounded-lg px-4 py-2.5 text-sm text-red-800">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
    </svg>
    <span>
        <strong>{{ anomaly_bureaux_count }}</strong> bureau{{ anomaly_bureaux_count > 1 ? 'x' : '' }} en anomalie exclu{{ anomaly_bureaux_count > 1 ? 's' : '' }}
        des résultats ci-dessous — <strong>{{ anomaly_bureaux_votes.toLocaleString('fr-FR') }}</strong> voix non comptabilisées.
    </span>
</div>

<!--  Stats Globales (Chiffres officiels) -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    <!-- Carte 1 : Bureaux -->
    <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
        <div class="text-3xl font-bold text-gray-900">
            {{ validated_bureaux }} <span class="text-lg text-gray-400 font-normal">/ {{ total_bureaux }}</span>
        </div>
        <div class="text-sm text-gray-500 mt-1">Bureaux validés</div>
        <div class="mt-3 h-1.5 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full bg-green-500 rounded-full transition-all duration-500"
                 :style="`width:${total_bureaux > 0 ? (validated_bureaux / total_bureaux) * 100 : 0}%`">
            </div>
        </div>
    </div>

    <!-- Carte 2 : Bulletins à vote individuel -->
    <div class="bg-white rounded-xl border border-sky-200 bg-sky-50/30 p-5 shadow-sm">
        <div class="text-3xl font-bold text-sky-700">
            {{ (total_electeurs_individuels || 0).toLocaleString('fr-FR') }}
        </div>
        <div class="text-sm font-semibold text-sky-900 mt-1">Bulletins individuels</div>
    </div>

    <!-- Carte 3 : Votants par procuration (≠ nombre de bulletins : un bulletin peut représenter plusieurs votants) -->
    <div class="bg-white rounded-xl border border-amber-200 bg-amber-50/30 p-5 shadow-sm">
        <div class="text-3xl font-bold text-amber-700">
            {{ (total_electeurs_procuration || 0).toLocaleString('fr-FR') }}
        </div>
        <div class="text-sm font-semibold text-amber-900 mt-1">Votants par procuration</div>
        <div class="text-[11px] text-amber-700/70 mt-2">
            via {{ total_bulletins_procuration_count }} bulletin{{ total_bulletins_procuration_count > 1 ? 's' : '' }} de procuration
        </div>
    </div>

    <!-- Carte 4 : Total des votants (somme individuel + procuration) -->
    <div class="bg-white rounded-xl border border-blue-200 bg-blue-50/30 p-5 shadow-sm">
        <div class="text-3xl font-bold text-blue-700">
            {{ (total_electeurs || 0).toLocaleString('fr-FR') }}
        </div>
        <div class="text-sm font-semibold text-blue-900 mt-1">Total des votants</div>
        <div class="text-[11px] text-blue-600/70 mt-2">
            = {{ (total_electeurs_individuels || 0).toLocaleString('fr-FR') }} ind.
            + {{ (total_electeurs_procuration || 0).toLocaleString('fr-FR') }} proc.
        </div>
    </div>
</div>

        <!-- Toggle PV / Système -->
        <div class="flex items-center gap-2 mb-4">
            <span class="text-sm text-gray-500">Affichage :</span>
            <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm">
                <button @click="activeView = 'pv'"
                        class="px-4 py-1.5 font-medium transition-colors"
                        :class="activeView === 'pv' ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'">
                    PV papier
                </button>
                <button @click="activeView = 'system'"
                        class="px-4 py-1.5 font-medium transition-colors border-l border-gray-200"
                        :class="activeView === 'system' ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'">
                    Compteur système
                </button>
            </div>
        </div>

        <!-- Classement des candidats (visuel unique : rang + photo + barre + chiffres) -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-100 flex items-baseline justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Résultats par candidat</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Les {{ seats }} premiers sont élus ({{ seats }} sièges à pourvoir)</p>
                </div>
                <span class="text-xs text-gray-400">{{ activeView === 'pv' ? 'PV papier' : 'Compteur système' }}</span>
            </div>

            <CandidateResultsRanking :items="rankingItems" :total-actif="totalActif" :top-count="seats" unit="voix" />
        </div>

        <!-- Blanc / Nul -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Bulletins blancs et nuls</h2>
            </div>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500">Type</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">Syst.</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">PV papier</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">Écart</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in others" :key="r.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 text-sm font-medium text-gray-700">{{ r.nom }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="text-sm font-mono text-gray-400">{{ (r.system_count || 0).toLocaleString('fr-FR') }}</div>
                            <div v-if="r.procuration > 0" class="text-[11px] font-mono text-purple-600 font-semibold mt-0.5">
                                dont {{ (r.procuration || 0).toLocaleString('fr-FR') }} proc.
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-mono font-semibold text-gray-900">
                            {{ (r.pv_count || 0).toLocaleString('fr-FR') }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-mono font-semibold"
                            :class="{
                                'text-green-600': r.ecart === 0,
                                'text-amber-600': r.ecart > 0,
                                'text-red-600':   r.ecart < 0,
                            }">
                            {{ r.ecart > 0 ? '+' : '' }}{{ (r.ecart || 0).toLocaleString('fr-FR') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </AuthenticatedLayout>
</template>