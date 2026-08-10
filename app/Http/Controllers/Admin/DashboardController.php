<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BulletinLog;
use App\Models\BureauResult;
use App\Models\BureauVote;
use App\Models\DeviceLoginAttempt;
use App\Models\VoteLog;
use App\Models\VoteOption;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Stats globales
        $totalBureaux = BureauVote::count();
        $validatedBureaux = BureauVote::where('status', 'validated')->count();
        $anomalyBureaux = BureauVote::where('status', 'anomaly')->count();
        // Validation admin : confirmation de second niveau (purement déclarative,
        // ne modifie aucun chiffre), posée après que l'opérateur a validé son bureau.
        $adminValidatedBureaux = BureauVote::whereNotNull('admin_validated_at')->count();

        $progression = $totalBureaux > 0
            ? round(($validatedBureaux / $totalBureaux) * 100)
            : 0;

        // Résultats nationaux (bureaux validés uniquement)
        $validatedBureauIds = BureauVote::where('status', 'validated')->pluck('id');

        $nationalResults = VoteOption::get()->map(function ($option) use ($validatedBureauIds) {

            // Compteur système : VoteLogs +1 moins -1, quantity incluse (procurations)
            $plus = VoteLog::where('vote_option_id', $option->id)
                ->whereIn('bureau_vote_id', $validatedBureauIds)
                ->where('action', '+1')
                ->sum('quantity');

            $minus = VoteLog::where('vote_option_id', $option->id)
                ->whereIn('bureau_vote_id', $validatedBureauIds)
                ->where('action', '-1')
                ->sum('quantity');

            // Total procurations pour ce candidat, tous bureaux validés confondus
            $procuration = VoteLog::where('vote_option_id', $option->id)
                ->whereIn('bureau_vote_id', $validatedBureauIds)
                ->where('is_procuration', true)
                ->sum('quantity');

            // PV papier
            $pvCount = BureauResult::where('vote_option_id', $option->id)
                ->whereIn('bureau_vote_id', $validatedBureauIds)
                ->sum('count');

            $systemCount = $plus - $minus;

            return [
                'id'           => $option->id,
                'nom'          => $option->nom,
                'type'         => $option->type,
                'system_count' => $systemCount,
                'procuration'  => (int) $procuration,
                'pv_count'     => (int) $pvCount,
                'ecart'        => (int) $pvCount - $systemCount,
            ];
        });

        // Total procurations national, toutes options confondues
        $totalProcurationNational = VoteLog::whereIn('bureau_vote_id', $validatedBureauIds)
            ->where('is_procuration', true)
            ->sum('quantity');

        // Transparence : voix enregistrées dans des bureaux marqués anomalie, exclues
        // des résultats nationaux ci-dessus (déjà limités aux bureaux validés) mais
        // affichées à part pour que rien ne disparaisse silencieusement.
        $anomalyBureauIds = BureauVote::where('status', 'anomaly')->pluck('id');
        $anomalyVotes = $anomalyBureauIds->isEmpty() ? 0 : (
            VoteLog::whereIn('bureau_vote_id', $anomalyBureauIds)->where('action', '+1')->sum('quantity')
            - VoteLog::whereIn('bureau_vote_id', $anomalyBureauIds)->where('action', '-1')->sum('quantity')
        );

        // Bulletins dépouillés (tous statuts SAUF anomalie — un bureau marqué anomalie
        // est exclu de tous les résultats officiels, y compris ce compteur de voix).
        $nonAnomalyBureauIds = BureauVote::where('status', '!=', 'anomaly')->pluck('id');
        $totalBulletins = BulletinLog::currentCountNational(null, $nonAnomalyBureauIds);
        $totalBulletinsProcuration = BulletinLog::currentCountNational(true, $nonAnomalyBureauIds);
        $totalBulletinsIndividuel = BulletinLog::currentCountNational(false, $nonAnomalyBureauIds);

        // Répartition des statuts
        $statusBreakdown = BureauVote::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Tentatives récentes depuis un appareil non autorisé (dernières 24h)
        $recentDeviceAttempts = DeviceLoginAttempt::where('created_at', '>=', now()->subDay())->count();

        // Alertes
        $alerts = [];
        if ($anomalyBureaux > 0) {
            $alerts[] = [
                'type' => 'error',
                'message' => "$anomalyBureaux bureau(x) en anomalie",
                'link' => route('admin.bureaux.index', ['status' => 'anomaly']),
            ];
        }
        if ($recentDeviceAttempts > 0) {
            $alerts[] = [
                'type' => 'error',
                'message' => "🚨 $recentDeviceAttempts tentative(s) depuis un appareil non autorisé (24h)",
                'link' => route('admin.devices.index'),
            ];
        }
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_bureaux' => $totalBureaux,
                'validated_bureaux' => $validatedBureaux,
                'anomaly_bureaux' => $anomalyBureaux,
                'admin_validated_bureaux' => $adminValidatedBureaux,
                'progression' => $progression,
                'total_procuration' => (int) $totalProcurationNational,
                'total_bulletins' => (int) $totalBulletins,
                'total_bulletins_procuration' => (int) $totalBulletinsProcuration,
                'total_bulletins_individuel' => (int) $totalBulletinsIndividuel,
                'anomaly_bureaux_votes' => (int) $anomalyVotes,
            ],
            'national_results' => $nationalResults,
            'status_breakdown' => $statusBreakdown,
            'alerts' => $alerts,
        ]);
    }
}
