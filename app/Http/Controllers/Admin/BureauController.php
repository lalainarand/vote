<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BureauVote;
use App\Models\VoteLog;
use App\Models\VoteOption;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BureauController extends Controller
{
    /**
     * Liste des bureaux avec filtres
     */



    public function index(Request $request)
    {
        $query = BureauVote::with(['users', 'statistics', 'adminValidator:id,name'])
            ->withCount(['bulletinImages' => fn($q) => $q->where('is_reset', false)])
            ->withCount('voteResets')
            ->with(['voteResets' => function ($q) {
                $q->latest()->select('id', 'bureau_vote_id', 'reason', 'created_at');
            }]);
        // ⚠️ withSum('sum_plus'/'sum_minus') supprimé : ça comptait des VOTES
        // (lignes VoteLog), pas des ÉLECTEURS — c'était trompeur sous "Compteur".

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->boolean('no_pv')) {
            $query->whereDoesntHave('bureauResults');
        }

        $bureaux = $query->orderBy('code')->paginate(15)->withQueryString();

        $bureaux->getCollection()->transform(function ($bureau) use ($request) {
            $latestReset = $bureau->voteResets->first();

            return [
                'id' => $bureau->id,
                'code' => $bureau->code,
                'nom' => $bureau->nom,
                'status' => $bureau->status,
                'is_procuration' => (bool) $bureau->is_procuration,
                'admin_validated' => $bureau->admin_validated_at !== null,
                'admin_validated_by_name' => $bureau->adminValidator?->name,
                'admin_validated_at' => $bureau->admin_validated_at?->format('d/m/Y à H:i'),
                'user_name' => $bureau->users->first()?->name ?? '—',
                'bulletin_images_count' => $bureau->bulletin_images_count,
                'reset_count' => $bureau->vote_resets_count,
                'latest_reset' => $latestReset ? [
                    'reason' => $latestReset->reason ?: 'Aucun motif fourni',
                    'created_at' => \Carbon\Carbon::parse($latestReset->created_at)->format('d/m/Y à H:i'),
                ] : null,
            ];
        });

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
            ->where('is_reset', false)
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
            'is_procuration' => 'boolean',
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
        $bureau->load(['users', 'statistics', 'bureauResults.voteOption', 'voteLogs.user', 'adminValidator:id,name']);

        // Compteurs système
        // ⚠️ sum('quantity'), pas count() : un vote par procuration est UNE ligne
        // VoteLog dont la quantité peut valoir 50 (ex: 50 votants procurés en un
        // lot). count() ne comptait que le nombre de lignes/saisies (ex: 1 ou 2),
        // ce qui créait un écart énorme et faux face au PV papier saisi en clair.
        $counters = VoteOption::orderBy('ordre_affichage')->get()->map(function ($opt) use ($bureau) {
            $plus = VoteLog::where('bureau_vote_id', $bureau->id)
                ->where('vote_option_id', $opt->id)
                ->where('action', '+1')
                ->sum('quantity');
            $minus = VoteLog::where('bureau_vote_id', $bureau->id)
                ->where('vote_option_id', $opt->id)
                ->where('action', '-1')
                ->sum('quantity');

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
               'created_at' => Carbon::parse($log->created_at)->format('H:i:s'),
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
            'is_procuration' => 'boolean',
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
     * Validation admin : confirmation de second niveau, APRÈS que l'opérateur ait
     * lui-même clôturé/validé son bureau (status = 'validated'). Purement déclaratif :
     * ne modifie ni les votes ni le PV, se contente de tracer qui a confirmé et quand,
     * pour affichage (badge "Validé par ...").
     */
    public function adminValidate(BureauVote $bureau)
    {
        if ($bureau->status !== 'validated') {
            return redirect()
                ->back()
                ->with('error', 'Ce bureau doit d\'abord être validé par l\'opérateur.');
        }

        if ($bureau->admin_validated_at !== null) {
            return redirect()
                ->back()
                ->with('error', 'Ce bureau a déjà été validé par un admin.');
        }

        $bureau->update([
            'admin_validated_by' => auth()->id(),
            'admin_validated_at' => now(),
        ]);

        return redirect()
            ->back()
            ->with('success', 'Bureau validé côté admin.');
    }
}
