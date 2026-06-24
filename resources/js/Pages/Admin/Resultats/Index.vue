<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    results:           Array,
    total_candidates:  Number,
    validated_bureaux: Number,
    total_bureaux:     Number,
    source_breakdown:  Object,
})

const candidates = props.results.filter(r => r.type === 'candidat')
const others = props.results.filter(r => r.type !== 'candidat')
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">Résultats globaux</h1>
                <Link href="/admin/resultats/export"
                      class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    ⬇ Exporter CSV
                </Link>
            </div>
        </template>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="text-3xl font-bold text-gray-900">{{ validated_bureaux }} / {{ total_bureaux }}</div>
                <div class="text-sm text-gray-500 mt-1">Bureaux validés</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="text-3xl font-bold text-green-600">{{ total_candidates.toLocaleString('fr-FR') }}</div>
                <div class="text-sm text-gray-500 mt-1">Votes exprimés</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="text-sm text-gray-500 mb-2">Sources</div>
                <div class="text-xs space-y-1">
                    <div class="flex justify-between">
                        <span>Opérateur</span>
                        <span class="font-semibold">{{ source_breakdown.counting || 0 }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Admin (PV)</span>
                        <span class="font-semibold">{{ source_breakdown.manual_pv || 0 }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Admin (override)</span>
                        <span class="font-semibold">{{ source_breakdown.admin_override || 0 }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Résultats candidats -->
        <div class="bg-white rounded-xl border border-gray-100 p-6 mb-6">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 uppercase">Résultats par candidat</h2>
            <div class="space-y-4">
                <div v-for="(r, idx) in candidates" :key="r.id">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-sm font-medium text-gray-700">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-100 text-blue-700 text-xs font-bold mr-2">
                                {{ idx + 1 }}
                            </span>
                            {{ r.nom }}
                        </span>
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-bold text-gray-900">{{ r.total.toLocaleString('fr-FR') }}</span>
                            <span v-if="total_candidates > 0" class="text-sm text-blue-600 font-semibold w-16 text-right">
                                {{ ((r.total / total_candidates) * 100).toFixed(1) }}%
                            </span>
                        </div>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-600 rounded-full transition-all"
                             :style="`width:${total_candidates > 0 ? (r.total / total_candidates) * 100 : 0}%`"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Blanc / Nul -->
        <div class="bg-white rounded-xl border border-gray-100 p-6">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 uppercase">Bulletins blancs et nuls</h2>
            <div class="grid grid-cols-2 gap-4">
                <div v-for="r in others" :key="r.id"
                     class="bg-gray-50 rounded-lg p-4 text-center">
                    <div class="text-sm text-gray-600">{{ r.nom }}</div>
                    <div class="text-3xl font-bold text-gray-900 mt-1">{{ r.total }}</div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>