<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BureauResult;
use App\Models\BureauVote;
use App\Models\VoteLog;
use App\Models\VoteOption;
use Inertia\Inertia;

class ResultController extends Controller
{
    public function index()
    {
        $validatedBureauIds = BureauVote::where('status', 'validated')->pluck('id');
        $validatedBureaux   = $validatedBureauIds->count();
        $totalBureaux       = BureauVote::count();

        $options = VoteOption::orderBy('ordre_affichage')->get();

        $results = $options->map(function ($option) use ($validatedBureauIds) {

            // --- Compteur système (VoteLogs, même logique que manualPv) ---
            $plus  = VoteLog::where('vote_option_id', $option->id)
                ->whereIn('bureau_vote_id', $validatedBureauIds)
                ->where('action', '+1')
                ->count();
            $minus = VoteLog::where('vote_option_id', $option->id)
                ->whereIn('bureau_vote_id', $validatedBureauIds)
                ->where('action', '-1')
                ->count();
            $systemCount = $plus - $minus;

            // --- PV papier (BureauResults) avec répartition par source ---
            $bureauResults = BureauResult::where('vote_option_id', $option->id)
                ->whereIn('bureau_vote_id', $validatedBureauIds)
                ->get();

            $pvCount   = (int) $bureauResults->sum('count');
            $bySource  = [
                'counting'       => (int) $bureauResults->where('source', 'counting')->sum('count'),
                'manual_pv'      => (int) $bureauResults->where('source', 'manual_pv')->sum('count'),
                'admin_override' => (int) $bureauResults->where('source', 'admin_override')->sum('count'),
            ];

            return [
                'id'           => $option->id,
                'nom'          => $option->nom,
                'type'         => $option->type,
                'system_count' => $systemCount,
                'pv_count'     => $pvCount,
                'ecart'        => $pvCount - $systemCount,
                'by_source'    => $bySource,
            ];
        });

        $totalCandidatesPv     = $results->where('type', 'candidat')->sum('pv_count');
        $totalCandidatesSystem = $results->where('type', 'candidat')->sum('system_count');

        $sourceBreakdown = BureauVote::whereIn('bureaux_vote.id', $validatedBureauIds)
    ->join('bureau_results', 'bureaux_vote.id', '=', 'bureau_results.bureau_vote_id')
    ->selectRaw('bureau_results.source, COUNT(DISTINCT bureaux_vote.id) as count')
    ->groupBy('bureau_results.source')
    ->pluck('count', 'source');

        return Inertia::render('Admin/Resultats/Index', [
            'results'                => $results,
            'total_candidates_pv'    => $totalCandidatesPv,
            'total_candidates_system' => $totalCandidatesSystem,
            'validated_bureaux'      => $validatedBureaux,
            'total_bureaux'          => $totalBureaux,
            'source_breakdown'       => $sourceBreakdown,
        ]);
    }

    public function export()
    {
        // Export CSV simple (pour V2 : Excel avec PhpSpreadsheet)
        $options = VoteOption::orderBy('ordre_affichage')->get();
        $bureaux = BureauVote::where('status', 'validated')->with('bureauResults')->get();

        $csv = "Bureau;Statut;" . $options->pluck('nom')->join(';') . "\n";

        foreach ($bureaux as $bureau) {
            $line = [$bureau->code . ' - ' . $bureau->nom, $bureau->status];
            foreach ($options as $opt) {
                $result = $bureau->bureauResults->firstWhere('vote_option_id', $opt->id);
                $line[] = $result?->count ?? 0;
            }
            $csv .= implode(';', $line) . "\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="resultats_' . date('Y-m-d_His') . '.csv"');
    }
}
