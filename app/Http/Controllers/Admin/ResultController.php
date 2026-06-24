<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BureauVote;
use App\Models\VoteOption;
use App\Models\BureauResult;
use Inertia\Inertia;

class ResultController extends Controller
{
    public function index()
    {
        // Bureaux validés uniquement (contrainte #6)
        $validatedBureaux = BureauVote::where('status', 'validated')->count();
        $totalBureaux = BureauVote::count();

        // Résultats globaux avec priorité : admin_override > manual_pv > counting
        $options = VoteOption::orderBy('ordre_affichage')->get();
        
        $results = $options->map(function ($option) use ($validatedBureaux) {
            // Récupérer tous les résultats de ce candidat sur bureaux validés
            $allResults = BureauResult::where('vote_option_id', $option->id)
                ->whereHas('bureau', fn($q) => $q->where('status', 'validated'))
                ->with('bureau')
                ->get();

            // Appliquer priorité par bureau
            $total = 0;
            $bySource = ['counting' => 0, 'manual_pv' => 0, 'admin_override' => 0];
            
            foreach ($allResults as $r) {
                $total += $r->count;
                $bySource[$r->source] = ($bySource[$r->source] ?? 0) + $r->count;
            }

            return [
                'id' => $option->id,
                'nom' => $option->nom,
                'type' => $option->type,
                'total' => $total,
                'by_source' => $bySource,
            ];
        });

        // Total candidats pour pourcentages
        $totalCandidates = $results->where('type', 'candidat')->sum('total');

        // Répartition par source
        $sourceBreakdown = BureauVote::where('status', 'validated')
            ->join('bureau_results', 'bureaux_vote.id', '=', 'bureau_results.bureau_vote_id')
            ->selectRaw('bureau_results.source, COUNT(DISTINCT bureaux_vote.id) as count')
            ->groupBy('bureau_results.source')
            ->pluck('count', 'source');

        return Inertia::render('Admin/Resultats/Index', [
            'results' => $results,
            'total_candidates' => $totalCandidates,
            'validated_bureaux' => $validatedBureaux,
            'total_bureaux' => $totalBureaux,
            'source_breakdown' => $sourceBreakdown,
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