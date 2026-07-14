<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\BulletinImage;
use App\Models\BulletinLog;
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

        // 🚀 OPTIMISATION & CORRECTION : Calcul précis incluant l'action (+1 / -1)
        $counters = VoteOption::orderBy('ordre_affichage')
            ->select('id', 'nom', 'type', 'photo', 'ordre_affichage')
            ->withSum(['voteLogs as plus_sum' => fn($q) => $q->where('action', '+1')->where('bureau_vote_id', $bureau->id)], 'quantity')
            ->withSum(['voteLogs as minus_sum' => fn($q) => $q->where('action', '-1')->where('bureau_vote_id', $bureau->id)], 'quantity')
            // NOUVEAU : On sépare les procurations +1 et -1 pour pouvoir les soustraire
            ->withSum(['voteLogs as proc_plus_sum' => fn($q) => $q->where('action', '+1')->where('is_procuration', true)->where('bureau_vote_id', $bureau->id)], 'quantity')
            ->withSum(['voteLogs as proc_minus_sum' => fn($q) => $q->where('action', '-1')->where('is_procuration', true)->where('bureau_vote_id', $bureau->id)], 'quantity')
            ->get()
            ->map(function ($opt) {
                return [
                    'id'              => $opt->id,
                    'nom'             => $opt->nom,
                    'type'            => $opt->type,
                    'photo'           => $opt->photo,
                    'ordre_affichage' => $opt->ordre_affichage,
                    'count'           => ($opt->plus_sum ?? 0) - ($opt->minus_sum ?? 0),
                    // La vraie formule : (Procurations ajoutées) - (Procurations retirées par reset)
                    'procuration'     => (int)(($opt->proc_plus_sum ?? 0) - ($opt->proc_minus_sum ?? 0)),
                ];
            });

        // Total procurations pour ce bureau, toutes options confondues (CORRIGÉ)
        $totalProcPlus = VoteLog::where('bureau_vote_id', $bureau->id)
            ->where('action', '+1')
            ->where('is_procuration', true)
            ->sum('quantity');

        $totalProcMinus = VoteLog::where('bureau_vote_id', $bureau->id)
            ->where('action', '-1')
            ->where('is_procuration', true)
            ->sum('quantity');

        $totalProcuration = $totalProcPlus - $totalProcMinus;

        // Compteur système de bulletins dépouillés
        // (Assurez-vous que currentCountForBureau est bien défini dans votre modèle, sinon utilisez la méthode privée du CountingController)
        $bulletinCount = BulletinLog::currentCountForBureau($bureau->id);

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

        // Photos du compteur/bulletins de ce bureau
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
            'total_procuration' => (int) $totalProcuration, // Sera maintenant bien à 0 après un reset
            'bulletin_count' => $bulletinCount,
            'bulletin_images' => $bulletinImages,
        ]);
    }
}
