<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import GeometricVotes from '@/Components/GeometricVotes.vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    bureau: Object,
    counters: Array,
    pv_results: Array,
    statistics: Object,
    error: String,
    reset_count: { type: Number, default: 0 },
    reset_history: { type: Array, default: () => [] },
    total_procuration: Number,
    bulletin_count: {
        type: Number,
        default: 0,
    },
    bulletin_images: {
        type: Array,
        default: () => [],
    },
})

const showModal = ref(false)
const showResetModalGet = ref(false)

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

// ── Galerie photos du bureau — zoom (lightbox) ──────────────────────────────
const zoomIndex = ref(null) // null = fermé, sinon index dans bulletin_images
const zoomOpen = computed(() => zoomIndex.value !== null)
const zoomImage = computed(() =>
    zoomIndex.value !== null ? props.bulletin_images[zoomIndex.value] : null
)

const openZoom = (index) => { zoomIndex.value = index }
const closeZoom = () => { zoomIndex.value = null }

const nextZoom = () => {
    if (zoomIndex.value === null) return
    zoomIndex.value = (zoomIndex.value + 1) % props.bulletin_images.length
}
const prevZoom = () => {
    if (zoomIndex.value === null) return
    zoomIndex.value = (zoomIndex.value - 1 + props.bulletin_images.length) % props.bulletin_images.length
}

// ── Réinitialisation du comptage ────────────────────────────────────────────
const showResetModal = ref(false)
const resetReason = ref('')
const resetLoading = ref(false)
const resetError = ref(null)
const resetConfirmText = ref('') // sécurité supplémentaire : taper "RESET" pour confirmer

const openResetModal = () => {
    resetReason.value = ''
    resetConfirmText.value = ''
    resetError.value = null
    showResetModal.value = true
}

const closeResetModal = () => {
    showResetModal.value = false
}

const submitReset = async () => {
    if (resetConfirmText.value.trim().toUpperCase() !== 'RESET') {
        resetError.value = 'Tapez RESET pour confirmer.'
        return
    }

    resetLoading.value = true
    resetError.value = null

    try {
        await axios.post('/operator/comptage/reset', {
            reason: resetReason.value || null,
        })

        closeResetModal()
        // Recharge les données de la page (compteurs remis à zéro, etc.)
        router.reload()
    } catch (e) {
        resetError.value = e.response?.data?.error || 'Erreur lors de la réinitialisation.'
    } finally {
        resetLoading.value = false
    }
}

// Fermer les modals avec la touche Echap (écoute sur window, seule façon fiable)
const handleKeydown = (e) => {
    if (e.key === 'Escape') {
        if (zoomOpen.value) {
            closeZoom()
        } else if (showResetModal.value) {
            closeResetModal()
        } else if (showModal.value) {
            showModal.value = false
        }
    } else if (zoomOpen.value && e.key === 'ArrowRight') {
        nextZoom()
    } else if (zoomOpen.value && e.key === 'ArrowLeft') {
        prevZoom()
    }
}

onMounted(() => window.addEventListener('keydown', handleKeydown))
onUnmounted(() => window.removeEventListener('keydown', handleKeydown))
</script>

<template>
    <AuthenticatedLayout>
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

            <!-- ── Carte d'information sur les Réinitialisations ─────────────────── -->
                <div v-if="reset_count > 0" 
                    @click="showResetModalGet = true"
                    class="mb-6 bg-amber-50 border border-amber-200 rounded-xl p-4 cursor-pointer hover:bg-amber-100 transition-colors group">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="bg-amber-100 p-2 rounded-full text-amber-600 group-hover:bg-amber-200 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-amber-900">Historique des réinitialisations</h3>
                                <p class="text-sm text-amber-700">
                                    <span class="font-bold">{{ reset_count }}</span> réinitialisation(s) effectuée(s) sur ce bureau.
                                </p>
                            </div>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-400 group-hover:text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </div>

                <!-- Message discret si aucun reset (optionnel, pour montrer que le système est actif) -->
                    <div v-else class="mb-6 bg-green-50 border border-green-200 rounded-xl p-3 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm font-medium text-green-800">Aucune réinitialisation n'a été effectuée sur ce bureau.</span>
                    </div>

            <!-- Compteurs en temps réel -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-6">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center flex-wrap gap-2">
                    <h2 class="font-semibold text-gray-800">Comptage en cours</h2>
                    <div class="flex items-center gap-2">
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

                        <!-- BOUTON RÉINITIALISER LE COMPTAGE -->
                        <button
                            @click="openResetModal"
                            class="text-sm bg-red-50 hover:bg-red-100 text-red-700 px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Réinitialiser le comptage
                        </button>
                    </div>
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
        <div>
            <span class="text-sm text-amber-600">Bulletins dépouillés : </span>
            <span class="text-lg font-bold text-amber-700">{{ bulletin_count }}</span>
        </div>
    </div>
