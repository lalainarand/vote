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

const form = useForm({
    pv_data: props.counters.map(c => ({
        vote_option_id: c.id,
        count: props.pv_values[c.id] ?? c.system_count,
    })),
    registered_voters: props.statistics?.registered_voters ?? 0,
    voters: props.statistics?.voters ?? 0,
    ballots_found: props.statistics?.ballots_found ?? 0,
})

const totalPv = computed(() => form.pv_data.reduce((s, p) => s + (parseInt(p.count) || 0), 0))

// Contrôles C1-C4
const controls = computed(() => {
    const c1 = form.pv_data.every(p => {
        const counter = props.counters.find(c => c.id === p.vote_option_id)
        return counter && parseInt(p.count) === counter.system_count
    })
    const c2 = totalPv.value === parseInt(form.ballots_found)
    const c3 = parseInt(form.ballots_found) <= parseInt(form.voters)
    const c4 = parseInt(form.voters) <= parseInt(form.registered_voters)
    return { c1, c2, c3, c4 }
})

const allPass = computed(() => Object.values(controls.value).every(v => v))

const submit = () => form.post('/operator/pv')
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
                            <td class="px-3 py-2 text-sm text-gray-900">{{ counter.nom }}</td>
                            <td class="px-3 py-2 text-center font-mono text-gray-600">{{ counter.system_count }}</td>
                            <td class="px-3 py-2 text-center">
                                <input v-model.number="form.pv_data[idx].count" type="number" min="0"
                                       class="w-24 px-2 py-1 border border-gray-300 rounded text-center" />
                            </td>
                            <td class="px-3 py-2 text-center font-mono"
                                :class="(form.pv_data[idx].count - counter.system_count) === 0 ? 'text-green-600' : 'text-red-600'">
                                {{ form.pv_data[idx].count - counter.system_count }}
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span :class="form.pv_data[idx].count === counter.system_count ? 'text-green-600' : 'text-red-600'">
                                    {{ form.pv_data[idx].count === counter.system_count ? '✓' : '✗' }}
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
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <span :class="controls.c1 ? 'text-green-600' : 'text-red-600'" class="text-lg font-bold">
                            {{ controls.c1 ? '✓' : '✗' }}
                        </span>
                        <span class="text-sm text-gray-700">C1 — Cohérence candidat (compteur = PV)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="controls.c2 ? 'text-green-600' : 'text-red-600'" class="text-lg font-bold">
                            {{ controls.c2 ? '✓' : '✗' }}
                        </span>
                        <span class="text-sm text-gray-700">C2 — ∑ bulletins = Bulletins trouvés ({{ totalPv }} / {{ form.ballots_found }})</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="controls.c3 ? 'text-green-600' : 'text-red-600'" class="text-lg font-bold">
                            {{ controls.c3 ? '✓' : '✗' }}
                        </span>
                        <span class="text-sm text-gray-700">C3 — Bulletins ≤ Votants</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="controls.c4 ? 'text-green-600' : 'text-red-600'" class="text-lg font-bold">
                            {{ controls.c4 ? '✓' : '✗' }}
                        </span>
                        <span class="text-sm text-gray-700">C4 — Votants ≤ Inscrits</span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-3">
                <button type="submit"
                        :disabled="form.processing"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium disabled:opacity-50">
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