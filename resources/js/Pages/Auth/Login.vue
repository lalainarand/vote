<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { useForm } from '@inertiajs/vue3'

const form = useForm({
    email:     '',
    password:  '',
    remember:  false,
})

const submit = () => form.post('/login', {
    onFinish: () => form.reset('password'),
})
</script>

<template>
    <GuestLayout>
        <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-900">Connexion</h2>
            <p class="text-sm text-gray-500 mt-1">Accédez à votre espace sécurisé</p>
        </div>

        <!-- Erreur globale -->
        <div v-if="form.errors.email" class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg">
            {{ form.errors.email }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <!-- Email -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Adresse email</label>
                <input
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    required
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm
                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                           placeholder:text-gray-400 transition"
                    :class="{ 'border-red-400 bg-red-50': form.errors.email }"
                    placeholder="admin@exemple.mg"
                />
            </div>

            <!-- Mot de passe -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Mot de passe</label>
                <input
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm
                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                           placeholder:text-gray-400 transition"
                    :class="{ 'border-red-400 bg-red-50': form.errors.password }"
                    placeholder="••••••••"
                />
                <p v-if="form.errors.password" class="text-red-500 text-xs mt-1">{{ form.errors.password }}</p>
            </div>

            <!-- Remember -->
            <div class="flex items-center gap-2">
                <input
                    id="remember"
                    v-model="form.remember"
                    type="checkbox"
                    class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                />
                <label for="remember" class="text-sm text-gray-600">Rester connecté</label>
            </div>

            <!-- Submit -->
            <button
                type="submit"
                :disabled="form.processing"
                class="w-full bg-blue-700 hover:bg-blue-800 disabled:opacity-60 disabled:cursor-not-allowed
                       text-white font-semibold py-2.5 px-4 rounded-lg text-sm transition-colors mt-2
                       flex items-center justify-center gap-2"
            >
                <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                {{ form.processing ? 'Connexion…' : 'Se connecter' }}
            </button>
        </form>
    </GuestLayout>
</template>