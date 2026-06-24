<script setup>
// Layout minimaliste pour les pages non authentifiées (login)
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-950 via-blue-900 to-indigo-900 flex items-center justify-center p-4">

        <!-- Fond décoratif -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-40 -right-40 w-80 h-80 bg-blue-500/10 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-indigo-500/10 rounded-full blur-3xl"></div>
        </div>

        <div class="relative w-full max-w-md">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-white tracking-tight">Système de comptage des votes</h1>
            </div>

            <!-- Carte -->
            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <slot />
            </div>

            <p class="text-center text-blue-300/60 text-xs mt-6">
                © {{ new Date().getFullYear() }} — Système sécurisé
            </p>
        </div>
    </div>
</template>