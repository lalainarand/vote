<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\BulletinImage;
use App\Models\BureauResult;
use App\Models\VoteLog;
use App\Models\VoteOption;
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

        // Compteurs en temps réel (somme des logs, procurations incluses)
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
                'id'               => $option->id,
                'nom'              => $option->nom,
                'type'             => $option->type,
                'photo'            => $option->photo,
                'ordre_affichage'  => $option->ordre_affichage,
                'count'            => $plus - $minus,
                'procuration'      => (int) $procuration,
            ];
        });

        // Total procurations pour ce bureau, toutes options confondues
        $totalProcuration = VoteLog::where('bureau_vote_id', $bureau->id)
            ->where('is_procuration', true)
            ->sum('quantity');

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

        // Photos du compteur/bulletins de ce bureau (les plus récentes en premier)
        $bulletinImages = BulletinImage::where('bureau_vote_id', $bureau->id)
            ->orderByDesc('taken_at')
            ->get()
            ->map(fn($img) => [
                'id'       => $img->id,
                'url'      => $img->url,
                'filename' => $img->filename,
                'taken_at' => $img->taken_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Operator/Dashboard', [
            'bureau' => $bureau,
            'counters' => $counters,
            'pv_results' => $pvResults,
            'statistics' => $stats,
            'total_procuration' => (int) $totalProcuration,
            'bulletin_images' => $bulletinImages,
        ]);
    }
}
