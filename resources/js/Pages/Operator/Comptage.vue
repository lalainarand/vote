<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import axios from 'axios'
import { reactive, ref, computed } from 'vue'

const props = defineProps({
    bureau:         Object,
    locked:         Boolean,
    candidates:     Array,
    blanc_nul:      Array,
    bulletin_count: {
        type: Number,
        default: 0,
    },
})

// ── Compteurs locaux réactifs ─────────────────────────────────────────────
const counts = reactive(
    Object.fromEntries(
        [...(props.candidates ?? []), ...(props.blanc_nul ?? [])]
            .map(c => [c.id, c.count ?? 0])
    )
)

// ── Animation compteur ────────────────────────────────────────────────────
const animating = reactive({})
const triggerAnimation = (id) => {
    animating[id] = true
    setTimeout(() => { animating[id] = false }, 300)
}

// ── Anti double-clic ───────────────────────────────────────────────────────
const disabledButtons = reactive({})
const disableButton = (id) => {
    disabledButtons[id] = true
    setTimeout(() => { disabledButtons[id] = false }, 900)
}

// ── Photo ────────────────────────────────────────────────────────────────
const getPhotoUrl = (c) => c.photo ? `/storage/${c.photo}` : null
const onImgError = (e) => { e.target.src = '/images/candidat-placeholder.png' }

// ── Recherche / filtre ──────────────────────────────────────────────────────
const search = ref('')
const filteredCandidates = computed(() => {
    if (!search.value.trim()) return props.candidates
    const q = search.value.trim().toLowerCase()
    return props.candidates.filter(c =>
        c.nom.toLowerCase().includes(q) ||
        String(c.ordre_affichage ?? c.id).includes(q)
    )
})

// ── Vote +1 / -1 classique ─────────────────────────────────────────────────
const vote = async (optionId, action) => {
    if (disabledButtons[optionId]) return
    disableButton(optionId)

    const delta = action === '+1' ? 1 : -1

    if (counts[optionId] + delta < 0) return
    counts[optionId] += delta
    triggerAnimation(optionId)

    try {
        const res = await axios.post('/operator/comptage/vote', {
            vote_option_id: optionId,
            action: action,
        })

        if (res.data.success) {
            counts[optionId] = res.data.count
        }
    } catch (e) {
        counts[optionId] -= delta

        if (e.response) {
            if (e.response.status === 422) {
                console.warn('Vote refusé :', e.response.data.message || e.response.data.error)
            } else if (e.response.status === 429) {
                // Double-clic, on ignore
            } else if (e.response.status === 403) {
                alert('Ce bureau est verrouillé.')
            } else {
                console.error('Erreur serveur', e.response.status)
            }
        } else {
            console.error('Erreur réseau :', e)
        }
    }
}

// ── Modale procuration (unique, partagée par toutes les cartes) ─────────────
const procurationModal = reactive({
    open: false,
    id: null,
    nom: '',
    quantity: null,
    loading: false,
    error: null,
})

const openProcurationModal = (c) => {
    procurationModal.open = true
    procurationModal.id = c.id
    procurationModal.nom = c.nom
    procurationModal.quantity = null
    procurationModal.error = null
}

const closeProcurationModal = () => {
    procurationModal.open = false
}

const submitProcuration = async () => {
    const qty = procurationModal.quantity

    if (!qty || qty < 1) {
        procurationModal.error = 'Entrez un nombre valide'
        return
    }

    procurationModal.loading = true
    procurationModal.error = null

    try {
        const res = await axios.post('/operator/comptage/vote-manuel', {
            vote_option_id: procurationModal.id,
            quantity: qty,
        })

        if (res.data.success) {
            counts[procurationModal.id] = res.data.count
            triggerAnimation(procurationModal.id)
            closeProcurationModal()
        }
    } catch (e) {
        if (e.response?.status === 422) {
            procurationModal.error = e.response.data.errors?.quantity?.[0]
                || e.response.data.message
                || 'Valeur invalide'
        } else if (e.response?.status === 403) {
            procurationModal.error = e.response.data.error || 'Ce bureau est verrouillé.'
        } else {
            procurationModal.error = 'Erreur lors de l\'enregistrement'
            console.error(e)
        }
    } finally {
        procurationModal.loading = false
    }
}

// ── Compteur de bulletins (indépendant des candidats) ──────────────────────
const bulletinCount = ref(props.bulletin_count)
const bulletinAnimating = ref(false)
const bulletinDisabled = ref(false)

const triggerBulletinAnimation = () => {
    bulletinAnimating.value = true
    setTimeout(() => { bulletinAnimating.value = false }, 300)
}

