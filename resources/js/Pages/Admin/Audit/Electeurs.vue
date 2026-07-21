<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
    sessions: Object,
    stats:    Object,
    filters:  Object,
    bureaux:  Array,
})

const filterBureau = (id) => {
    router.get('/admin/electeurs/audit', { ...props.filters, bureau_id: id || undefined }, { preserveState: true })
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Reconstruction du nombre d'électeurs</h1>
        </template>

        <!-- Avertissement méthode -->
        <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-4 text-xs text-amber-800 leading-relaxed">
            <strong>Méthode d'estimation :</strong> chaque bulletin est délimité par deux clics successifs sur le compteur
            de bulletins (les clics annulés par un « -1 » sont retirés au préalable). Si plusieurs quantités différentes
            de votes de procuration apparaissent dans le même intervalle (ex : 50 puis 30), chacune est traitée comme un
            bulletin distinct et comptée séparément — donc 50 + 30 = 80 électeurs, pas seulement 50. La seule limite
            restante : deux bulletins différents ayant exactement la <strong>même</strong> quantité dans la même fenêtre
            sont indiscernables et fusionnés en un seul (voir « Fenêtres à vérifier » ci-dessous).
        </div>

        <!-- Explication des fenêtres à risque -->
        <div v-if="stats.nb_fenetres_multi_bulletins > 0"
             class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 mb-4 text-xs text-blue-800 leading-relaxed">
            <strong>ℹ️ Fenêtres avec plusieurs bulletins détectés : {{ stats.nb_fenetres_multi_bulletins }}</strong> —
            ces intervalles contenaient plusieurs bulletins de procuration (identifiés par leurs quantités différentes),
            déjà comptés séparément dans le total. Elles restent surlignées ci-dessous pour vérification visuelle : si
            dans l'une d'elles deux bulletins avaient par coïncidence la même quantité, ils auraient été fusionnés à
            tort et sous-comptés.
        </div>

        <!-- Filtres -->
        <div class="bg-white rounded-xl border border-gray-100 p-4 mb-4 grid grid-cols-1 md:grid-cols-4 gap-3">
            <select @change="filterBureau($event.target.value)"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Tous les bureaux</option>
                <option v-for="b in bureaux" :key="b.id" :value="b.id" :selected="filters.bureau_id == b.id">
                    {{ b.code }} — {{ b.nom }}
                </option>
            </select>
            <Link href="/admin/electeurs/audit"
                  class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium text-center">
                Réinitialiser
            </Link>
        </div>

        <!-- Statistiques : électeurs -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
            <div class="bg-white rounded-xl border border-blue-200 bg-blue-50/30 p-4">
                <div class="text-xs font-semibold text-blue-600 uppercase mb-1">Total électeurs</div>
                <div class="text-xl font-bold text-blue-700">{{ stats.total_electeurs.toLocaleString('fr-FR') }}</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-4">
                <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Électeurs individuels</div>
                <div class="text-xl font-bold text-gray-800">{{ stats.total_electeurs_individuels.toLocaleString('fr-FR') }}</div>
            </div>
            <div class="bg-purple-50 rounded-xl border border-purple-100 p-4">
                <div class="text-xs font-semibold text-purple-600 uppercase mb-1">Électeurs par procuration</div>
                <div class="text-xl font-bold text-purple-700">{{ stats.total_electeurs_procuration.toLocaleString('fr-FR') }}</div>
            </div>
            <div class="rounded-xl border p-4"
                 :class="stats.nb_fenetres_multi_bulletins > 0 ? 'bg-blue-50 border-blue-200' : 'bg-white border-gray-100'">
                <div class="text-xs font-semibold uppercase mb-1" :class="stats.nb_fenetres_multi_bulletins > 0 ? 'text-blue-600' : 'text-gray-500'">
                    Fenêtres à vérifier
                </div>
                <div class="text-xl font-bold" :class="stats.nb_fenetres_multi_bulletins > 0 ? 'text-blue-700' : 'text-gray-800'">
                    {{ stats.nb_fenetres_multi_bulletins.toLocaleString('fr-FR') }}
                </div>
            </div>
        </div>

        <!-- Statistiques : bulletins -->
        <div class="grid grid-cols-4 gap-3 mb-4">
            <div class="bg-white rounded-xl border border-gray-100 p-4">
                <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Bulletins au total</div>
                <div class="text-xl font-bold text-gray-800">{{ stats.nb_bulletins_total.toLocaleString('fr-FR') }}</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-4">
                <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Bulletins individuels</div>
                <div class="text-xl font-bold text-gray-800">{{ stats.nb_bulletins_individuels.toLocaleString('fr-FR') }}</div>
            </div>
            <div class="bg-purple-50 rounded-xl border border-purple-100 p-4">
                <div class="text-xs font-semibold text-purple-600 uppercase mb-1">Bulletins par procuration</div>
                <div class="text-xl font-bold text-purple-700">{{ stats.nb_bulletins_procuration.toLocaleString('fr-FR') }}</div>
            </div>
            <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
                <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Bulletins annulés (-1)</div>
                <div class="text-xl font-bold text-gray-700">{{ stats.nb_annulations.toLocaleString('fr-FR') }}</div>
            </div>
        </div>

        <!-- Table des sessions reconstruites -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date/Heure</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Bureau</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Type</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Électeurs</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Candidats cochés</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="sessions.data.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">Aucune donnée</td>
                    </tr>
                    <tr v-for="(s, idx) in sessions.data" :key="idx"
                        :class="[
                            s.nb_bulletins_fenetre > 1 ? 'bg-blue-50/50 hover:bg-blue-50' :
                            s.type === 'procuration' ? 'bg-purple-50/40 hover:bg-purple-50' : 'hover:bg-gray-50'
                        ]">
                        <td class="px-4 py-3 text-sm font-mono text-gray-600">
                            {{ new Date(s.created_at).toLocaleString('fr-FR') }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ s.bureau }}</td>
                        <td class="px-4 py-3 text-center">
                            <span v-if="s.type === 'procuration'"
                                  class="bg-purple-100 text-purple-700 text-xs font-bold px-2.5 py-1 rounded-full">
                                Procuration
                            </span>
                            <span v-else class="bg-gray-100 text-gray-600 text-xs font-bold px-2.5 py-1 rounded-full">
                                Individuel
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-sm font-semibold text-gray-800">
                            {{ s.electeurs }}
                        </td>
                        <td class="px-4 py-3 text-center text-sm text-gray-600">
                            {{ s.nb_candidats ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span v-if="s.nb_bulletins_fenetre > 1" class="text-blue-600 text-xs font-medium"
                                  title="Plusieurs bulletins de procuration détectés dans le même intervalle (quantités distinctes) — déjà comptés séparément, à vérifier visuellement">
                                ℹ {{ s.nb_bulletins_fenetre }} bulletins dans cette fenêtre
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="sessions.links.length > 3" class="px-4 py-3 border-t border-gray-100">
                <div class="flex justify-center gap-1">
                    <Link v-for="link in sessions.links" :key="link.label"
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