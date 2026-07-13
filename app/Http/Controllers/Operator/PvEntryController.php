<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\VoteOption;
use App\Models\VoteLog;
use App\Models\BulletinLog;
use App\Models\BureauResult;
use App\Models\BureauStatistic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PvEntryController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $bureau = $user->bureauVote;

        if (!$bureau) {
            abort(403, 'Aucun bureau assigné');
        }

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

        $pvValues = $bureau->bureauResults()
            ->where('source', 'counting')
            ->pluck('count', 'vote_option_id')
            ->toArray();

        $stats = $bureau->statistics;

        // Comptage système des bulletins (celui-ci était déjà correct car le where était présent)
        $systemBallotsCount = BulletinLog::where('bureau_vote_id', $bureau->id)
            ->selectRaw("SUM(CASE WHEN action = '+1' THEN quantity ELSE 0 END) - SUM(CASE WHEN action = '-1' THEN quantity ELSE 0 END) as total")
            ->value('total') ?? 0;

        return Inertia::render('Operator/Pv', [
            'bureau' => $bureau,
            'counters' => $counters,
            'pv_values' => $pvValues,
            'statistics' => $stats ? [
                'ballots_found' => $stats->ballots_found,
            ] : null,
            'system_ballots_count' => (int) $systemBallotsCount,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $bureau = $user->bureauVote;

        if (!$bureau) {
            return back()->with('error', 'Aucun bureau assigné');
        }

        if (!in_array($bureau->status, ['counting', 'pv_entry', 'anomaly'])) {
            return back()->with('error', 'Le bureau n\'est pas en phase de saisie PV');
        }

        $validated = $request->validate([
            'pv_data' => 'required|array',
            'pv_data.*.vote_option_id' => 'required|exists:vote_options,id',
            'pv_data.*.count' => 'required|integer|min:0',
            'ballots_found' => 'required|integer|min:0', // Votants = Bulletins trouvés
        ]);

        DB::transaction(function () use ($validated, $bureau, $user) {
            // 1. Enregistrer les résultats par option
            foreach ($validated['pv_data'] as $pv) {
                BureauResult::updateOrCreate(
                    ['bureau_vote_id' => $bureau->id, 'vote_option_id' => $pv['vote_option_id']],
                    [
                        'count' => $pv['count'],
                        'source' => 'counting',
                        'entered_by' => $user->id,
                        'entered_at' => now(),
                    ]
                );
            }

            // 2. Statistiques (voters est automatiquement égal à ballots_found)
            BureauStatistic::updateOrCreate(
                ['bureau_vote_id' => $bureau->id],
                [
                    'voters' => $validated['ballots_found'],
                    'ballots_found' => $validated['ballots_found'],
                    'pv_source' => 'operator',
                ]
            );

            $bureau->update(['status' => 'pv_entry']);
        });

        return redirect()
            ->route('operator.cloture.index')
            ->with('success', 'PV enregistré avec succès. Vous pouvez maintenant clôturer le bureau.');
    }
}
