<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
    modelValue: [String, Number, null],
    options: {
        type: Array,
        default: () => [], // [{ id, label }]
    },
    placeholder: {
        type: String,
        default: 'Tous',
    },
    searchPlaceholder: {
        type: String,
        default: 'Rechercher...',
    },
})

const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const search = ref('')
const root = ref(null)
const inputRef = ref(null)

const selected = computed(() =>
    props.options.find(o => String(o.id) === String(props.modelValue))
)

const filtered = computed(() => {
    if (!search.value.trim()) return props.options
    const q = search.value.toLowerCase()
    return props.options.filter(o => o.label.toLowerCase().includes(q))
})

function toggle() {
    open.value = !open.value
    if (open.value) {
        search.value = ''
        requestAnimationFrame(() => inputRef.value?.focus())
    }
}

function select(option) {
    emit('update:modelValue', option ? option.id : '')
    open.value = false
    search.value = ''
}

function onClickOutside(e) {
    if (root.value && !root.value.contains(e.target)) {
        open.value = false
    }
}

onMounted(() => document.addEventListener('click', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside))
</script>

<template>
    <div ref="root" class="relative">
        <button type="button" @click="toggle"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-left bg-white flex items-center justify-between hover:border-gray-400">
            <span :class="selected ? 'text-gray-900' : 'text-gray-400'" class="truncate">
                {{ selected ? selected.label : placeholder }}
            </span>
            <svg class="w-4 h-4 text-gray-400 flex-shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div v-if="open"
             class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-72 overflow-hidden flex flex-col">
            <input ref="inputRef" v-model="search" type="text" :placeholder="searchPlaceholder"
                   class="px-3 py-2 text-sm border-b border-gray-100 focus:outline-none"
                   @click.stop />

            <div class="overflow-y-auto">
                <div @click="select(null)"
                     class="px-3 py-2 text-sm text-gray-500 hover:bg-gray-50 cursor-pointer"
                     :class="!modelValue ? 'bg-blue-50 text-blue-700 font-medium' : ''">
                    {{ placeholder }}
                </div>
                <div v-for="o in filtered" :key="o.id" @click="select(o)"
                     class="px-3 py-2 text-sm hover:bg-gray-50 cursor-pointer"
                     :class="String(o.id) === String(modelValue) ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'">
                    {{ o.label }}
                </div>
                <div v-if="filtered.length === 0" class="px-3 py-2 text-sm text-gray-400">
                    Aucun résultat
                </div>
            </div>
        </div>
    </div>
</template>