const voteBulletin = async (action) => {
    if (bulletinDisabled.value) return
    bulletinDisabled.value = true
    setTimeout(() => { bulletinDisabled.value = false }, 900)

    const delta = action === '+1' ? 1 : -1
    if (bulletinCount.value + delta < 0) return

    bulletinCount.value += delta
    triggerBulletinAnimation()

    try {
        const res = await axios.post('/operator/comptage/bulletin', { action })
        if (res.data.success) {
            bulletinCount.value = res.data.count
        }
    } catch (e) {
        bulletinCount.value -= delta

        if (e.response) {
            if (e.response.status === 422) {
                console.warn('Action refusée :', e.response.data.message || e.response.data.error)
            } else if (e.response.status === 429) {
                // Double-clic, on ignore
            } else if (e.response.status === 403) {
                alert('Ce bureau est verrouillé.')
            } else {
                console.error('Erreur serveur', e.response.status)
            }
        } else {
            console.error('Erreur réseau :', e)
        }
    }
}

// ── Modale saisie manuelle groupée du nombre de bulletins ──────────────────
const bulletinManuelModal = reactive({
    open: false,
    quantity: null,
    loading: false,
    error: null,
})

const openBulletinManuelModal = () => {
    bulletinManuelModal.open = true
    bulletinManuelModal.quantity = null
    bulletinManuelModal.error = null
}

const closeBulletinManuelModal = () => {
    bulletinManuelModal.open = false
}

