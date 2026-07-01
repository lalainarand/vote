<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BureauVote;
use App\Models\VoteOption;
use App\Models\BureauResult;
use App\Models\BureauStatistic;
use App\Models\VoteLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BureauController extends Controller
{
    /**
     * Liste des bureaux avec filtres
     */
    public function index(Request $request)
    {
        $query = BureauVote::with(['users', 'statistics']);

        // Filtre par statut
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtre : bureaux sans PV saisi
        if ($request->boolean('no_pv')) {
            $query->whereDoesntHave('bureauResults');
        }

        $bureaux = $query->orderBy('code')->paginate(15)->withQueryString();

        return Inertia::render('Admin/Bureaux/Index', [
            'bureaux' => $bureaux,
            'filters' => $request->only(['status', 'no_pv']),
        ]);
    }

    /**
     * Formulaire de création
     */
    public function create()
    {
        return Inertia::render('Admin/Bureaux/Create');
    }

    /**
     * Enregistrement
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:bureaux_vote,code',
            'nom'  => 'required|string|max:255',
        ]);

        BureauVote::create($validated);

        return redirect()
            ->route('admin.bureaux.index')
            ->with('success', 'Bureau de vote créé avec succès');
    }

    /**
     * Affichage détail bureau
     */

    public function show(BureauVote $bureau)
    {
        $bureau->load(['users', 'statistics', 'bureauResults.voteOption', 'voteLogs.user']);

        // Compteurs système
        $counters = VoteOption::orderBy('ordre_affichage')->get()->map(function ($opt) use ($bureau) {
            $plus = VoteLog::where('bureau_vote_id', $bureau->id)
                ->where('vote_option_id', $opt->id)
                ->where('action', '+1')
                ->count();
            $minus = VoteLog::where('bureau_vote_id', $bureau->id)
                ->where('vote_option_id', $opt->id)
                ->where('action', '-1')
                ->count();

            $systemCount = $plus - $minus;

            // Résultat PV
            $result = $bureau->bureauResults->firstWhere('vote_option_id', $opt->id);
            $pvCount = $result?->count ?? null;
            $ecart = $pvCount !== null ? $pvCount - $systemCount : null;

            return [
                'id' => $opt->id,
                'nom' => $opt->nom,
                'type' => $opt->type,
                'system_count' => $systemCount,
                'pv_count' => $pvCount,
                'ecart' => $ecart,
                'source' => $result?->source,
            ];
        });

        // Historique vote_logs (derniers 100)
        $recentLogs = $bureau->voteLogs()
            ->with(['user', 'voteOption'])
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(fn($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'option' => $log->voteOption?->nom,
                'user' => $log->user?->name,
                'created_at' => $log->created_at->format('H:i:s'),
            ]);

        // Statistiques
        $stats = $bureau->statistics;

        return Inertia::render('Admin/Bureaux/Show', [
            'bureau' => $bureau,
            'counters' => $counters,
            'recent_logs' => $recentLogs,
            'statistics' => $stats ? [
                'registered_voters' => $stats->registered_voters,
                'voters' => $stats->voters,
                'ballots_found' => $stats->ballots_found,
                'pv_source' => $stats->pv_source,
                'pv_note' => $stats->pv_note,
            ] : null,
        ]);
    }

    /**
     * Formulaire d'édition
     */
    public function edit(BureauVote $bureau)
    {
        return Inertia::render('Admin/Bureaux/Edit', [
            'bureau' => $bureau,
        ]);
    }

    /**
     * Mise à jour
     */
    public function update(Request $request, BureauVote $bureau)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:bureaux_vote,code,' . $bureau->id,
            'nom'  => 'required|string|max:255',
        ]);

        $bureau->update($validated);

        return redirect()
            ->route('admin.bureaux.index')
            ->with('success', 'Bureau mis à jour');
    }

    /**
     * Suppression (si pas de votes enregistrés)
     */
    public function destroy(BureauVote $bureau)
    {
        if ($bureau->voteLogs()->exists()) {
            return redirect()
                ->route('admin.bureaux.index')
                ->with('error', 'Impossible de supprimer un bureau ayant déjà des votes enregistrés');
        }

        $bureau->delete();

        return redirect()
            ->route('admin.bureaux.index')
            ->with('success', 'Bureau supprimé');
    }

    /**
     * Verrouiller un bureau
     */
    public function lock(BureauVote $bureau)
    {
        $bureau->update(['status' => 'anomaly']);

        return redirect()
            ->back()
            ->with('success', 'Bureau verrouillé');
    }

    /**
     * Déverrouiller un bureau
     */
    public function unlock(BureauVote $bureau)
    {
        $bureau->update(['status' => 'counting']);

        return redirect()
            ->back()
            ->with('success', 'Bureau déverrouillé');
    }

    /**
     * Écran de saisie PV manuelle admin
     */
    public function manualPv(BureauVote $bureau)
    {
        $bureau->load(['statistics', 'bureauResults']);

        // Compteurs système (procurations incluses via quantity)
        $counters = VoteOption::orderBy('ordre_affichage')->get()->map(function ($option) use ($bureau) {
            $plus = VoteLog::where('bureau_vote_id', $bureau->id)
                ->where('vote_option_id', $option->id)
                ->where('action', '+1')
                ->sum('quantity');

            $minus = VoteLog::where('bureau_vote_id', $bureau->id)
                ->where('vote_option_id', $option->id)
                ->where('action', '-1')
                ->sum('quantity');

            $procuration = VoteLog::where('bureau_vote_id', $bureau->id)
                ->where('vote_option_id', $option->id)
                ->where('is_procuration', true)
                ->sum('quantity');

            return [
                'id' => $option->id,
                'nom' => $option->nom,
                'type' => $option->type,
                'system_count' => $plus - $minus,
                'procuration' => $procuration,
            ];
        });

        // Valeurs PV actuelles (si déjà saisies)
        $pvValues = $bureau->bureauResults->pluck('count', 'vote_option_id')->toArray();

        return Inertia::render('Admin/Bureaux/ManualPv', [
            'bureau' => $bureau,
            'counters' => $counters,
            'pv_values' => $pvValues,
        ]);
    }

    /**
     * Enregistrement PV manuelle admin
     */
    public function storeManualPv(Request $request, BureauVote $bureau)
    {
        $validated = $request->validate([
            'pv_data' => 'required|array',
            'pv_data.*.vote_option_id' => 'required|exists:vote_options,id',
            'pv_data.*.count' => 'required|integer|min:0',
            'registered_voters' => 'required|integer|min:0',
            'voters' => 'required|integer|min:0',
            'ballots_found' => 'required|integer|min:0',
            'note' => 'nullable|string|max:1000',
            'mark_anomaly' => 'boolean',
        ]);

        DB::transaction(function () use ($validated, $bureau) {
            // Déterminer la source
            $source = $bureau->voteLogs()->exists() ? 'admin_override' : 'manual_pv';

            // Enregistrer les résultats par candidat
            foreach ($validated['pv_data'] as $pv) {
                BureauResult::updateOrCreate(
                    [
                        'bureau_vote_id' => $bureau->id,
                        'vote_option_id' => $pv['vote_option_id'],
                    ],
                    [
                        'count' => $pv['count'],
                        'source' => $source,
                        'entered_by' => auth()->id(),
                        'entered_at' => now(),
                    ]
                );
            }

            // Enregistrer les statistiques
            BureauStatistic::updateOrCreate(
                ['bureau_vote_id' => $bureau->id],
                [
                    'registered_voters' => $validated['registered_voters'],
                    'voters' => $validated['voters'],
                    'ballots_found' => $validated['ballots_found'],
                    'pv_source' => 'admin',
                    'pv_note' => $validated['note'] ?? null,
                ]
            );

            // Statut du bureau
            $newStatus = $validated['mark_anomaly'] ?? false ? 'anomaly' : 'pv_admin';
            $bureau->update(['status' => $newStatus]);

            // Log dans vote_logs pour traçabilité
            VoteLog::create([
                'bureau_vote_id' => $bureau->id,
                'vote_option_id' => VoteOption::where('type', 'candidat')->first()->id,
                'user_id' => auth()->id(),
                'action' => '+1',
                'created_at' => now(),
            ]);
        });

        return redirect()
            ->route('admin.bureaux.index')
            ->with('success', 'PV manuel enregistré avec succès');
    }
}
