<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
    candidates: Array,
})

const deleteCandidate = (id, name) => {
    if (confirm(`Supprimer le candidat "${name}" ?`)) {
        router.delete(`/admin/candidats/${id}`)
    }
}

const getPhotoUrl = (c) => {
    return c.photo ? `/storage/${c.photo}` : null
}

// si l'image ne charge pas (fichier supprimé/corrompu), on bascule sur le placeholder
const onImgError = (e) => {
    e.target.src = '/images/candidat-placeholder.png'
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">Candidats</h1>
                <Link :href="`/admin/candidats/create`"
                      class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    + Nouveau candidat
                </Link>
            </div>
        </template>

        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">N°</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Photo</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Nom</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Votes enregistrés</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="candidates.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">
                            Aucun candidat créé
                        </td>
                    </tr>
                    <tr v-for="c in candidates" :key="c.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-blue-100 text-blue-700 font-bold text-sm">
                                {{ c.ordre_affichage }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <img
                                :src="getPhotoUrl(c) || '/images/candidat-placeholder.png'"
                                @error="onImgError"
                                :alt="c.nom"
                                class="w-10 h-10 rounded-full object-cover border border-gray-200 mx-auto"
                            />
                        </td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ c.nom }}</td>
                        <td class="px-4 py-3 text-center">
                            <span :class="c.vote_logs_count > 0 ? 'text-orange-600' : 'text-gray-400'"
                                  class="text-sm font-semibold">
                                {{ c.vote_logs_count }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-3">
                            <Link :href="`/admin/candidats/${c.id}/edit`"
                                  class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                Modifier
                            </Link>
                            <button v-if="c.vote_logs_count === 0"
                                    @click="deleteCandidate(c.id, c.nom)"
                                    class="text-red-600 hover:text-red-800 text-sm font-medium">
                                Supprimer
                            </button>
                            <span v-else class="text-xs text-gray-400 italic" title="Votes existants">
                                🔒
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
            <strong>Note :</strong> Un candidat ayant déjà des votes enregistrés ne peut plus être supprimé (traçabilité).
        </div>
    </AuthenticatedLayout>
</template>