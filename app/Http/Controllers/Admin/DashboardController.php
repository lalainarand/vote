<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BureauVote;
use App\Models\VoteOption;
use App\Models\BureauResult;
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
        $nationalResults = VoteOption::with(['bureauResults' => function($q) {
            $q->whereHas('bureau', fn($q2) => $q2->where('status', 'validated'));
        }])->get()->map(function($option) {
            return [
                'id' => $option->id,
                'nom' => $option->nom,
                'type' => $option->type,
                'total' => $option->bureauResults->sum('count'),
            ];
        });

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
            ],
            'national_results' => $nationalResults,
            'status_breakdown' => $statusBreakdown,
            'alerts' => $alerts,
        ]);
    }
}