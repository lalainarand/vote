<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { router } from '@inertiajs/vue3'

defineProps({
    name: String,
})

const refresh = () => router.reload()
const logout = () => router.post('/logout')
</script>

<template>
    <GuestLayout>
        <div class="text-center">
            <div class="mx-auto mb-4 w-14 h-14 rounded-full bg-amber-100 flex items-center justify-center">
                <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>

            <h2 class="text-lg font-bold text-gray-900">Bonjour {{ name }}</h2>
            <p class="text-sm text-gray-500 mt-1">
                Votre compte a été créé mais n'a pas encore été autorisé par un administrateur.
            </p>

            <div class="mt-5 inline-flex items-center gap-2 bg-amber-50 border border-amber-200 text-amber-800 text-sm font-medium px-4 py-2 rounded-full">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                Statut : En attente
            </div>

            <p class="text-xs text-gray-400 mt-4">
                Revenez sur cette page une fois l'accès autorisé, ou vérifiez à nouveau.
            </p>

            <div class="flex gap-3 mt-6">
                <button @click="refresh"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg text-sm transition-colors">
                    Vérifier à nouveau
                </button>
                <button @click="logout"
                        class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2.5 rounded-lg text-sm transition-colors">
                    Se déconnecter
                </button>
            </div>
        </div>
    </GuestLayout>
</template>
