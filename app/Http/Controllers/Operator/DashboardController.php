<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\VoteOption;
use App\Models\VoteLog;
use App\Models\BureauResult;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $bureau = $user->bureauVote;

        if (!$bureau) {
            return Inertia::render('Operator/Dashboard', [
                'bureau' => null,
                'error' => 'Aucun bureau assigné. Contactez l\'administrateur.',
            ]);
        }

        // Compteurs en temps réel (somme des logs)
        $counters = VoteOption::withCount(['voteLogs' => function($q) use ($bureau) {
            $q->where('bureau_vote_id', $bureau->id)
              ->where('action', '+1');
        }])->get()->map(function($option) use ($bureau) {
            $minus = VoteLog::where('bureau_vote_id', $bureau->id)
                ->where('vote_option_id', $option->id)
                ->where('action', '-1')
                ->count();
            
            return [
                'id' => $option->id,
                'nom' => $option->nom,
                'type' => $option->type,
                'count' => $option->vote_logs_count - $minus,
            ];
        });

        // Résultats PV (si saisis)
        $pvResults = BureauResult::where('bureau_vote_id', $bureau->id)
            ->with('voteOption')
            ->get()
            ->map(fn($r) => [
                'vote_option_id' => $r->vote_option_id,
                'pv_value' => $r->count,
                'source' => $r->source,
            ]);

        // Statistiques bureau
        $stats = $bureau->statistics ? [
            'registered_voters' => $bureau->statistics->registered_voters,
            'voters' => $bureau->statistics->voters,
            'ballots_found' => $bureau->statistics->ballots_found,
        ] : null;

        return Inertia::render('Operator/Dashboard', [
            'bureau' => [
                'id' => $bureau->id,
                'code' => $bureau->code,
                'nom' => $bureau->nom,
                'status' => $bureau->status,
            ],
            'counters' => $counters,
            'pv_results' => $pvResults,
            'statistics' => $stats,
        ]);
    }
}