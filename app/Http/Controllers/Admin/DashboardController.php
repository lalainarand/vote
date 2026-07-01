<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BureauResult;
use App\Models\BureauVote;
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
        $adminPvBureaux = BureauVote::where('status', 'pv_admin')->count();

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

        // Répartition des statuts
        $statusBreakdown = BureauVote::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Alertes
        $alerts = [];
        if ($anomalyBureaux > 0) {
            $alerts[] = [
                'type' => 'error',
                'message' => "$anomalyBureaux bureau(x) en anomalie",
                'link' => route('admin.bureaux.index', ['status' => 'anomaly']),
            ];
        }
        if ($adminPvBureaux > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "$adminPvBureaux bureau(x) avec saisie PV admin",
                'link' => route('admin.bureaux.index', ['status' => 'pv_admin']),
            ];
        }

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_bureaux' => $totalBureaux,
                'validated_bureaux' => $validatedBureaux,
                'anomaly_bureaux' => $anomalyBureaux,
                'admin_pv_bureaux' => $adminPvBureaux,
                'progression' => $progression,
                'total_procuration' => (int) $totalProcurationNational,
            ],
            'national_results' => $nationalResults,
            'status_breakdown' => $statusBreakdown,
            'alerts' => $alerts,
        ]);
    }
}
