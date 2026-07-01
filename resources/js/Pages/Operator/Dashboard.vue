<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    bureau:            Object,
    counters:          Array,
    pv_results:        Array,
    statistics:        Object,
    error:             String,
    total_procuration: Number,
})

const statusLabel = {
    pending:   { label: 'En attente',  cls: 'bg-gray-100 text-gray-600' },
    counting:  { label: 'Comptage',    cls: 'bg-blue-100 text-blue-700' },
    pv_entry:  { label: 'Saisie PV',   cls: 'bg-yellow-100 text-yellow-700' },
    validated: { label: 'Validé',      cls: 'bg-green-100 text-green-700' },
    anomaly:   { label: 'Anomalie',    cls: 'bg-red-100 text-red-700' },
}

const candidates = props.counters.filter(c => c.type === 'candidat' || c.type === 'nul'|| c.type === 'blanc' )
const totalVotes = candidates.reduce((sum, c) => sum + c.count, 0)
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Tableau de bord Opérateur</h1>
        </template>

        <!-- Erreur si pas de bureau -->
        <div v-if="error" class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
            <p class="text-red-800 font-medium">{{ error }}</p>
        </div>

        <template v-else-if="bureau">
            <!-- Info bureau -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">{{ bureau.nom }}</h2>
                        <p class="text-sm text-gray-500 mt-1">Code : {{ bureau.code }}</p>
                    </div>
                    <span :class="statusLabel[bureau.status]?.cls ?? 'bg-gray-100 text-gray-600'"
                          class="text-sm font-medium px-3 py-1.5 rounded-full">
                        {{ statusLabel[bureau.status]?.label ?? bureau.status }}
                    </span>
                </div>
            </div>

            <!-- Compteurs en temps réel -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-6">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-800">Comptage en cours</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div v-for="counter in counters" :key="counter.id"
                             class="bg-gray-50 rounded-xl p-4 text-center">
                            <div class="text-sm text-gray-600 mb-1">{{ counter.nom }}</div>
                            <div class="text-3xl font-bold text-gray-900">{{ counter.count }}</div>
                            <div v-if="counter.procuration > 0" class="text-xs text-purple-600 font-medium mt-1">
                                dont {{ counter.procuration }} par procuration
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-center gap-6 text-center flex-wrap">
                        <div>
                            <span class="text-sm text-gray-500">Total votes exprimés : </span>
                            <span class="text-lg font-bold text-gray-900">{{ totalVotes }}</span>
                        </div>
                        <div v-if="total_procuration > 0">
                            <span class="text-sm text-purple-600">Dont procurations : </span>
                            <span class="text-lg font-bold text-purple-700">{{ total_procuration }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Link :href="`/operator/comptage`"
                      class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl p-6 text-center transition-colors">
                    <div class="text-lg font-semibold">Comptage</div>
                    <div class="text-sm text-blue-100 mt-1">Enregistrer les votes</div>
                </Link>
                <Link :href="`/operator/pv`"
                      class="bg-yellow-600 hover:bg-yellow-700 text-white rounded-xl p-6 text-center transition-colors">
                    <div class="text-lg font-semibold">Vérification PV</div>
                    <div class="text-sm text-yellow-100 mt-1">Saisir le PV papier</div>
                </Link>
                <Link :href="`/operator/cloture`"
                      class="bg-green-600 hover:bg-green-700 text-white rounded-xl p-6 text-center transition-colors">
                    <div class="text-lg font-semibold">Clôturer</div>
                    <div class="text-sm text-green-100 mt-1">Valider définitivement</div>
                </Link>
            </div>
        </template>
    </AuthenticatedLayout>
</template>