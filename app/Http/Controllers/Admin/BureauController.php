<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BulletinLog;
use App\Models\BureauResult;
use App\Models\BureauStatistic;
use App\Models\BureauVote;
use App\Models\VoteLog;
use App\Models\VoteOption;
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
        $query = BureauVote::with(['users', 'statistics'])
            ->withCount('bulletinImages'); // ← ajouté : bureau.bulletin_images_count

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

    // ═══════════════════════════════════════════════════════════════
    // Nouvelle méthode : page photos d'un bureau (réutilisable, ne
    // nécessite que l'ID du bureau)
    // ═══════════════════════════════════════════════════════════════

    public function photos(BureauVote $bureau)
    {
        $images = $bureau->bulletinImages()
            ->with('user:id,name')
            ->orderByDesc('taken_at')
            ->get()
            ->map(fn($img) => [
                'id'       => $img->id,
                'url'      => $img->url,
                'filename' => $img->filename,
                'taken_at' => $img->taken_at->format('d/m/Y H:i'),
                'user'     => $img->user?->name,
            ]);

        return Inertia::render('Admin/Bureaux/Photos', [
            'bureau' => $bureau->only(['id', 'code', 'nom', 'status']),
            'images' => $images,
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

        $counters = VoteOption::orderBy('ordre_affichage')
            ->select('id', 'nom', 'type', 'ordre_affichage')
            ->withSum(['voteLogs as plus_sum' => fn($q) => $q->where('action', '+1')->where('bureau_vote_id', $bureau->id)], 'quantity')
            ->withSum(['voteLogs as minus_sum' => fn($q) => $q->where('action', '-1')->where('bureau_vote_id', $bureau->id)], 'quantity')
            ->withSum(['voteLogs as procuration_sum' => fn($q) => $q->where('is_procuration', true)->where('bureau_vote_id', $bureau->id)], 'quantity')
            ->get()
            ->map(function ($opt) {
                return [
                    'id' => $opt->id,
                    'nom' => $opt->nom,
                    'type' => $opt->type,
                    'system_count' => ($opt->plus_sum ?? 0) - ($opt->minus_sum ?? 0),
                    'procuration' => $opt->procuration_sum ?? 0,
                ];
            });

        $pvValues = $bureau->bureauResults->pluck('count', 'vote_option_id')->toArray();

        // Cette partie était déjà correcte car elle contenait déjà le where('bureau_vote_id', $bureau->id)
        $systemBallotsCount = BulletinLog::where('bureau_vote_id', $bureau->id)
            ->selectRaw("SUM(CASE WHEN action = '+1' THEN quantity ELSE 0 END) - SUM(CASE WHEN action = '-1' THEN quantity ELSE 0 END) as total")
            ->value('total') ?? 0;

        return Inertia::render('Admin/Bureaux/ManualPv', [
            'bureau' => $bureau,
            'counters' => $counters,
            'pv_values' => $pvValues,
            'system_ballots_count' => (int) $systemBallotsCount,
        ]);
    }

    /**
     * Enregistrement PV manuel admin
     */
    public function storeManualPv(Request $request, BureauVote $bureau)
    {
        $validated = $request->validate([
            'pv_data' => 'required|array',
            'pv_data.*.vote_option_id' => 'required|exists:vote_options,id',
            'pv_data.*.count' => 'required|integer|min:0',
            'ballots_found' => 'required|integer|min:0', // Votants sera égal à ceci
            'note' => 'nullable|string|max:1000',
            'mark_anomaly' => 'boolean',
        ]);

        DB::transaction(function () use ($validated, $bureau) {
            // Déterminer la source
            $source = $bureau->voteLogs()->exists() ? 'admin_override' : 'manual_pv';

            // 1. Enregistrer les résultats par candidat/option
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

            // 2. Enregistrer les statistiques (voters = ballots_found)
            BureauStatistic::updateOrCreate(
                ['bureau_vote_id' => $bureau->id],
                [
                    'voters' => $validated['ballots_found'],
                    'ballots_found' => $validated['ballots_found'],
                    'pv_source' => 'admin',
                    'pv_note' => $validated['note'] ?? null,
                ]
            );

            // 3. Statut du bureau
            $newStatus = $validated['mark_anomaly'] ? 'anomaly' : 'pv_admin';
            $bureau->update(['status' => $newStatus]);

            // 4. Log dans vote_logs pour traçabilité (conservé de votre logique originale)
            $firstCandidate = VoteOption::where('type', 'candidat')->first();
            if ($firstCandidate) {
                VoteLog::create([
                    'bureau_vote_id' => $bureau->id,
                    'vote_option_id' => $firstCandidate->id,
                    'user_id' => auth()->id(),
                    'action' => '+1',
                    'quantity' => 0, // Quantité 0 pour ne pas fausser les comptes, juste pour la trace
                    'is_procuration' => false,
                    'created_at' => now(),
                ]);
            }
        });

        return redirect()
            ->route('admin.bureaux.index') // Adaptez si le nom de route est différent
            ->with('success', 'PV manuel enregistré avec succès');
    }
}
