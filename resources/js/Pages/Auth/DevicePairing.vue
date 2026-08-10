<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Link } from '@inertiajs/vue3'

defineProps({
    success: Boolean,
    message: String,
})
</script>

<template>
    <GuestLayout>
        <div class="text-center">
            <div class="mx-auto mb-4 w-14 h-14 rounded-full flex items-center justify-center"
                 :class="success ? 'bg-green-100' : 'bg-red-100'">
                <svg v-if="success" class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <svg v-else class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
            </div>

            <h2 class="text-lg font-bold text-gray-900">
                {{ success ? 'Appareil enregistré' : "Échec de l'appairage" }}
            </h2>
            <p class="text-sm text-gray-500 mt-1">{{ message }}</p>

            <Link v-if="success" href="/login"
                  class="inline-block mt-6 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-lg text-sm transition-colors">
                Aller à la connexion
            </Link>
        </div>
    </GuestLayout>
</template>
