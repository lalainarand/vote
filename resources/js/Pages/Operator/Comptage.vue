<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import axios from 'axios'
import { reactive } from 'vue'

const props = defineProps({
    bureau:     Object,
    locked:     Boolean,
    candidates: Array,
    blanc_nul:  Array,
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

// ── Saisie manuelle (procuration) ───────────────────────────────────────────
const procurationInputs = reactive({})
const procurationLoading = reactive({})
const procurationErrors = reactive({})

const submitProcuration = async (optionId) => {
    const qty = procurationInputs[optionId]

    if (!qty || qty < 1) {
        procurationErrors[optionId] = 'Entrez un nombre valide'
        return
    }

    if (procurationLoading[optionId]) return
    procurationLoading[optionId] = true
    procurationErrors[optionId] = null

    try {
        const res = await axios.post('/operator/comptage/vote-manuel', {
            vote_option_id: optionId,
            quantity: qty,
        })

        if (res.data.success) {
            counts[optionId] = res.data.count
            triggerAnimation(optionId)
            procurationInputs[optionId] = null
        }
    } catch (e) {
        if (e.response?.status === 422) {
            procurationErrors[optionId] = e.response.data.errors?.quantity?.[0]
                || e.response.data.message
                || 'Valeur invalide'
        } else if (e.response?.status === 403) {
            alert(e.response.data.error || 'Ce bureau est verrouillé.')
        } else {
            procurationErrors[optionId] = 'Erreur lors de l\'enregistrement'
            console.error(e)
        }
    } finally {
        procurationLoading[optionId] = false
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

            <!-- ── Candidats ────────────────────────────────────────── -->
            <div class="mb-6">
                <h2 class="text-xs font-semibold text-gray-500 mb-3 uppercase tracking-wider">
                    Candidats <span class="text-gray-400 normal-case">({{ candidates.length }})</span>
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                    <div
                        v-for="c in candidates" :key="c.id"
                        class="bg-white rounded-xl border border-gray-100 p-3 shadow-sm flex flex-col items-center"
                    >
                        <!-- Photo + badge numéro superposé -->
                        <div class="relative mb-2">
                            <img
                                :src="getPhotoUrl(c) || '/images/candidat-placeholder.png'"
                                @error="onImgError"
                                :alt="c.nom"
                                class="w-16 h-16 rounded-full object-cover border border-gray-200"
                            />
                            <span class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full bg-blue-600 text-white text-[11px] font-bold flex items-center justify-center border-2 border-white">
                                {{ c.ordre_affichage ?? c.id }}
                            </span>
                        </div>

                        <!-- Nom -->
                        <div class="text-xs font-semibold text-gray-900 text-center leading-tight mb-2 line-clamp-2 h-8">
                            {{ c.nom }}
                        </div>

                        <!-- Compteur -->
                        <div
                            class="text-2xl font-black text-blue-600 tabular-nums transition-transform duration-150 mb-2"
                            :class="{ 'scale-110': animating[c.id] }"
                        >
                            {{ counts[c.id] }}
                        </div>

                        <!-- Boutons +1 / -1 -->
                        <div class="flex gap-1.5 w-full mb-2">
                            <button
                                @click="vote(c.id, '+1')"
                                :disabled="disabledButtons[c.id]"
                                class="flex-1 bg-green-600 hover:bg-green-700 active:scale-95
                                       disabled:opacity-50 disabled:cursor-not-allowed
                                       text-white font-bold py-2 rounded-lg text-sm
                                       transition-all duration-100 select-none"
                            >
                                +1
                            </button>
                            <button
                                @click="vote(c.id, '-1')"
                                :disabled="disabledButtons[c.id] || counts[c.id] === 0"
                                class="flex-1 bg-red-500 hover:bg-red-600 active:scale-95
                                       disabled:opacity-50 disabled:cursor-not-allowed
                                       text-white font-bold py-2 rounded-lg text-sm
                                       transition-all duration-100 select-none"
                            >
                                −1
                            </button>
                        </div>

                        <!-- ── Saisie manuelle / Procuration ────────────────── -->
                        <div class="w-full pt-2 border-t border-gray-100">
                            <div class="flex gap-1.5">
                                <input
                                    v-model.number="procurationInputs[c.id]"
                                    type="number"
                                    min="1"
                                    placeholder="Nb"
                                    class="w-full min-w-0 text-xs px-2 py-1.5 border border-gray-300 rounded-lg
                                           focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                />
                                <button
                                    @click="submitProcuration(c.id)"
                                    :disabled="procurationLoading[c.id] || !procurationInputs[c.id]"
                                    class="bg-purple-600 hover:bg-purple-700 active:scale-95
                                           disabled:opacity-50 disabled:cursor-not-allowed
                                           text-white text-xs font-bold px-2.5 py-1.5 rounded-lg
                                           whitespace-nowrap transition-all duration-100 select-none"
                                >
                                    Valider
                                </button>
                            </div>
                            <p v-if="procurationErrors[c.id]" class="text-[10px] text-red-600 mt-1">
                                {{ procurationErrors[c.id] }}
                            </p>
                            <p class="text-[10px] text-gray-400 mt-1 leading-tight">
                                Saisie manuelle réservée aux votes par procuration
                            </p>
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

                        <!-- ── Saisie manuelle / Procuration ────────────────── -->
                        <div class="pt-3 border-t border-gray-100">
                            <div class="flex gap-2">
                                <input
                                    v-model.number="procurationInputs[c.id]"
                                    type="number"
                                    min="1"
                                    placeholder="Nombre de votes"
                                    class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg
                                           focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                />
                                <button
                                    @click="submitProcuration(c.id)"
                                    :disabled="procurationLoading[c.id] || !procurationInputs[c.id]"
                                    class="bg-purple-600 hover:bg-purple-700 active:scale-95
                                           disabled:opacity-50 disabled:cursor-not-allowed
                                           text-white text-sm font-bold px-4 py-2 rounded-lg
                                           whitespace-nowrap transition-all duration-100 select-none"
                                >
                                    Valider
                                </button>
                            </div>
                            <p v-if="procurationErrors[c.id]" class="text-xs text-red-600 mt-1">
                                {{ procurationErrors[c.id] }}
                            </p>
                            <p class="text-xs text-gray-400 mt-1">
                                Saisie manuelle réservée aux votes par procuration
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </template>
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