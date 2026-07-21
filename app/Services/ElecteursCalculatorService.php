<?php

namespace App\Services;

use App\Models\BulletinLog;
use App\Models\VoteLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ElecteursCalculatorService
{
    /**
     * Reprend la même logique que AuditController::electeurs(), mais
     * bureau par bureau et sans le détail des sessions — juste les totaux.
     */
    public function pourBureau(int $bureauId, int $seuilSecondes = 5): array
    {
        $excludeReset = function ($query) {
            $query->where(function ($q) {
                $q->whereNull('is_reset')->orWhere('is_reset', false);
            });
        };

        $allBulletinLogs = BulletinLog::where('bureau_vote_id', $bureauId)
            ->where('is_manuel', false)
            ->tap($excludeReset)
            ->orderBy('created_at')
            ->get(['id', 'action', 'created_at']);

        if ($allBulletinLogs->isEmpty()) {
            return ['individuels' => 0, 'procuration' => 0, 'total' => 0];
        }

        // Reconstruit la pile des clics +1 réellement valides (annule sur -1, LIFO)
        $stack = [];
        foreach ($allBulletinLogs as $log) {
            if ($log->action === '+1') {
                $stack[] = $log;
            } elseif ($log->action === '-1' && count($stack) > 0) {
                array_pop($stack);
            }
        }
        $clics = collect($stack)->values();

        if ($clics->isEmpty()) {
            return ['individuels' => 0, 'procuration' => 0, 'total' => 0];
        }

        $electeursIndividuels = 0;
        $electeursProcuration = 0;

        foreach ($clics as $i => $clic) {
            $debut = $clic->created_at;
            $fin   = $clics[$i + 1]->created_at ?? now()->addYears(10);

            $votesProcuration = VoteLog::where('bureau_vote_id', $bureauId)
                ->where('is_procuration', true)
                ->tap($excludeReset)
                ->whereBetween('created_at', [$debut, $fin])
                ->orderBy('created_at')
                ->get(['quantity', 'created_at']);

            if ($votesProcuration->isNotEmpty()) {
                $bulletinsDetectes = $this->detecterBulletinsProcuration($votesProcuration, $seuilSecondes);
                $electeursProcuration += (int) $bulletinsDetectes->sum('quantity');
            } else {
                $electeursIndividuels += 1;
            }
        }

        return [
            'individuels' => $electeursIndividuels,
            'procuration' => $electeursProcuration,
            'total'       => $electeursIndividuels + $electeursProcuration,
        ];
    }

    /**
     * Identique à AuditController::detecterBulletinsProcuration(), version allégée
     * (pas besoin des lignes détaillées ici, seulement les paquets quantity/dernier).
     */
    private function detecterBulletinsProcuration(Collection $votesProcuration, int $seuilSecondes): Collection
    {
        $votesTries = $votesProcuration->sortBy('created_at')->values();
        $bulletins  = collect();
        $courant    = null;

        foreach ($votesTries as $vote) {
            if ($courant === null) {
                $courant = ['quantity' => $vote->quantity, 'dernier' => $vote->created_at];
                continue;
            }

            $gap = Carbon::parse($vote->created_at)->diffInSeconds($courant['dernier']);

            if ((int) $vote->quantity === (int) $courant['quantity'] && $gap <= $seuilSecondes) {
                $courant['dernier'] = $vote->created_at;
            } else {
                $bulletins->push($courant);
                $courant = ['quantity' => $vote->quantity, 'dernier' => $vote->created_at];
            }
        }
        if ($courant !== null) {
            $bulletins->push($courant);
        }

        return $bulletins;
    }
}