</div>
            </div>

            <!-- ── Photos du bureau (compteurs scannés) ─────────────────── -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-6">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <h2 class="font-semibold text-gray-800">
                        Photos du bureau
                        <span class="text-gray-400 font-normal text-sm">({{ bulletin_images.length }})</span>
                    </h2>
                </div>
                <div class="p-6">
                    <div v-if="bulletin_images.length === 0" class="text-center text-sm text-gray-400 py-6">
                        Aucune photo enregistrée pour ce bureau.
                    </div>
                    <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                        <button
                            v-for="(img, index) in bulletin_images" :key="img.id"
                            @click="openZoom(index)"
                            class="group relative aspect-square rounded-xl overflow-hidden bg-gray-100 border border-gray-200 hover:ring-2 hover:ring-blue-500 transition-all"
                        >
                            <img :src="img.url" :alt="img.filename" class="w-full h-full object-cover" loading="lazy" />
                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors flex items-center justify-center">
                                <svg class="w-6 h-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5A6.5 6.5 0 114 10.5a6.5 6.5 0 0113 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 8v5M8.5 10.5h5"/>
                                </svg>
                            </div>
                            <span class="absolute bottom-1 left-1 right-1 bg-black/60 text-white text-[10px] font-mono px-1.5 py-0.5 rounded truncate">
                                {{ img.taken_at }}
                            </span>
                        </button>
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

        <!-- ══ ZOOM PHOTO (lightbox) ══════════════════════════════════════ -->
        <Teleport to="body">
            <div v-if="zoomOpen"
                 @click.self="closeZoom"
                 class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/85">

                <!-- Bouton fermer -->
                <button @click="closeZoom"
                        class="absolute top-4 right-4 text-white/80 hover:text-white bg-white/10 hover:bg-white/20 p-2.5 rounded-full transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                <!-- Flèche précédent -->
                <button v-if="bulletin_images.length > 1" @click.stop="prevZoom"
                        class="absolute left-4 top-1/2 -translate-y-1/2 text-white/80 hover:text-white bg-white/10 hover:bg-white/20 p-3 rounded-full transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>

                <!-- Flèche suivant -->
                <button v-if="bulletin_images.length > 1" @click.stop="nextZoom"
                        class="absolute right-4 top-1/2 -translate-y-1/2 text-white/80 hover:text-white bg-white/10 hover:bg-white/20 p-3 rounded-full transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>

                <!-- Image zoomée -->
                <div class="max-w-[92vw] max-h-[85vh] flex flex-col items-center gap-3" @click.stop>
                    <img :src="zoomImage?.url" :alt="zoomImage?.filename"
                         class="max-w-full max-h-[75vh] object-contain rounded-lg shadow-2xl" />
                    <div class="flex items-center gap-3 text-white/80 text-sm font-mono">
                        <span>{{ zoomImage?.taken_at }}</span>
                        <span v-if="bulletin_images.length > 1" class="text-white/50">
                            {{ zoomIndex + 1 }} / {{ bulletin_images.length }}
                        </span>
                        <a :href="zoomImage?.url" download
                           class="text-blue-300 hover:text-blue-200 underline">
                            Télécharger
                        </a>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ══ CONFIRMATION — Réinitialiser le comptage ══════════════════ -->
        <Teleport to="body">
            <div v-if="showResetModal"
                 @click.self="closeResetModal"
                 class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/60">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-8.25 3.75h.008v.008h-.008v-.008z"/>
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-gray-900">Réinitialiser le comptage</h3>
                    </div>

                    <p class="text-sm text-gray-600 mb-1">
                        Tous les compteurs (candidats + bulletins) de <strong>{{ bureau?.nom }}</strong> seront remis à zéro.
                        Vous pourrez ressaisir les votes depuis le début.
                    </p>
                    <p class="text-xs text-gray-400 mb-4">
                        Rien n'est supprimé : l'ancien comptage reste conservé dans le journal d'audit.
                    </p>

                    <label class="block text-sm font-medium text-gray-700 mb-1">Raison (optionnel)</label>
                    <textarea
                        v-model="resetReason"
                        rows="2"
                        placeholder="Ex: erreur de comptage constatée, double-clic massif..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm mb-4 resize-none
                               focus:ring-2 focus:ring-red-500 focus:border-transparent"
                    ></textarea>

                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Tapez <span class="font-mono font-bold text-red-600">RESET</span> pour confirmer
                    </label>
                    <input
                        v-model="resetConfirmText"
                        type="text"
                        placeholder="RESET"
                        @keyup.enter="submitReset"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono
                               focus:ring-2 focus:ring-red-500 focus:border-transparent"
                    />
                    <p v-if="resetError" class="text-sm text-red-600 mt-1.5">{{ resetError }}</p>

                    <div class="flex gap-2 mt-5">
                        <button
                            @click="submitReset"
                            :disabled="resetLoading || resetConfirmText.trim().toUpperCase() !== 'RESET'"
                            class="flex-1 bg-red-600 hover:bg-red-700 active:scale-95
                                   disabled:opacity-50 disabled:cursor-not-allowed
                                   text-white font-bold py-2.5 rounded-lg text-sm transition-all duration-100"
                        >
                            {{ resetLoading ? 'Réinitialisation...' : 'Confirmer la réinitialisation' }}
                        </button>
                        <button
                            @click="closeResetModal"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2.5 px-4 rounded-lg text-sm"
                        >
                            Annuler
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ══ MODAL : DÉTAIL DES RÉINITIALISATIONS ═══════════════════════════ -->
        <Teleport to="body">
            <div v-if="showResetModalGet" class="fixed inset-0 z-[80] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4" @click.self="showResetModalGet = false">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col">
                    
                    <!-- Header du modal -->
                    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-amber-50 rounded-t-2xl">
                        <div class="flex items-center gap-3">
                            <div class="bg-amber-100 p-2 rounded-full text-amber-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Journal des réinitialisations</h3>
                                <p class="text-sm text-gray-500">Bureau : {{ bureau.nom }}</p>
                            </div>
                        </div>
                        <button @click="showResetModalGet = false" class="text-gray-400 hover:text-gray-600 hover:bg-gray-200 p-2 rounded-full transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Contenu scrollable -->
                    <div class="p-6 overflow-y-auto flex-1">
                        <div v-if="reset_history.length === 0" class="text-center text-gray-500 py-8">
                            Aucune donnée disponible.
                        </div>

                        <div v-else class="space-y-4">
                            <div v-for="reset in reset_history" :key="reset.id" class="border border-gray-200 rounded-xl p-4 hover:bg-gray-50 transition-colors">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="bg-gray-200 text-gray-700 text-xs font-bold px-2 py-1 rounded">
                                            {{ reset.user_name }}
                                        </span>
                                        <span class="text-xs text-gray-500 flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            {{ reset.created_at }}
                                        </span>
                                    </div>
                                    <span class="text-sm font-bold text-red-600 bg-red-50 px-2 py-1 rounded">
                                        -{{ reset.total_votes_annules }} voix
                                    </span>
                                </div>
                                
                                <div class="bg-amber-50 border-l-4 border-amber-400 p-3 rounded-r">
                                    <p class="text-xs font-semibold text-amber-800 uppercase mb-1">Motif de la réinitialisation</p>
                                    <p class="text-sm text-gray-800 italic">
                                        "{{ reset.reason }}"
                                    </p>
                                </div>

                                <div class="mt-2 text-xs text-gray-500 text-right">
                                    Bulletins dépouillés remis à zéro : <span class="font-mono font-semibold">{{ reset.bulletin_count_annule }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer du modal -->
                    <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl flex justify-end">
                        <button @click="showResetModalGet = false" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded-lg font-medium transition-colors">
                            Fermer
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>