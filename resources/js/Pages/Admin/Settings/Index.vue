<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    settings: Array,
})

// ── CRUD paramètres ──────────────────────────────────────────────────────
const createForm = useForm({ key: '', value: '', label: '', description: '' })
const showCreate = ref(false)
const submitCreate = () => {
    createForm.post('/admin/settings', {
        onSuccess: () => { createForm.reset(); showCreate.value = false }
    })
}

const editingId = ref(null)
const editForm = useForm({ key: '', value: '', label: '', description: '' })
const startEdit = (s) => {
    editingId.value = s.id
    editForm.key = s.key
    editForm.value = s.value
    editForm.label = s.label
    editForm.description = s.description
}
const cancelEdit = () => { editingId.value = null }
const submitEdit = (s) => {
    editForm.put(`/admin/settings/${s.id}`, {
        onSuccess: () => { editingId.value = null }
    })
}

const destroySetting = (s) => {
    if (confirm(`Supprimer le paramètre "${s.label || s.key}" ?`)) {
        router.delete(`/admin/settings/${s.id}`)
    }
}

// ── Réinitialisation complète de la base de données ─────────────────────
// Le mot-clé réservé n'est JAMAIS affiché ni codé en dur ici (le JS livré au
// navigateur est toujours inspectable) : seul le serveur le connaît et le
// valide (voir SettingController::resetDatabase).
const showResetModal = ref(false)
const resetForm = useForm({ password: '', keyword: '' })

const openResetModal = () => {
    resetForm.reset()
    resetForm.clearErrors()
    showResetModal.value = true
}
const closeResetModal = () => { showResetModal.value = false }

