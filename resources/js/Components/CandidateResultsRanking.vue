<script setup>
import { computed } from 'vue'

const props = defineProps({
    // [{ id, nom, numero, photo, value, system_count, procuration, pv_count, ecart }] déjà trié par value desc
    items: { type: Array, default: () => [] },
    totalActif: { type: Number, default: 0 },
    topCount: { type: Number, default: 9 },
    unit: { type: String, default: 'voix' },
})

const maxValue = computed(() =>
    Math.max(1, ...props.items.map(i => i.value || 0))
)

const widthPct = (value) => Math.max(0, Math.min(100, ((value || 0) / maxValue.value) * 100))
const pct = (value) => props.totalActif > 0 ? ((value || 0) / props.totalActif) * 100 : null

const getPhotoUrl = (item) => item.photo ? `/storage/${item.photo}` : null
const onImgError = (e) => { e.target.src = '/images/candidat-placeholder.png' }

const rankBadgeClass = (rank) => {
    if (rank === 1) return 'bg-gradient-to-br from-amber-400 to-amber-600 text-white shadow-sm'
    if (rank === 2) return 'bg-gradient-to-br from-gray-300 to-gray-400 text-white shadow-sm'
    if (rank === 3) return 'bg-gradient-to-br from-orange-400 to-orange-600 text-white shadow-sm'
    if (rank <= props.topCount) return 'bg-green-600 text-white'
    return 'bg-gray-100 text-gray-400'
}
</script>

<template>
    <div v-if="items.length === 0" class="text-sm text-gray-400 text-center py-6">
        Aucune donnée à afficher
    </div>

    <div v-else class="divide-y divide-gray-50">
        <template v-for="(item, idx) in items" :key="item.id">
            <!-- Séparateur au passage sous la barre des sièges -->
            <div v-if="idx === topCount"
                 class="px-4 py-2 bg-gray-50 text-[11px] font-semibold text-gray-400 uppercase tracking-wide text-center">
                Non élus
            </div>

            <div class="px-4 py-3 hover:bg-gray-50/70 transition-colors" :class="{ 'bg-green-50/30': idx < topCount }">
                <div class="flex items-center gap-3">
                    <!-- Rang -->
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold shrink-0"
                         :class="rankBadgeClass(idx + 1)">
                        {{ idx + 1 }}
                    </div>

                    <!-- Photo -->
                    <img :src="getPhotoUrl(item) || '/images/candidat-placeholder.png'"
                         @error="onImgError"
                         :alt="item.nom"
                         class="w-9 h-9 rounded-full object-cover border border-gray-200 shrink-0" />

                    <!-- Identité -->
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-semibold text-gray-900 truncate">{{ item.nom }}</span>
                            <span v-if="item.numero" class="text-xs font-mono text-gray-400">N°{{ item.numero }}</span>
                            <span v-if="idx < topCount"
                                  class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-green-100 text-green-700">
                                Élu
                            </span>
                        </div>
                        <div class="text-[11px] text-gray-400 font-mono mt-0.5">
                            Système {{ (item.system_count || 0).toLocaleString('fr-FR') }}<template v-if="item.procuration > 0"> (dont {{ item.procuration.toLocaleString('fr-FR') }} proc.)</template>
                            · PV {{ (item.pv_count || 0).toLocaleString('fr-FR') }}
                            · Écart
                            <span :class="{
                                'text-green-600': item.ecart === 0,
                                'text-amber-600': item.ecart > 0,
                                'text-red-600':   item.ecart < 0,
                            }">{{ item.ecart > 0 ? '+' : '' }}{{ item.ecart || 0 }}</span>
                        </div>
                    </div>

                    <!-- Valeur -->
                    <div class="text-right shrink-0">
                        <div class="text-sm font-mono font-bold text-gray-900">{{ (item.value || 0).toLocaleString('fr-FR') }} {{ unit }}</div>
                        <div class="text-xs text-gray-400">
                            <span v-if="pct(item.value) !== null">{{ pct(item.value).toFixed(1) }}%</span>
                            <span v-else>—</span>
                        </div>
                    </div>
                </div>

                <!-- Barre -->
                <div class="h-2 bg-gray-100 rounded-full overflow-hidden mt-2 ml-11">
                    <div class="h-full rounded-full transition-all duration-500"
                         :class="idx < topCount ? 'bg-blue-600' : 'bg-gray-300'"
                         :style="`width:${widthPct(item.value)}%`">
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
