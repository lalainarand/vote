<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    bureau:     Object,
    counters:   Array,
    pv_values:  Object,
    statistics: Object,
})

// ── Formulaire ──────────────────────────────────────────────────────
const form = useForm({
    pv_data: props.counters.map(c => ({
        vote_option_id: c.id,
        count: props.pv_values[c.id] ?? c.system_count,
    })),
    registered_voters: props.statistics?.registered_voters ?? 0,
    voters: props.statistics?.voters ?? 0,
    ballots_found: props.statistics?.ballots_found ?? 0,
})

// Helper : cast sûr en entier (évite les NaN sur champ vidé)
const toInt = (v) => {
    const n = parseInt(v, 10)
    return Number.isNaN(n) ? 0 : n
}

const totalPv = computed(() =>
    form.pv_data.reduce((s, p) => s + toInt(p.count), 0)
)

// Styles par niveau de gravité
const levelStyles = {
    ok: {
        box: 'bg-green-50 border-green-200',
        icon: 'text-green-600',
        text: 'text-green-800',
        symbol: '✓',
    },
    error: {
        box: 'bg-red-50 border-red-200',
        icon: 'text-red-600',
        text: 'text-red-800',
        symbol: '✗',
    },
    warning: {
        box: 'bg-amber-50 border-amber-200',
        icon: 'text-amber-600',
        text: 'text-amber-800',
        symbol: '⚠',
    },
    info: {
        box: 'bg-blue-50 border-blue-200',
        icon: 'text-blue-600',
        text: 'text-blue-800',
        symbol: 'ℹ',
    },
}

// ── Détail des contrôles (source unique de vérité) ───────────────────
const controlDetails = computed(() => {
    const details = []

    // ── C1 : Cohérence candidat par candidat ──────────────────────
    const mismatches = form.pv_data
        .map((p, idx) => ({ p, counter: props.counters[idx] }))
        .filter(({ p, counter }) => toInt(p.count) !== counter.system_count)

    if (mismatches.length === 0) {
        details.push({
            key: 'c1',
            level: 'ok',
            title: 'C1 — Cohérence candidat',
            message: 'Tous les compteurs système correspondent au PV papier.',
        })
    } else {
        const noms = mismatches.map(({ counter }) => counter.nom).join(', ')
        details.push({
            key: 'c1',
            level: 'error',
            title: 'C1 — Cohérence candidat',
            message: `Écart détecté pour ${mismatches.length} candidat(s) : ${noms}. Vérifiez la saisie ou le comptage.`,
        })
    }

    // ── C2 : Total bulletins = Bulletins trouvés ──────────────────
    const ballotsFound = toInt(form.ballots_found)
    const ecartC2 = totalPv.value - ballotsFound
    if (ecartC2 === 0) {
        details.push({
            key: 'c2',
            level: 'ok',
            title: 'C2 — Total des bulletins',
            message: `La somme des votes (${totalPv.value}) correspond aux bulletins trouvés.`,
        })
    } else if (ecartC2 > 0) {
        details.push({
            key: 'c2',
            level: 'warning',
            title: 'C2 — Total des bulletins',
            message: `Le total des votes saisis dépasse de ${ecartC2} le nombre de bulletins trouvés (${totalPv.value} / ${ballotsFound}). Vérifiez le décompte.`,
        })
    } else {
        details.push({
            key: 'c2',
            level: 'warning',
            title: 'C2 — Total des bulletins',
            message: `Il manque ${Math.abs(ecartC2)} vote(s) par rapport aux bulletins trouvés (${totalPv.value} / ${ballotsFound}). Vérifiez le décompte.`,
        })
    }

    // ── C3 : Bulletins ≤ Votants ───────────────────────────────────
    const voters = toInt(form.voters)
    const ecartC3 = ballotsFound - voters
    if (ecartC3 <= 0) {
        details.push({
            key: 'c3',
            level: 'ok',
            title: 'C3 — Bulletins ≤ Votants',
            message: 'Le nombre de bulletins trouvés est cohérent avec le nombre de votants.',
        })
    } else {
        details.push({
            key: 'c3',
            level: 'error',
            title: 'C3 — Bulletins ≤ Votants',
            message: `Anomalie critique : ${ecartC3} bulletin(s) de plus que de votants enregistrés. Situation physiquement impossible, à corriger avant validation.`,
        })
    }

    // ── C4 : Votants ≤ Inscrits ──────────────────────────────────────
    const registeredVoters = toInt(form.registered_voters)
    const ecartC4 = voters - registeredVoters
    if (ecartC4 <= 0) {
        details.push({
            key: 'c4',
            level: 'ok',
            title: 'C4 — Votants ≤ Inscrits',
            message: "Le nombre de votants est cohérent avec le nombre d'inscrits.",
        })
    } else {
        details.push({
            key: 'c4',
            level: 'error',
            title: 'C4 — Votants ≤ Inscrits',
            message: `Anomalie critique : ${ecartC4} votant(s) de plus que d'inscrits. Situation physiquement impossible, à corriger avant validation.`,
        })
    }

    return details
})

