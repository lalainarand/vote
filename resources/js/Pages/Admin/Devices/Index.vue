<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    devices:  Array,
    attempts: Array,
})

const addForm = useForm({ device_name: '' })
const submitAdd = () => {
    addForm.post('/admin/devices', { onSuccess: () => addForm.reset() })
}

const bulkForm = useForm({ count: 26, prefix: 'Tablette' })
const showBulk = ref(false)
const submitBulk = () => {
    if (!confirm(`Générer ${bulkForm.count} appareil(s) "${bulkForm.prefix} 01, 02, ..." ?`)) return
    bulkForm.post('/admin/devices/bulk', { onSuccess: () => { showBulk.value = false } })
}

const toggleApproved = (d) => {
    const action = d.is_approved ? 'révoquer' : 'réautoriser'
    if (confirm(`Confirmer : ${action} l'appareil "${d.device_name}" ?`)) {
        router.patch(`/admin/devices/${d.id}/toggle-approved`, {}, { preserveScroll: true })
    }
}

const destroyDevice = (d) => {
    if (confirm(`Supprimer définitivement l'appareil "${d.device_name}" ? Cette action est irréversible.`)) {
        router.delete(`/admin/devices/${d.id}`)
    }
}

const copied = ref(null)
const copyLink = async (d) => {
    try {
        await navigator.clipboard.writeText(d.pairing_url)
        copied.value = d.id
        setTimeout(() => { copied.value = null }, 1500)
    } catch (e) {
        prompt('Copiez ce lien :', d.pairing_url)
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Appareils autorisés</h1>
        </template>

        <!-- Ajout -->
        <div class="bg-white rounded-xl border border-gray-100 p-4 mb-4">
            <div class="flex flex-wrap items-end gap-3">
                <form @submit.prevent="submitAdd" class="flex items-end gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Nom de l'appareil</label>
                        <input v-model="addForm.device_name" type="text" placeholder="Ex: Tablette 12"
                               class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                    </div>
                    <button type="submit" :disabled="addForm.processing || !addForm.device_name"
                            class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                        + Ajouter
                    </button>
                </form>

                <button @click="showBulk = !showBulk"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    Générer en série…
                </button>
            </div>

            <form v-if="showBulk" @submit.prevent="submitBulk" class="flex items-end gap-2 mt-3 pt-3 border-t border-gray-100">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Nombre</label>
                    <input v-model.number="bulkForm.count" type="number" min="1" max="100"
                           class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Préfixe</label>
                    <input v-model="bulkForm.prefix" type="text"
                           class="px-3 py-2 border border-gray-300 rounded-lg text-sm" />
                </div>
                <button type="submit" :disabled="bulkForm.processing"
                        class="bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    Générer
                </button>
                <span class="text-xs text-gray-400 pb-2.5">
                    Ex: "{{ bulkForm.prefix }} 01", "{{ bulkForm.prefix }} 02"…
                </span>
            </form>
        </div>

        <!-- Liste des appareils -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden mb-6">
            <div class="px-4 py-3 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">
                    Tablettes enregistrées <span class="text-gray-400 font-normal">({{ devices.length }})</span>
                </h2>
            </div>
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">Nom</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">Dernière utilisation</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">Navigateur / Plateforme</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">Approuvé par</th>
                        <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase">Statut</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="devices.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">Aucun appareil enregistré</td>
                    </tr>
                    <tr v-for="d in devices" :key="d.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ d.device_name }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500 font-mono">{{ d.last_used_at ?? 'Jamais utilisé' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            <template v-if="d.browser || d.platform">{{ d.browser ?? '—' }} / {{ d.platform ?? '—' }}</template>
                            <span v-else class="text-gray-300">—</span>
                            <div v-if="d.ip_address" class="font-mono text-gray-400">{{ d.ip_address }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ d.approved_by ?? '—' }}
                            <div v-if="d.approved_at" class="text-gray-400">{{ d.approved_at }}</div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button @click="toggleApproved(d)"
                                    class="text-xs font-semibold px-2.5 py-1 rounded-full transition-colors"
                                    :class="d.is_approved
                                        ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                        : 'bg-red-100 text-red-700 hover:bg-red-200'"
                                    :title="d.is_approved ? 'Cliquer pour révoquer' : 'Cliquer pour réautoriser'">
                                {{ d.is_approved ? 'Autorisé' : 'Révoqué' }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right space-x-3">
                            <button @click="copyLink(d)" class="text-blue-600 hover:text-blue-800 text-xs font-semibold">
                                {{ copied === d.id ? 'Copié !' : "Lien d'appairage" }}
                            </button>
                            <button @click="destroyDevice(d)" class="text-red-600 hover:text-red-800 text-xs font-semibold">
                                Supprimer
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Tentatives non autorisées -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2">
                <span v-if="attempts.length > 0" class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                <h2 class="text-sm font-semibold text-gray-700">
                    🚨 Tentatives depuis un appareil non autorisé
                    <span class="text-gray-400 font-normal">({{ attempts.length }})</span>
                </h2>
            </div>
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">Utilisateur</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">Navigateur / Plateforme</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">IP</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="attempts.length === 0">
                        <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400">Aucune tentative bloquée</td>
                    </tr>
                    <tr v-for="a in attempts" :key="a.id" class="hover:bg-red-50/50">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ a.user ?? 'Inconnu' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ a.browser ?? '—' }} / {{ a.platform ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs font-mono text-gray-500">{{ a.ip_address ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs font-mono text-gray-500">{{ a.created_at }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Info -->
        <div class="mt-4 bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
            <strong>Comment appairer une tablette :</strong>
            <ol class="list-decimal list-inside mt-1 space-y-0.5">
                <li>Ajoutez l'appareil ci-dessus (il est autorisé dès sa création)</li>
                <li>Copiez son lien d'appairage et ouvrez-le une fois dans le navigateur de la tablette</li>
                <li>La tablette est reconnue durablement (cookie persistant) — les opérateurs actifs et approuvés peuvent alors s'y connecter</li>
            </ol>
        </div>
    </AuthenticatedLayout>
</template>
