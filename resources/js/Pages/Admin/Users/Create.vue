<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { watch } from 'vue'

const props = defineProps({
    available_bureaux: Array,
})

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'operator',
    bureau_vote_id: null,
})

// Si on passe en admin, on retire le bureau
watch(() => form.role, (newRole) => {
    if (newRole === 'admin') {
        form.bureau_vote_id = null
    }
})

const submit = () => {
    form.post('/admin/users')
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Nouvel utilisateur</h1>
        </template>

        <div class="max-w-2xl mx-auto">
            <form @submit.prevent="submit" class="bg-white rounded-xl border border-gray-100 p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom complet</label>
                    <input v-model="form.name" type="text" placeholder="Ex: RAKOTO Jean"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           required />
                    <p v-if="form.errors.name" class="text-red-600 text-sm mt-1">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input v-model="form.email" type="email" placeholder="operateur@eglise.mg"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           required />
                    <p v-if="form.errors.email" class="text-red-600 text-sm mt-1">{{ form.errors.email }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                        <input v-model="form.password" type="password" placeholder="Minimum 8 caractères"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               required />
                        <p v-if="form.errors.password" class="text-red-600 text-sm mt-1">{{ form.errors.password }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirmation</label>
                        <input v-model="form.password_confirmation" type="password"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               required />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rôle</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition-colors"
                               :class="form.role === 'operator' ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-gray-300'">
                            <input v-model="form.role" type="radio" value="operator" class="text-green-600 focus:ring-green-500" />
                            <div>
                                <div class="text-sm font-medium text-gray-900">Opérateur</div>
                                <div class="text-xs text-gray-500">Comptage dans un bureau</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition-colors"
                               :class="form.role === 'admin' ? 'border-amber-500 bg-amber-50' : 'border-gray-200 hover:border-gray-300'">
                            <input v-model="form.role" type="radio" value="admin" class="text-amber-600 focus:ring-amber-500" />
                            <div>
                                <div class="text-sm font-medium text-gray-900">Administrateur</div>
                                <div class="text-xs text-gray-500">Accès complet</div>
                            </div>
                        </label>
                    </div>
                    <p v-if="form.errors.role" class="text-red-600 text-sm mt-1">{{ form.errors.role }}</p>
                </div>

                <!-- Bureau (uniquement si operator) -->
                <div v-if="form.role === 'operator'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bureau assigné</label>
                    <select v-model="form.bureau_vote_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            :required="form.role === 'operator'">
                        <option :value="null" disabled>-- Sélectionner un bureau --</option>
                        <option v-for="b in available_bureaux" :key="b.id" :value="b.id">
                            {{ b.code }} — {{ b.nom }}
                        </option>
                    </select>
                    <p v-if="form.errors.bureau_vote_id" class="text-red-600 text-sm mt-1">{{ form.errors.bureau_vote_id }}</p>
                    <p v-if="available_bureaux.length === 0" class="text-amber-600 text-xs mt-1">
                        ⚠️ Tous les bureaux ont déjà un opérateur assigné
                    </p>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="submit"
                            :disabled="form.processing"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium disabled:opacity-50">
                        Créer
                    </button>
                    <Link :href="`/admin/users`"
                          class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg font-medium">
                        Annuler
                    </Link>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>