const submitBulletinManuel = async () => {
    const qty = bulletinManuelModal.quantity

    if (!qty || qty < 1) {
        bulletinManuelModal.error = 'Entrez un nombre valide'
        return
    }

    bulletinManuelModal.loading = true
    bulletinManuelModal.error = null

    try {
        const res = await axios.post('/operator/comptage/bulletin-manuel', { quantity: qty })

        if (res.data.success) {
            bulletinCount.value = res.data.count
            triggerBulletinAnimation()
            closeBulletinManuelModal()
        }
    } catch (e) {
        if (e.response?.status === 422) {
            bulletinManuelModal.error = e.response.data.errors?.quantity?.[0]
                || e.response.data.message
                || 'Valeur invalide'
        } else if (e.response?.status === 403) {
            bulletinManuelModal.error = e.response.data.error || 'Ce bureau est verrouillé.'
        } else {
            bulletinManuelModal.error = 'Erreur lors de l\'enregistrement'
            console.error(e)
        }
    } finally {
        bulletinManuelModal.loading = false
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">
                    Comptage — {{ bureau.nom }}
                </h1>
                <span class="text-sm text-gray-500 font-mono">{{ bureau.code }}</span>
            </div>
        </template>

        <!-- Bureau verrouillé -->
        <div v-if="locked" class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center">
            <p class="text-amber-800 font-medium">
                🔒 Ce bureau n'est plus en phase de comptage (statut : {{ bureau.status }})
            </p>
        </div>

        <template v-else>

            <!-- ── Compteur de bulletins dépouillés ────────────────────── -->
            <div class="mb-6 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                            Bulletins dépouillés
                        </h2>
                        <p class="text-[11px] text-gray-400 max-w-xs">
                            1 bulletin peut contenir de 1 à 9 votes candidats. Ce compteur est indépendant des compteurs candidats.
                        </p>
                    </div>

                    <div class="flex items-center gap-4">
                        <div
                            class="text-4xl font-black text-amber-600 tabular-nums transition-transform duration-150"
                            :class="{ 'scale-110': bulletinAnimating }"
                        >
                            {{ bulletinCount }}
                        </div>

                        <div class="flex gap-2">
                            <button
                                @click="voteBulletin('-1')"
                                :disabled="bulletinDisabled || bulletinCount === 0"
                                class="bg-red-50 hover:bg-red-100 active:scale-95 text-red-600
                                       disabled:opacity-40 disabled:cursor-not-allowed
                                       font-bold w-11 h-11 rounded-xl text-lg leading-none
                                       transition-all duration-100 select-none"
                            >
                                −
                            </button>
                            <button
                                @click="voteBulletin('+1')"
                                :disabled="bulletinDisabled"
                                class="bg-amber-500 hover:bg-amber-600 active:scale-95
                                       disabled:opacity-50 disabled:cursor-not-allowed
                                       text-white font-bold w-11 h-11 rounded-xl text-lg leading-none
                                       transition-all duration-100 select-none"
                            >
                                +
                            </button>
                        </div>
                    </div>
                </div>

                <!-- <button
                    @click="openBulletinManuelModal"
                    class="text-[11px] text-amber-600 hover:text-amber-800 hover:underline font-medium mt-3"
                >
                    + Saisie groupée (par paquet)
                </button> -->
            </div>

            <!-- ── Candidats ────────────────────────────────────────── -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-3 gap-3 flex-wrap">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        Candidats <span class="text-gray-400 normal-case">({{ filteredCandidates.length }}/{{ candidates.length }})</span>
                    </h2>
                    <div class="relative w-full max-w-xs">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Rechercher un candidat..."
                            class="w-full text-sm pl-8 pr-3 py-1.5 border border-gray-300 rounded-lg
                                   focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                        <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5A6.5 6.5 0 114 10.5a6.5 6.5 0 0113 0z"/>
                        </svg>
                    </div>
                </div>

                <div v-if="filteredCandidates.length === 0" class="bg-white rounded-xl border border-gray-100 p-8 text-center text-sm text-gray-400">
                    Aucun candidat ne correspond à "{{ search }}"
                </div>

                <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                    <div
                        v-for="c in filteredCandidates" :key="c.id"
                        class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden flex flex-col"
                    >
                        <!-- Photo carrée dominante + badge numéro superposé -->
                        <div class="relative w-full aspect-square bg-gray-100">
                            <img
                                :src="getPhotoUrl(c) || '/images/candidat-placeholder.png'"
                                @error="onImgError"
                                :alt="c.nom"
                                class="w-full h-full object-cover"
                            />
                            <span class="absolute top-1.5 left-1.5 min-w-[24px] h-6 px-1.5 rounded-md bg-blue-600 text-white text-xs font-bold flex items-center justify-center shadow">
                                {{ c.ordre_affichage ?? c.id }}
                            </span>
                        </div>

                        <div class="p-2 flex flex-col items-center">
                            <!-- Nom -->
                            <div class="text-xs font-semibold text-gray-900 text-center leading-tight mb-1.5 line-clamp-2 h-8">
                                {{ c.nom }}
                            </div>

                            <!-- Compteur -->
                            <div
                                class="text-xl font-black text-blue-600 tabular-nums transition-transform duration-150 mb-1.5"
                                :class="{ 'scale-110': animating[c.id] }"
                            >
                                {{ counts[c.id] }}
                            </div>

                            <!-- Boutons +1 / -1 -->
                            <div class="flex gap-1 w-full mb-1">
                                <button
                                    @click="vote(c.id, '-1')"
                                    :disabled="disabledButtons[c.id] || counts[c.id] === 0"
                                    class="flex-1 bg-red-50 hover:bg-red-100 active:scale-95 text-red-600
                                           disabled:opacity-40 disabled:cursor-not-allowed
                                           font-bold py-1.5 rounded-md text-sm leading-none
                                           transition-all duration-100 select-none"
                                >
                                    −
                                </button>
                                <button
                                    @click="vote(c.id, '+1')"
                                    :disabled="disabledButtons[c.id]"
                                    class="flex-1 bg-green-600 hover:bg-green-700 active:scale-95
                                           disabled:opacity-50 disabled:cursor-not-allowed
                                           text-white font-bold py-1.5 rounded-md text-sm leading-none
                                           transition-all duration-100 select-none"
                                >
                                    +
                                </button>
                            </div>

                            <!-- Lien procuration discret -->
                            <button
                                @click="openProcurationModal(c)"
                                class="text-[10px] text-purple-500 hover:text-purple-700 hover:underline font-medium"
                            >
                                + Procuration
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Blanc / Nul ──────────────────────────────────────── -->
            <div>
                <h2 class="text-xs font-semibold text-gray-500 mb-3 uppercase tracking-wider">
                    Autres
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        v-for="c in blanc_nul" :key="c.id"
                        class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm"
                    >
                        <div class="flex items-center justify-between mb-4">
                            <div class="text-base font-bold text-gray-700">{{ c.nom }}</div>
                            <div
                                class="text-4xl font-black text-gray-500 tabular-nums transition-transform duration-150"
                                :class="{ 'scale-110': animating[c.id] }"
                            >
                                {{ counts[c.id] }}
                            </div>
                        </div>
                        <div class="flex gap-2 mb-3">
                            <button
                                @click="vote(c.id, '+1')"
                                :disabled="disabledButtons[c.id]"
                                class="flex-1 bg-green-600 hover:bg-green-700 active:scale-95
                                       disabled:opacity-50 disabled:cursor-not-allowed
                                       text-white font-bold py-3 rounded-xl
                                       transition-all duration-100 select-none"
                            >
                                +1
                            </button>
                            <button
                                @click="vote(c.id, '-1')"
                                :disabled="disabledButtons[c.id] || counts[c.id] === 0"
                                class="flex-1 bg-red-500 hover:bg-red-600 active:scale-95
                                       disabled:opacity-50 disabled:cursor-not-allowed
                                       text-white font-bold py-3 rounded-xl
                                       transition-all duration-100 select-none"
                            >
                                −1
                            </button>
                        </div>
                        <button
                            @click="openProcurationModal(c)"
                            class="text-xs text-purple-600 hover:text-purple-800 hover:underline font-medium"
                        >
                            + Ajouter des votes par procuration
                        </button>
                    </div>
                </div>
            </div>

        </template>

        <!-- ══ Modale Procuration ══════════════════════════════════════ -->
        <Teleport to="body">
            <div v-if="procurationModal.open"
                 @click.self="closeProcurationModal"
                 class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">
                    <div class="flex items-center justify-between mb-1">
                        <h3 class="text-base font-bold text-gray-900">Vote par procuration</h3>
                        <button @click="closeProcurationModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <p class="text-sm text-gray-500 mb-4">{{ procurationModal.nom }}</p>

                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de votes</label>
                    <input
                        v-model.number="procurationModal.quantity"
                        type="number"
                        min="1"
                        autofocus
                        placeholder="Ex: 12"
                        @keyup.enter="submitProcuration"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-lg font-semibold
                               focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    />
                    <p v-if="procurationModal.error" class="text-sm text-red-600 mt-1.5">
                        {{ procurationModal.error }}
                    </p>

                    <div class="bg-purple-50 border border-purple-100 rounded-lg px-3 py-2 mt-3">
                        <p class="text-xs text-purple-700 leading-snug">
                            Ce champ est réservé à la saisie manuelle des votes par procuration. Il sera enregistré séparément et tracé dans le journal d'audit.
                        </p>
                    </div>

                    <div class="flex gap-2 mt-5">
                        <button
                            @click="submitProcuration"
                            :disabled="procurationModal.loading || !procurationModal.quantity"
                            class="flex-1 bg-purple-600 hover:bg-purple-700 active:scale-95
                                   disabled:opacity-50 disabled:cursor-not-allowed
                                   text-white font-bold py-2.5 rounded-lg text-sm
                                   transition-all duration-100"
                        >
                            {{ procurationModal.loading ? 'Enregistrement...' : 'Valider' }}
                        </button>
                        <button
                            @click="closeProcurationModal"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2.5 px-4 rounded-lg text-sm"
                        >
                            Annuler
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ══ Modale Saisie groupée — Bulletins ══════════════════════════ -->
        <Teleport to="body">
            <div v-if="bulletinManuelModal.open"
                 @click.self="closeBulletinManuelModal"
                 class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">
                    <div class="flex items-center justify-between mb-1">
                        <h3 class="text-base font-bold text-gray-900">Saisie groupée — Bulletins</h3>
                        <button @click="closeBulletinManuelModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <p class="text-sm text-gray-500 mb-4">Ajouter un nombre de bulletins dépouillés en une seule saisie.</p>

                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de bulletins</label>
                    <input
                        v-model.number="bulletinManuelModal.quantity"
                        type="number"
                        min="1"
                        autofocus
                        placeholder="Ex: 50"
                        @keyup.enter="submitBulletinManuel"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-lg font-semibold
                               focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                    />
                    <p v-if="bulletinManuelModal.error" class="text-sm text-red-600 mt-1.5">
                        {{ bulletinManuelModal.error }}
                    </p>

                    <div class="bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 mt-3">
                        <p class="text-xs text-amber-700 leading-snug">
                            Cette saisie est tracée dans le journal d'audit comme un ajout groupé (distinct des clics unitaires).
                        </p>
                    </div>

                    <div class="flex gap-2 mt-5">
                        <button
                            @click="submitBulletinManuel"
                            :disabled="bulletinManuelModal.loading || !bulletinManuelModal.quantity"
                            class="flex-1 bg-amber-600 hover:bg-amber-700 active:scale-95
                                   disabled:opacity-50 disabled:cursor-not-allowed
                                   text-white font-bold py-2.5 rounded-lg text-sm
                                   transition-all duration-100"
                        >
                            {{ bulletinManuelModal.loading ? 'Enregistrement...' : 'Valider' }}
                        </button>
                        <button
                            @click="closeBulletinManuelModal"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2.5 px-4 rounded-lg text-sm"
                        >
                            Annuler
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>