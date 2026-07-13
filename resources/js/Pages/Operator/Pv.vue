<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    bureau: Object,
    counters: Array,
    pv_values: Object,
    statistics: Object,
    system_ballots_count: Number,
})

// ── Formulaire ──────────────────────────────────────────────────────
const form = useForm({
    pv_data: props.counters.map(c => ({
        vote_option_id: c.id,
        count: props.pv_values[c.id] ?? c.system_count,
    })),
    // Pré-remplir avec le comptage système pour faciliter la saisie
    ballots_found: props.statistics?.ballots_found ?? props.system_ballots_count, 
})

const toInt = (v) => {
    const n = parseInt(v, 10)
    return Number.isNaN(n) ? 0 : n
}

// Map rapide des types pour les calculs
const countersMap = computed(() => {
    const map = {}
    props.counters.forEach(c => { map[c.id] = c.type })
    return map
})

// Totaux calculés en temps réel
const totalVoixCandidats = computed(() =>
    form.pv_data.reduce((sum, p) => {
        return countersMap.value[p.vote_option_id] === 'candidat' ? sum + toInt(p.count) : sum
    }, 0)
)

const totalBlancsNuls = computed(() =>
    form.pv_data.reduce((sum, p) => {
        const type = countersMap.value[p.vote_option_id]
        return (type === 'blanc' || type === 'nul') ? sum + toInt(p.count) : sum
    }, 0)
)

const levelStyles = {
    ok: { box: 'bg-green-50 border-green-200', icon: 'text-green-600', text: 'text-green-800', symbol: '✓' },
    error: { box: 'bg-red-50 border-red-200', icon: 'text-red-600', text: 'text-red-800', symbol: '✗' },
    warning: { box: 'bg-amber-50 border-amber-200', icon: 'text-amber-600', text: 'text-amber-800', symbol: '⚠' },
}

// ── Détail des contrôles (Adapté au scrutin plurinominal 1-9 voix) ──
const controlDetails = computed(() => {
    const details = []
    const ballotsFound = toInt(form.ballots_found)
    const systemBallots = props.system_ballots_count

    // C1 : Cohérence candidat par candidat
    const mismatches = form.pv_data
        .map((p, idx) => ({ p, counter: props.counters[idx] }))
        .filter(({ p, counter }) => toInt(p.count) !== counter.system_count)

    if (mismatches.length === 0) {
        details.push({ key: 'c1', level: 'ok', title: 'C1 — Cohérence par option', message: 'Les saisies correspondent aux compteurs système pour chaque option.' })
    } else {
        const noms = mismatches.map(({ counter }) => counter.nom).join(', ')
        details.push({ key: 'c1', level: 'warning', title: 'C1 — Cohérence par option', message: `Écart détecté pour ${mismatches.length} élément(s) : ${noms}.` })
    }

    // C2 : Plafond de voix (Max 9 voix par bulletin)
    // const maxVoixPossibles = ballotsFound * 9
    // if (totalVoixCandidats.value > maxVoixPossibles) {
    //     details.push({
    //         key: 'c2', level: 'error', title: 'C2 — Plafond de voix',
    //         message: `Le total des voix (${totalVoixCandidats.value}) dépasse le maximum théorique (${maxVoixPossibles} voix pour ${ballotsFound} bulletins × 9).`
    //     })
    // } else {
    //     details.push({ key: 'c2', level: 'ok', title: 'C2 — Plafond de voix', message: `Le total des voix (${totalVoixCandidats.value}) est cohérent (max autorisé : ${maxVoixPossibles}).` })
    // }

    // C3 : Plancher de voix (Min 1 voix par bulletin valable)
    const bulletinsValables = Math.max(0, ballotsFound - totalBlancsNuls.value)
    if (totalVoixCandidats.value < bulletinsValables) {
        details.push({
            key: 'c3', level: 'error', title: 'C3 — Plancher de voix',
            message: `Le total des voix (${totalVoixCandidats.value}) est inférieur au nombre de bulletins valables (${bulletinsValables}). Chaque bulletin valable doit contenir au moins 1 vote.`
        })
    } else {
        details.push({ key: 'c3', level: 'ok', title: 'C3 — Plancher de voix', message: `Le total des voix couvre bien tous les bulletins valables (${bulletinsValables}).` })
    }

    // C4 : Bulletins trouvés (Manuel) vs Système
    const ecartSysteme = Math.abs(ballotsFound - systemBallots)
    if (ecartSysteme === 0) {
        details.push({ key: 'c4', level: 'ok', title: 'C4 — Cohérence des bulletins', message: `Le nombre de bulletins trouvés (${ballotsFound}) correspond au comptage système.` })
    } else {
        details.push({
            key: 'c4', level: 'warning', title: 'C4 — Cohérence des bulletins',
            message: `Écart de ${ecartSysteme} bulletin(s) entre la saisie manuelle (${ballotsFound}) et le système (${systemBallots}).`
        })
    }

    return details
})

