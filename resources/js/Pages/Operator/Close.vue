<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    bureau:     Object,
    results:    Array,
    statistics: Object,
})

const candidates = computed(() => props.results.filter(r => r.type === 'candidat' || r.type === 'nul' || r.type === 'blanc'))
const totalVotes = computed(() => candidates.value.reduce((s, r) => s + r.count, 0))

const form = useForm({})
const closeBureau = () => {
    if (confirm('Valider définitivement ce bureau ? Cette action est irréversible.')) {
        form.post('/operator/cloture')
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Clôture — {{ bureau.nom }}</h1>
        </template>

        <div class="max-w-3xl mx-auto space-y-6">
            <!-- Statut actuel -->
            <div class="bg-white rounded-xl border border-gray-100 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">{{ bureau.nom }}</h2>
                        <p class="text-sm text-gray-500 mt-1">Statut actuel : <span class="font-semibold">{{ bureau.status }}</span></p>
                    </div>
                </div>
            </div>

            <!-- Résumé résultats -->
            <div class="bg-white rounded-xl border border-gray-100 p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-4 uppercase">Résultats finaux</h2>
                <div class="space-y-3">
                    <div v-for="r in results" :key="r.nom"
                         class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                        <span class="text-sm text-gray-700">{{ r.nom }}</span>
                        <span class="text-lg font-bold text-gray-900">{{ r.count }}</span>
                    </div>
                    <div class="flex items-center justify-between pt-3 border-t-2 border-gray-300">
                        <span class="text-sm font-semibold text-gray-900">Total votes exprimés</span>
                        <span class="text-xl font-black text-blue-600">{{ totalVotes }}</span>
                    </div>
                </div>
            </div>

            <!-- Statistiques -->
            <div v-if="statistics" class="bg-white rounded-xl border border-gray-100 p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-4 uppercase">Statistiques</h2>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="text-2xl font-bold text-gray-900">{{ statistics.registered_voters }}</div>
                        <div class="text-xs text-gray-500 mt-1">Inscrits</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-900">{{ statistics.voters }}</div>
                        <div class="text-xs text-gray-500 mt-1">Votants</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-900">{{ statistics.ballots_found }}</div>
                        <div class="text-xs text-gray-500 mt-1">Bulletins</div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-3">
                <button v-if="bureau.status !== 'validated'"
                        @click="closeBureau"
                        :disabled="form.processing"
                        class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold disabled:opacity-50">
                    ✓ Valider définitivement
                </button>
                <div v-else class="bg-green-50 border border-green-200 text-green-800 px-6 py-3 rounded-lg">
                    ✓ Ce bureau est déjà validé
                </div>
                <Link :href="`/operator/dashboard`"
                      class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-medium">
                    Retour
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>