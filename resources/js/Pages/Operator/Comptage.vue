<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import axios from 'axios'
import { ref, reactive } from 'vue'

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

// ── Anti double-clic : 500ms par option ───────────────────────────────────
const lastClick = ref({})
const debounced = (id) => {
    const last = lastClick.value[id] ?? 0
    return Date.now() - last < 500
}

// ── Animation compteur ────────────────────────────────────────────────────
const animating = reactive({})
const triggerAnimation = (id) => {
    animating[id] = true
    setTimeout(() => { animating[id] = false }, 300)
}

// ── Appel via axios (CSRF automatique) ────────────────────────────────────
const vote = async (optionId, action) => {
    if (debounced(optionId)) return
    lastClick.value[optionId] = Date.now()

    const delta = action === '+1' ? 1 : -1

    // Optimistic UI
    if (counts[optionId] + delta < 0) return
    counts[optionId] += delta
    triggerAnimation(optionId)

    try {
        // ✅ axios gère automatiquement le CSRF via le cookie XSRF-TOKEN
        const res = await axios.post('/operator/comptage/vote', {
            vote_option_id: optionId,
            action: action,
        })

        if (res.data.success) {
            // Synchroniser avec la valeur serveur
            counts[optionId] = res.data.count
        }
    } catch (e) {
        // Rollback
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
                    Candidats
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="c in candidates" :key="c.id"
                        class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm"
                    >
                        <div class="flex items-start justify-between mb-5">
                            <div>
                                <div class="text-xs text-gray-400 mb-0.5">N° {{ c.ordre_affichage ?? c.id }}</div>
                                <div class="text-base font-bold text-gray-900 leading-tight">{{ c.nom }}</div>
                            </div>
                            <div
                                class="text-5xl font-black text-blue-600 tabular-nums transition-transform duration-150"
                                :class="{ 'scale-110': animating[c.id] }"
                            >
                                {{ counts[c.id] }}
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button
                                @click="vote(c.id, '+1')"
                                :disabled="debounced(c.id)"
                                class="flex-1 bg-green-600 hover:bg-green-700 active:scale-95
                                       disabled:opacity-50 disabled:cursor-not-allowed
                                       text-white font-bold py-4 rounded-xl text-xl
                                       transition-all duration-100 select-none"
                            >
                                +1
                            </button>
                            <button
                                @click="vote(c.id, '-1')"
                                :disabled="debounced(c.id) || counts[c.id] === 0"
                                class="flex-1 bg-red-500 hover:bg-red-600 active:scale-95
                                       disabled:opacity-50 disabled:cursor-not-allowed
                                       text-white font-bold py-4 rounded-xl text-xl
                                       transition-all duration-100 select-none"
                            >
                                −1
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
                        <div class="flex gap-2">
                            <button
                                @click="vote(c.id, '+1')"
                                :disabled="debounced(c.id)"
                                class="flex-1 bg-green-600 hover:bg-green-700 active:scale-95
                                       disabled:opacity-50 disabled:cursor-not-allowed
                                       text-white font-bold py-3 rounded-xl
                                       transition-all duration-100 select-none"
                            >
                                +1
                            </button>
                            <button
                                @click="vote(c.id, '-1')"
                                :disabled="debounced(c.id) || counts[c.id] === 0"
                                class="flex-1 bg-red-500 hover:bg-red-600 active:scale-95
                                       disabled:opacity-50 disabled:cursor-not-allowed
                                       text-white font-bold py-3 rounded-xl
                                       transition-all duration-100 select-none"
                            >
                                −1
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </template>
    </AuthenticatedLayout>
</template>