const hasCriticalError = computed(() => controlDetails.value.some(d => d.level === 'error'))
const hasWarning = computed(() => controlDetails.value.some(d => d.level === 'warning'))

const submit = () => {
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
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Option</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-gray-600">Compteur système</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-gray-600">PV papier (Saisie)</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-gray-600">Écart</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="(counter, idx) in counters" :key="counter.id" :class="counter.type !== 'candidat' ? 'bg-gray-50' : ''">
                            <td class="px-3 py-2 text-sm text-gray-900">
                                <span :class="counter.type !== 'candidat' ? 'font-semibold text-gray-700' : ''">
                                    {{ counter.nom }}
                                </span>
                                <div v-if="counter.procuration > 0" class="text-xs text-purple-600 font-normal mt-0.5">
                                    dont {{ counter.procuration }} par procuration
                                </div>
                            </td>
                            <td class="px-3 py-2 text-center font-mono text-gray-600">{{ counter.system_count }}</td>
                            <td class="px-3 py-2 text-center">
                                <input v-model.number="form.pv_data[idx].count" type="number" min="0"
                                       class="w-24 px-2 py-1 border border-gray-300 rounded text-center focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow" />
                            </td>
                            <td class="px-3 py-2 text-center font-mono"
                                :class="(toInt(form.pv_data[idx].count) - counter.system_count) === 0 ? 'text-green-600' : 'text-red-600'">
                                {{ toInt(form.pv_data[idx].count) - counter.system_count }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Statistiques et Totaux en temps réel -->
            <div class="bg-white rounded-xl border border-gray-100 p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-4 uppercase">Statistiques du bureau</h2>
                
                <!-- Récapitulatif automatique pour guider l'opérateur -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="p-3 bg-blue-50 rounded-lg border border-blue-200 flex justify-between items-center">
                        <span class="text-sm font-medium text-blue-800">📊 Bulletins comptés (Système)</span>
                        <span class="text-xl font-bold font-mono text-blue-900">{{ system_ballots_count }}</span>
                    </div>
                    <div class="p-3 bg-indigo-50 rounded-lg border border-indigo-200 flex justify-between items-center">
                        <span class="text-sm font-medium text-indigo-800">🗳️ Total des voix (Candidats)</span>
                        <span class="text-xl font-bold font-mono text-indigo-900">{{ totalVoixCandidats }}</span>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nombre de bulletins trouvés (Saisie manuelle)
                        <span class="block text-xs font-normal text-gray-500 mt-0.5">
                            Rappel : 1 bulletin peut contenir de 1 à 9 voix. Le nombre de votants est automatiquement égal à ce chiffre.
                        </span>
                    </label>
                    <input v-model.number="form.ballots_found" type="number" min="0"
                           class="w-full md:w-1/2 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow" />
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
                    ⚠️ Anomalie(s) critique(s) détectée(s). Vous pouvez tout de même enregistrer, mais le bureau sera marqué pour vérification obligatoire.
                </p>
                <p v-else-if="hasWarning" class="mt-3 text-sm font-medium text-amber-700">
                    ℹ️ Des écarts non bloquants subsistent. Vérifiez les chiffres avant validation si possible.
                </p>
            </div>

            <!-- Actions -->
            <div class="flex gap-3 pb-8">
                <button type="submit"
                        :disabled="form.processing"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    Enregistrer le PV
                </button>
                <Link :href="`/operator/dashboard`"
                      class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg font-medium transition-colors">
                    Annuler
                </Link>
            </div>
        </form>
    </AuthenticatedLayout>
</template>