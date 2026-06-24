<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\VoteOption;
use App\Models\VoteLog;
use App\Models\BureauResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CloseController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $bureau = $user->bureauVote;

        if (!$bureau) {
            abort(403, 'Aucun bureau assigné');
        }

        // Résultats PV
        $results = BureauResult::where('bureau_vote_id', $bureau->id)
            ->with('voteOption')
            ->get()
            ->sortBy(fn($r) => $r->voteOption->ordre_affichage)
            ->values()
            ->map(fn($r) => [
                'nom' => $r->voteOption->nom,
                'type' => $r->voteOption->type,
                'count' => $r->count,
            ]);

        $stats = $bureau->statistics;

        return Inertia::render('Operator/Close', [
            'bureau' => $bureau,
            'results' => $results,
            'statistics' => $stats ? [
                'registered_voters' => $stats->registered_voters,
                'voters' => $stats->voters,
                'ballots_found' => $stats->ballots_found,
            ] : null,
        ]);
    }

    public function close(Request $request)
    {
        $user = auth()->user();
        $bureau = $user->bureauVote;

        if (!$bureau) {
            return back()->with('error', 'Aucun bureau assigné');
        }

        // Vérifications
        if ($bureau->status === 'validated') {
            return back()->with('error', 'Ce bureau est déjà validé');
        }

        if (!in_array($bureau->status, ['pv_entry', 'pv_admin', 'anomaly'])) {
            return back()->with('error', 'Le PV doit être saisi avant la clôture');
        }

        // Vérifier que les résultats existent
        if (!$bureau->bureauResults()->exists()) {
            return back()->with('error', 'Aucun résultat à valider');
        }

        $bureau->update(['status' => 'validated']);

        return redirect()
            ->route('operator.dashboard')
            ->with('success', 'Bureau validé définitivement !');
    }
}