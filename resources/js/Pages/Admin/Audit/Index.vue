<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
    logs:     Object,
    filters:  Object,
    bureaux:  Array,
    users:    Array,
})

const filterBureau = (id) => {
    router.get('/admin/audit', { ...props.filters, bureau_id: id || undefined }, { preserveState: true })
}
const filterUser = (id) => {
    router.get('/admin/audit', { ...props.filters, user_id: id || undefined }, { preserveState: true })
}
const filterAction = (action) => {
    router.get('/admin/audit', { ...props.filters, action: action || undefined }, { preserveState: true })
}
const filterProcuration = (value) => {
    router.get('/admin/audit', { ...props.filters, procuration: value || undefined }, { preserveState: true })
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Journal d'audit</h1>
        </template>

        <!-- Filtres -->
        <div class="bg-white rounded-xl border border-gray-100 p-4 mb-4 grid grid-cols-1 md:grid-cols-5 gap-3">
            <select @change="filterBureau($event.target.value)"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Tous les bureaux</option>
                <option v-for="b in bureaux" :key="b.id" :value="b.id">{{ b.code }} — {{ b.nom }}</option>
            </select>
            <select @change="filterUser($event.target.value)"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Tous les utilisateurs</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
            <select @change="filterAction($event.target.value)"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Toutes les actions</option>
                <option value="+1">+1</option>
                <option value="-1">-1</option>
            </select>
            <select @change="filterProcuration($event.target.value)"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Tout (procuration ou non)</option>
                <option value="1">Procuration uniquement</option>
                <option value="0">Hors procuration</option>
            </select>
            <Link :href="`/admin/audit`"
                  class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium text-center">
                Réinitialiser
            </Link>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date/Heure</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Bureau</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Candidat</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Action</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Quantité</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Utilisateur</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="logs.data.length === 0">
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-400">Aucun log</td>
                    </tr>
                    <tr v-for="log in logs.data" :key="log.id"
                        :class="log.is_procuration ? 'bg-purple-50/50 hover:bg-purple-50' : 'hover:bg-gray-50'">
                        <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ log.created_at }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            <span v-if="log.bureau" class="font-mono text-xs">{{ log.bureau.code }}</span>
                            <span v-if="log.bureau" class="ml-1">{{ log.bureau.nom }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 font-medium">{{ log.option }}</td>
                        <td class="px-4 py-3 text-center">
                            <span :class="log.action === '+1' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                  class="text-xs font-bold px-2.5 py-1 rounded-full">
                                {{ log.action }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-sm font-semibold text-gray-800">
                            {{ log.quantity }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span v-if="log.is_procuration"
                                  class="bg-purple-100 text-purple-700 text-xs font-bold px-2.5 py-1 rounded-full">
                                Procuration
                            </span>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ log.user }}</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="logs.links.length > 3" class="px-4 py-3 border-t border-gray-100">
                <div class="flex justify-center gap-1">
                    <Link v-for="link in logs.links" :key="link.label"
                          :href="link.url || '#'"
                          :class="link.active ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                          class="px-3 py-1 rounded text-sm"
                          v-html="link.label">
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>