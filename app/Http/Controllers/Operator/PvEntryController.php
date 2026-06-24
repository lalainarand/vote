<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\VoteOption;
use App\Models\VoteLog;
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

        // Compteurs système
        $counters = VoteOption::orderBy('ordre_affichage')->get()->map(function ($opt) use ($bureau) {
            $plus = VoteLog::where('bureau_vote_id', $bureau->id)
                ->where('vote_option_id', $opt->id)->where('action', '+1')->count();
            $minus = VoteLog::where('bureau_vote_id', $bureau->id)
                ->where('vote_option_id', $opt->id)->where('action', '-1')->count();
            return [
                'id' => $opt->id,
                'nom' => $opt->nom,
                'type' => $opt->type,
                'system_count' => $plus - $minus,
            ];
        });

        // PV déjà saisis (si édition)
        $pvValues = $bureau->bureauResults()
            ->where('source', 'counting')
            ->pluck('count', 'vote_option_id')
            ->toArray();

        $stats = $bureau->statistics;

        return Inertia::render('Operator/Pv', [
            'bureau' => $bureau,
            'counters' => $counters,
            'pv_values' => $pvValues,
            'statistics' => $stats ? [
                'registered_voters' => $stats->registered_voters,
                'voters' => $stats->voters,
                'ballots_found' => $stats->ballots_found,
            ] : null,
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
            'registered_voters' => 'required|integer|min:0',
            'voters' => 'required|integer|min:0',
            'ballots_found' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($validated, $bureau, $user) {
            // Enregistrer les résultats
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

            // Statistiques
            BureauStatistic::updateOrCreate(
                ['bureau_vote_id' => $bureau->id],
                [
                    'registered_voters' => $validated['registered_voters'],
                    'voters' => $validated['voters'],
                    'ballots_found' => $validated['ballots_found'],
                    'pv_source' => 'operator',
                ]
            );

            // Passage en pv_entry
            $bureau->update(['status' => 'pv_entry']);
        });

        return redirect()
            ->route('operator.cloture.index')
            ->with('success', 'PV enregistré. Vous pouvez maintenant clôturer le bureau.');
    }
}