// Dérivé de controlDetails : plus de duplication de règles
const hasCriticalError = computed(() =>
    controlDetails.value.some(d => d.level === 'error')
)
const hasWarning = computed(() =>
    controlDetails.value.some(d => d.level === 'warning')
)
const allPass = computed(() =>
    controlDetails.value.every(d => d.level === 'ok')
)

const submit = () => {
    if (hasCriticalError.value) return // garde-fou supplémentaire côté client
    form.post('/operator/pv')
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Vérification PV — {{ bureau.nom }}</h1>
        </template>

        <form @submit.prevent="submit" class="max-w-4xl mx-auto space-y-6">
            <!-- Tableau comparatif -->
            <div class="bg-white rounded-xl border border-gray-100 p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-4 uppercase">Comparaison Compteur / PV papier</h2>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Candidat</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-gray-600">Compteur système</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-gray-600">PV papier</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-gray-600">Écart</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-gray-600">C1</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="(counter, idx) in counters" :key="counter.id">
                            <td class="px-3 py-2 text-sm text-gray-900">
                                {{ counter.nom }}
                                <div v-if="counter.procuration > 0" class="text-xs text-purple-600 font-normal mt-0.5">
                                    dont {{ counter.procuration }} par procuration
                                </div>
                            </td>
                            <td class="px-3 py-2 text-center font-mono text-gray-600">{{ counter.system_count }}</td>
                            <td class="px-3 py-2 text-center">
                                <input v-model.number="form.pv_data[idx].count" type="number" min="0"
                                       class="w-24 px-2 py-1 border border-gray-300 rounded text-center" />
                            </td>
                            <td class="px-3 py-2 text-center font-mono"
                                :class="(toInt(form.pv_data[idx].count) - counter.system_count) === 0 ? 'text-green-600' : 'text-red-600'">
                                {{ toInt(form.pv_data[idx].count) - counter.system_count }}
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span :class="toInt(form.pv_data[idx].count) === counter.system_count ? 'text-green-600' : 'text-red-600'">
                                    {{ toInt(form.pv_data[idx].count) === counter.system_count ? '✓' : '✗' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Statistiques -->
            <div class="bg-white rounded-xl border border-gray-100 p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-4 uppercase">Statistiques du bureau</h2>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Inscrits</label>
                        <input v-model.number="form.registered_voters" type="number" min="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Votants</label>
                        <input v-model.number="form.voters" type="number" min="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bulletins trouvés</label>
                        <input v-model.number="form.ballots_found" type="number" min="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
                    </div>
                </div>
            </div>

            <!-- Contrôles -->
            <div class="bg-gray-50 rounded-xl p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-3 uppercase">Contrôles automatiques</h2>
                <div class="space-y-2.5">
                    <div
                        v-for="detail in controlDetails"
                        :key="detail.key"
                        class="flex items-start gap-3 rounded-lg border px-4 py-3"
                        :class="levelStyles[detail.level].box"
                    >
                        <span class="text-lg font-bold leading-none mt-0.5" :class="levelStyles[detail.level].icon">
                            {{ levelStyles[detail.level].symbol }}
                        </span>
                        <div>
                            <div class="text-sm font-semibold" :class="levelStyles[detail.level].text">
                                {{ detail.title }}
                            </div>
                            <div class="text-sm mt-0.5" :class="levelStyles[detail.level].text">
                                {{ detail.message }}
                            </div>
                        </div>
                    </div>
                </div>

                <p v-if="hasCriticalError" class="mt-3 text-sm font-medium text-red-700">
                    Corrigez les anomalies critiques (C3/C4) avant de pouvoir enregistrer ce PV.
                </p>
                <p v-else-if="hasWarning" class="mt-3 text-sm font-medium text-amber-700">
                    Des écarts non bloquants subsistent (C2). Vérifiez avant validation.
                </p>
            </div>

            <!-- Actions -->
            <div class="flex gap-3">
                <button type="submit"
                        :disabled="form.processing || hasCriticalError"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                    Enregistrer le PV
                </button>
                <Link :href="`/operator/dashboard`"
                      class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg font-medium">
                    Annuler
                </Link>
            </div>
        </form>
    </AuthenticatedLayout>
</template>