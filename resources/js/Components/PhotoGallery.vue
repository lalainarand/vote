<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    images: {
        type: Array,
        default: () => [],
    },
    emptyLabel: {
        type: String,
        default: 'Aucune photo enregistrée pour ce bureau.',
    },
})

const zoomIndex = ref(null)
const zoomOpen = computed(() => zoomIndex.value !== null)
const zoomImage = computed(() =>
    zoomIndex.value !== null ? props.images[zoomIndex.value] : null
)

const openZoom = (index) => { zoomIndex.value = index }
const closeZoom = () => { zoomIndex.value = null }

const nextZoom = () => {
    if (zoomIndex.value === null) return
    zoomIndex.value = (zoomIndex.value + 1) % props.images.length
}
const prevZoom = () => {
    if (zoomIndex.value === null) return
    zoomIndex.value = (zoomIndex.value - 1 + props.images.length) % props.images.length
}

const handleKeydown = (e) => {
    if (!zoomOpen.value) return
    if (e.key === 'Escape') closeZoom()
    else if (e.key === 'ArrowRight') nextZoom()
    else if (e.key === 'ArrowLeft') prevZoom()
}

onMounted(() => window.addEventListener('keydown', handleKeydown))
onUnmounted(() => window.removeEventListener('keydown', handleKeydown))
</script>

<template>
    <div>
        <div v-if="images.length === 0" class="text-center text-sm text-gray-400 py-6">
            {{ emptyLabel }}
        </div>

        <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
            <button
                v-for="(img, index) in images" :key="img.id"
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

        <!-- ══ ZOOM PHOTO (lightbox) ══════════════════════════════════════ -->
        <Teleport to="body">
            <div v-if="zoomOpen"
                 @click.self="closeZoom"
                 class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/85">

                <button @click="closeZoom"
                        class="absolute top-4 right-4 text-white/80 hover:text-white bg-white/10 hover:bg-white/20 p-2.5 rounded-full transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                <button v-if="images.length > 1" @click.stop="prevZoom"
                        class="absolute left-4 top-1/2 -translate-y-1/2 text-white/80 hover:text-white bg-white/10 hover:bg-white/20 p-3 rounded-full transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>

                <button v-if="images.length > 1" @click.stop="nextZoom"
                        class="absolute right-4 top-1/2 -translate-y-1/2 text-white/80 hover:text-white bg-white/10 hover:bg-white/20 p-3 rounded-full transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>

                <div class="max-w-[92vw] max-h-[85vh] flex flex-col items-center gap-3" @click.stop>
                    <img :src="zoomImage?.url" :alt="zoomImage?.filename"
                         class="max-w-full max-h-[75vh] object-contain rounded-lg shadow-2xl" />
                    <div class="flex items-center gap-3 text-white/80 text-sm font-mono">
                        <span>{{ zoomImage?.taken_at }}</span>
                        <span v-if="zoomImage?.user" class="text-white/50">— {{ zoomImage.user }}</span>
                        <span v-if="images.length > 1" class="text-white/50">
                            {{ zoomIndex + 1 }} / {{ images.length }}
                        </span>
                        <a :href="zoomImage?.url" download
                           class="text-blue-300 hover:text-blue-200 underline">
                            Télécharger
                        </a>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>