const submitReset = () => {
    if (!confirm('Dernière confirmation : TOUTES les données électorales (bureaux, votes, bulletins, appareils autorisés...) seront supprimées et remplacées par les données de démo du seeder. Seul votre compte admin est préservé. Continuer ?')) {
        return
    }
    resetForm.post('/admin/settings/reset-database', {
        onSuccess: () => { showResetModal.value = false }
    })
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Paramètres</h1>
        </template>

        <!-- ══ Action 1 : Paramètres généraux (CRUD) ══════════════════════ -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden mb-6">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">Paramètres généraux</h2>
                <button @click="showCreate = !showCreate"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                    + Nouveau paramètre
                </button>
            </div>

            <form v-if="showCreate" @submit.prevent="submitCreate" class="p-4 border-b border-gray-100 bg-gray-50 grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Clé (identifiant technique)</label>
                    <input v-model="createForm.key" type="text" placeholder="ex: max_procuration"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono" />
                    <p v-if="createForm.errors.key" class="text-red-600 text-xs mt-1">{{ createForm.errors.key }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Valeur</label>
                    <input v-model="createForm.value" type="text"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
                    <p v-if="createForm.errors.value" class="text-red-600 text-xs mt-1">{{ createForm.errors.value }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Libellé</label>
                    <input v-model="createForm.label" type="text" placeholder="Nom affiché"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                    <input v-model="createForm.description" type="text"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
                </div>
                <div class="col-span-2 flex gap-2">
                    <button type="submit" :disabled="createForm.processing"
                            class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        Créer
                    </button>
                    <button type="button" @click="showCreate = false"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">
                        Annuler
                    </button>
                </div>
            </form>

            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">Paramètre</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">Clé</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">Valeur</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="settings.length === 0">
                        <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400">Aucun paramètre</td>
                    </tr>
                    <template v-for="s in settings" :key="s.id">
                        <tr v-if="editingId !== s.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <div class="font-medium">{{ s.label || '—' }}</div>
                                <div v-if="s.description" class="text-xs text-gray-400">{{ s.description }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs font-mono text-gray-500">{{ s.key }}</td>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-800">{{ s.value }}</td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <button @click="startEdit(s)" class="text-blue-600 hover:text-blue-800 text-xs font-semibold">Modifier</button>
                                <button @click="destroySetting(s)" class="text-red-600 hover:text-red-800 text-xs font-semibold">Supprimer</button>
                            </td>
                        </tr>
                        <tr v-else class="bg-blue-50/50">
                            <td colspan="4" class="px-4 py-3">
                                <form @submit.prevent="submitEdit(s)" class="grid grid-cols-4 gap-2 items-end">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Libellé</label>
                                        <input v-model="editForm.label" type="text" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Valeur</label>
                                        <input v-model="editForm.value" type="text" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm" />
                                        <p v-if="editForm.errors.value" class="text-red-600 text-xs mt-1">{{ editForm.errors.value }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                                        <input v-model="editForm.description" type="text" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm" />
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" :disabled="editForm.processing"
                                                class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white px-3 py-1.5 rounded text-xs font-medium">
                                            Enregistrer
                                        </button>
                                        <button type="button" @click="cancelEdit"
                                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded text-xs font-medium">
                                            Annuler
                                        </button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- ══ Action 2 : Réinitialisation complète de la base de données ═ -->
        <div class="bg-white rounded-xl border border-red-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-red-100 bg-red-50">
                <h2 class="text-sm font-semibold text-red-800">⚠️ Zone dangereuse</h2>
            </div>
            <div class="p-4">
                <h3 class="text-sm font-semibold text-gray-900 mb-1">Réinitialiser la base de données</h3>
                <p class="text-sm text-gray-500 mb-3">
                    Supprime <strong>toutes</strong> les données électorales (bureaux, candidats, votes, bulletins,
                    photos, appareils autorisés, rôles/permissions...) et les recrée à partir du seeder de démo
                    (rôles, candidats, bureaux, opérateurs). <strong>Seul votre compte administrateur est préservé</strong> —
                    tous les autres comptes (opérateurs) sont supprimés puis recréés par le seeder.
                </p>
                <p class="text-xs text-red-600 font-medium mb-4">
                    Action irréversible. À utiliser uniquement pour repartir sur une nouvelle élection ou un
                    environnement de test propre.
                </p>
                <button @click="openResetModal"
                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    Réinitialiser la base de données…
                </button>
            </div>
        </div>

        <!-- ══ Modale de confirmation ══════════════════════════════════════ -->
        <Teleport to="body">
            <div v-if="showResetModal"
                 @click.self="closeResetModal"
                 class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-8.25 3.75h.008v.008h-.008v-.008z"/>
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-gray-900">Confirmer la réinitialisation</h3>
                    </div>

                    <p class="text-sm text-gray-600 mb-4">
                        Cette action supprime toutes les données électorales et ne peut pas être annulée.
                        Confirmez votre identité pour continuer.
                    </p>

                    <label class="block text-sm font-medium text-gray-700 mb-1">Votre mot de passe</label>
                    <input v-model="resetForm.password" type="password" autocomplete="current-password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm mb-1
                                  focus:ring-2 focus:ring-red-500 focus:border-transparent" />
                    <p v-if="resetForm.errors.password" class="text-red-600 text-xs mb-3">{{ resetForm.errors.password }}</p>

                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Mot-clé de confirmation
                    </label>
                    <p class="text-xs text-gray-400 mb-1">Saisissez le mot clé réservé ici</p>
                    <input v-model="resetForm.keyword" type="text"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono
                                  focus:ring-2 focus:ring-red-500 focus:border-transparent" />
                    <p v-if="resetForm.errors.keyword" class="text-red-600 text-xs mt-1">{{ resetForm.errors.keyword }}</p>

                    <div class="flex gap-2 mt-5">
                        <button
                            @click="submitReset"
                            :disabled="resetForm.processing || !resetForm.password || !resetForm.keyword"
                            class="flex-1 bg-red-600 hover:bg-red-700 active:scale-95
                                   disabled:opacity-50 disabled:cursor-not-allowed
                                   text-white font-bold py-2.5 rounded-lg text-sm transition-all duration-100"
                        >
                            {{ resetForm.processing ? 'Réinitialisation...' : 'Réinitialiser définitivement' }}
                        </button>
                        <button
                            @click="closeResetModal"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2.5 px-4 rounded-lg text-sm"
                        >
                            Annuler
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
