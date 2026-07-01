<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    bureau: Object,
    counters: Array,
    pv_values: Object,
})

const form = useForm({
    pv_data: props.counters.map(c => ({
        vote_option_id: c.id,
        count: props.pv_values[c.id] ?? c.system_count,
    })),
    registered_voters: props.bureau.statistics?.registered_voters ?? 0,
    voters: props.bureau.statistics?.voters ?? 0,
    ballots_found: props.bureau.statistics?.ballots_found ?? 0,
    note: '',
    mark_anomaly: false,
})

// Contrôles de cohérence
const totalPv = computed(() => form.pv_data.reduce((sum, p) => sum + (parseInt(p.count) || 0), 0))
const totalSystem = computed(() => props.counters.reduce((sum, c) => sum + c.system_count, 0))

const controls = computed(() => ({
    c1: form.pv_data.every(p => parseInt(p.count) === props.counters.find(c => c.id === p.vote_option_id)?.system_count),
    c2: totalPv.value === form.ballots_found,
    c3: form.ballots_found <= form.voters,
    c4: form.voters <= form.registered_voters,
}))

const allControlsPass = computed(() => Object.values(controls.value).every(v => v))

const submit = () => {
    form.post(`/admin/bureaux/${props.bureau.id}/pv-manuel`)
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Saisie PV manuel - {{ bureau.code }}</h1>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Info bureau -->
            <div class="bg-white rounded-xl border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-900">{{ bureau.nom }}</h2>
                <p class="text-sm text-gray-500 mt-1">Statut actuel : {{ bureau.status }}</p>
            </div>

            <!-- Tableau comparatif -->
            <form @submit.prevent="submit" class="bg-white rounded-xl border border-gray-100 p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Résultats par candidat</h3>
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Candidat</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold text-gray-600">Compteur système</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold text-gray-600">PV papier</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold text-gray-600">Écart</th>
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
                                <td class="px-3 py-2 text-center text-sm font-mono text-gray-600">{{ counter.system_count }}</td>
                                <td class="px-3 py-2 text-center">
                                    <input v-model.number="form.pv_data[idx].count" type="number" min="0"
                                           class="w-24 px-2 py-1 border border-gray-300 rounded text-center" />
                                </td>
                                <td class="px-3 py-2 text-center text-sm font-mono"
                                    :class="(form.pv_data[idx].count - counter.system_count) === 0 ? 'text-green-600' : 'text-red-600'">
                                    {{ form.pv_data[idx].count - counter.system_count }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Statistiques -->
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

                <!-- Contrôles -->
                <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Contrôles de cohérence</h3>
                    <div class="flex items-center gap-2">
                        <span :class="controls.c1 ? 'text-green-600' : 'text-red-600'">
                            {{ controls.c1 ? '✓' : '✗' }}
                        </span>
                        <span class="text-sm text-gray-700">C1 - Cohérence candidat</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="controls.c2 ? 'text-green-600' : 'text-red-600'">
                            {{ controls.c2 ? '✓' : '✗' }}
                        </span>
                        <span class="text-sm text-gray-700">C2 - Total bulletins</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="controls.c3 ? 'text-green-600' : 'text-red-600'">
                            {{ controls.c3 ? '✓' : '✗' }}
                        </span>
                        <span class="text-sm text-gray-700">C3 - Bulletins ≤ Votants</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="controls.c4 ? 'text-green-600' : 'text-red-600'">
                            {{ controls.c4 ? '✓' : '✗' }}
                        </span>
                        <span class="text-sm text-gray-700">C4 - Votants ≤ Inscrits</span>
                    </div>
                </div>

                <!-- Note -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Note / Motif</label>
                    <textarea v-model="form.note" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                              placeholder="Motif de la saisie manuelle..."></textarea>
                </div>

                <!-- Actions -->
                <div class="flex gap-3 pt-4">
                    <button type="submit"
                            :disabled="form.processing"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium disabled:opacity-50">
                        Enregistrer le PV
                    </button>
                    <button type="button"
                            @click="form.mark_anomaly = true; submit()"
                            :disabled="form.processing"
                            class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg font-medium disabled:opacity-50">
                        Marquer anomalie
                    </button>
                    <Link href="/admin/bureaux"
                          class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg font-medium">
                        Annuler
                    </Link>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>