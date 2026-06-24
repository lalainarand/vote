<script setup>
import { computed } from 'vue'
import { usePage }  from '@inertiajs/vue3'

const flash = computed(() => usePage().props.flash ?? {})

const kind = computed(() => {
    if (flash.value.success) return 'success'
    if (flash.value.error)   return 'error'
    return null
})

const message = computed(() => flash.value.success ?? flash.value.error ?? null)
</script>

<template>
    <Transition name="slide-down">
        <div v-if="kind && message" class="notice" :class="`notice--${kind}`" role="alert">
            <svg v-if="kind === 'success'" viewBox="0 0 20 20" fill="currentColor" class="notice-icon">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
            </svg>
            <svg v-else viewBox="0 0 20 20" fill="currentColor" class="notice-icon">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/>
            </svg>
            {{ message }}
        </div>
    </Transition>
</template>

<style scoped>
.notice {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.8rem 1.25rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-family: 'Inter', system-ui, sans-serif;
    margin-bottom: 1.25rem;
    font-weight: 500;
}

.notice--success {
    background: rgba(5, 150, 105, 0.12);
    border: 1px solid rgba(5, 150, 105, 0.3);
    color: #34D399;
}
.notice--error {
    background: rgba(220, 38, 38, 0.12);
    border: 1px solid rgba(220, 38, 38, 0.3);
    color: #F87171;
}

.notice-icon { width: 1.1rem; height: 1.1rem; flex-shrink: 0; }

/* Transition */
.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.25s ease;
}
.slide-down-enter-from,
.slide-down-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>