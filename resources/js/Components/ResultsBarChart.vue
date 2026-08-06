<script setup>
import { computed } from 'vue'

const props = defineProps({
    // [{ label, value, sub }]
    items: { type: Array, default: () => [] },
    unit: { type: String, default: 'voix' },
})

const maxValue = computed(() =>
    Math.max(1, ...props.items.map(i => i.value || 0))
)

const widthPct = (value) => Math.max(0, Math.min(100, ((value || 0) / maxValue.value) * 100))
</script>

<template>
    <div class="space-y-3">
        <div v-if="items.length === 0" class="text-sm text-gray-400 text-center py-6">
            Aucune donnée à afficher
        </div>

        <div v-for="item in items" :key="item.label" class="group">
            <div class="flex items-center justify-between mb-1 gap-2">
                <span class="text-xs font-medium text-gray-700 truncate">{{ item.label }}</span>
                <span class="text-xs font-mono font-semibold text-gray-900 tabular-nums shrink-0">
                    {{ (item.value || 0).toLocaleString('fr-FR') }} {{ unit }}
                </span>
            </div>
            <div class="h-5 bg-gray-100 rounded-full overflow-hidden" :title="`${item.label} : ${(item.value || 0).toLocaleString('fr-FR')} ${unit}`">
                <div
                    class="h-full bg-blue-600 rounded-full transition-all duration-500 group-hover:bg-blue-700"
                    :style="`width:${widthPct(item.value)}%`"
                ></div>
            </div>
        </div>
    </div>
</template>
