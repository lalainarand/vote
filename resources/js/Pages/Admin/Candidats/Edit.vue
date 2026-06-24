<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
    candidat: Object,
})

const form = useForm({
    nom: props.candidat.nom,
    ordre_affichage: props.candidat.ordre_affichage,
})

const submit = () => {
    form.put(`/admin/candidats/${props.candidat.id}`)
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Modifier {{ candidat.nom }}</h1>
        </template>

        <div class="max-w-2xl mx-auto">
            <form @submit.prevent="submit" class="bg-white rounded-xl border border-gray-100 p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom du candidat</label>
                    <input v-model="form.nom" type="text"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           required />
                    <p v-if="form.errors.nom" class="text-red-600 text-sm mt-1">{{ form.errors.nom }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Numéro (ordre d'affichage)</label>
                    <input v-model.number="form.ordre_affichage" type="number" min="1"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           required />
                    <p v-if="form.errors.ordre_affichage" class="text-red-600 text-sm mt-1">{{ form.errors.ordre_affichage }}</p>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="submit"
                            :disabled="form.processing"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium disabled:opacity-50">
                        Mettre à jour
                    </button>
                    <Link :href="`/admin/candidats`"
                          class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg font-medium">
                        Annuler
                    </Link>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>