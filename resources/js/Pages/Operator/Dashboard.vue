<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import GeometricVotes from '@/Components/GeometricVotes.vue' 
import { ref, computed } from 'vue'

const props = defineProps({
    bureau: Object,
    counters: Array,
    pv_results: Array,
    statistics: Object,
    error: String,
    total_procuration: Number,
})

const showModal = ref(false)

const statusLabel = {
    pending:   { label: 'En attente',  cls: 'bg-gray-100 text-gray-600' },
    counting:  { label: 'Comptage',    cls: 'bg-blue-100 text-blue-700' },
    pv_entry:  { label: 'Saisie PV',   cls: 'bg-yellow-100 text-yellow-700' },
    validated: { label: 'Validé',      cls: 'bg-green-100 text-green-700' },
    anomaly:   { label: 'Anomalie',    cls: 'bg-red-100 text-red-700' },
}

const candidates = computed(() => 
    props.counters.filter(c => c.type === 'candidat' || c.type === 'nul' || c.type === 'blanc')
)

const totalVotes = computed(() => 
    candidates.value.reduce((sum, c) => sum + c.count, 0)
)

const getPhotoUrl = (c) => c.photo ? `/storage/${c.photo}` : null

const onImgError = (e) => {
    e.target.src = '/images/candidat-placeholder.png'
}

// Fermer le modal avec la touche Echap
const handleKeydown = (e) => {
    if (e.key === 'Escape') showModal.value = false
}
</script>

<template>
    <AuthenticatedLayout @keydown.escape="handleKeydown">
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Tableau de bord Opérateur</h1>
        </template>

        <div v-if="error" class="bg-red-50 border border-red-200 rounded-xl p-6 text-center mb-6">
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
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <h2 class="font-semibold text-gray-800">Comptage en cours</h2>
                    <!-- BOUTON POUR OUVRIR LE MODAL GÉOMÉTRIQUE -->
                    <button 
                        @click="showModal = true"
                        class="text-sm bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        Vue géométrique
                    </button>
                </div>
                <div class="p-6">
    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3">
        <div v-for="counter in counters" :key="counter.id" class="bg-gray-50 rounded-xl overflow-hidden text-center border border-gray-100">
            <div v-if="counter.type === 'candidat'" class="relative w-full bg-gray-200 overflow-hidden">
                <img :src="getPhotoUrl(counter) || '/images/candidat-placeholder.png'" @error="onImgError" :alt="counter.nom" class="w-full h-full object-cover object-top" />
                <span v-if="counter.ordre_affichage" class="absolute top-1.5 left-1.5 min-w-[36px] h-7 px-1.5 inline-flex items-center justify-center rounded-md bg-blue-600 text-white font-black text-xs shadow-lg ring-2 ring-white">
                    N°{{ counter.ordre_affichage }}
                </span>
            </div>
            <div class="p-2.5">
                <div class="text-xs text-gray-600 mb-1 truncate font-medium">{{ counter.nom }}</div>
                <div class="text-xl font-bold text-gray-900">{{ counter.count }}</div>
                <div v-if="counter.procuration > 0" class="text-[10px] text-purple-600 font-medium mt-0.5">
                    dont {{ counter.procuration }} proc.
                </div>
            </div>
        </div>
    </div>
    <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-center gap-6 text-center flex-wrap">
        <div>
            <span class="text-sm text-gray-500">Total votes : </span>
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
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <Link :href="`/operator/comptage`" class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl p-6 text-center transition-colors shadow-sm">
                    <div class="text-lg font-semibold">Comptage</div>
                    <div class="text-sm text-blue-100 mt-1">Enregistrer les votes</div>
                </Link>
                <Link :href="`/operator/pv`" class="bg-yellow-600 hover:bg-yellow-700 text-white rounded-xl p-6 text-center transition-colors shadow-sm">
                    <div class="text-lg font-semibold">Vérification PV</div>
                    <div class="text-sm text-yellow-100 mt-1">Saisir le PV papier</div>
                </Link>
                <Link :href="`/operator/cloture`" class="bg-green-600 hover:bg-green-700 text-white rounded-xl p-6 text-center transition-colors shadow-sm">
                    <div class="text-lg font-semibold">Clôturer</div>
                    <div class="text-sm text-green-100 mt-1">Valider définitivement</div>
                </Link>
            </div>
        </template>

        <!-- GRAND MODAL DE DÉPOUILLEMENT GÉOMÉTRIQUE -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showModal = false">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-7xl max-h-[90vh] flex flex-col">
                <!-- Header du modal -->
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50 rounded-t-2xl">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Dépouillement géométrique</h3>
                        <p class="text-sm text-gray-500">Bureau : {{ bureau.nom }} ({{ bureau.code }})</p>
                    </div>
                    <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 hover:bg-gray-200 p-2 rounded-full transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Contenu scrollable du modal -->
                <div class="p-6 overflow-y-auto flex-1">
                    <div class="md:grid-cols-2 gap-x-8 gap-y-2">
                        <GeometricVotes 
                            v-for="c in candidates" 
                            :key="c.id" 
                            :count="c.count" 
                            :name="c.nom" 
                        />
                    </div>

                    <div class="mt-8 pt-6 border-t-2 border-gray-100 flex justify-between items-center bg-blue-50 p-4 rounded-xl">
                        <span class="text-lg font-bold text-gray-800">TOTAL GÉNÉRAL</span>
                        <span class="text-3xl font-mono font-black text-blue-700">{{ totalVotes }} voix</span>
                    </div>
                </div>

                <!-- Footer du modal -->
                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl flex justify-end">
                    <button @click="showModal = false" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded-lg font-medium transition-colors">
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>