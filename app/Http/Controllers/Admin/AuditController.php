<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BulletinLog;
use App\Models\BureauVote;
use App\Models\User;
use App\Models\VoteLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditController extends Controller
{

   public function index(Request $request)
    {
        $query = VoteLog::with(['bureau', 'voteOption', 'user'])
            // 🛡️ EXCLUSION UNIQUEMENT DES LOGS D'ANNULATION (RESET)
            // On masque les compensations système, mais on GARDE les votes restaurés (is_restored = true) 
            // car ils représentent l'état valide et actuel des données.
            ->where(function ($q) {
                $q->whereNull('is_reset')->orWhere('is_reset', false);
            })
            ->orderBy('created_at', 'desc');

        // ── Filtres existants ──────────────────────────────────────────────
        if ($request->filled('bureau_id')) {
            $query->where('bureau_vote_id', $request->bureau_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }
        if ($request->filled('procuration')) {
            $query->where('is_procuration', $request->procuration === '1');
        }

        $logs = $query->paginate(50)->withQueryString();

        $logs->getCollection()->transform(function ($log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'quantity' => $log->quantity,
                'is_procuration' => $log->is_procuration,
                'is_restored' => $log->is_restored ?? false, // 🆕 Utile pour l'affichage frontend (badge "Restauré")
                'created_at' => Carbon::parse($log->created_at)->format('d/m/Y H:i:s'),
                'bureau' => $log->bureau ? [
                    'code' => $log->bureau->code,
                    'nom' => $log->bureau->nom,
                ] : null,
                'option' => $log->voteOption?->nom,
                'user' => $log->user?->name,
            ];
        });

        return Inertia::render('Admin/Audit/Index', [
            'logs' => $logs,
            'filters' => $request->only(['bureau_id', 'user_id', 'action', 'date_from', 'date_to', 'procuration']),
            'bureaux' => BureauVote::orderBy('code')->get(['id', 'code', 'nom']),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function bulletins(Request $request)
    {
        $query = BulletinLog::with(['bureau', 'user'])
            // 🛡️ EXCLUSION UNIQUEMENT DES LOGS D'ANNULATION (RESET)
            ->where(function ($q) {
                $q->whereNull('is_reset')->orWhere('is_reset', false);
            })
            ->orderBy('created_at', 'desc');

        // ── Filtres existants ──────────────────────────────────────────────
        if ($request->filled('bureau_id')) {
            $query->where('bureau_vote_id', $request->bureau_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }
        if ($request->filled('manuel')) {
            $query->where('is_manuel', $request->manuel === '1');
        }

        $logs = $query->paginate(50)->withQueryString();

        $logs->getCollection()->transform(function ($log) {
            return [
                'id'          => $log->id,
                'quantity'    => $log->quantity,
                'action'      => $log->action,
                'is_manuel'   => $log->is_manuel,
                'is_restored' => $log->is_restored ?? false, // 🆕 Utile pour l'affichage frontend
                'created_at'  => Carbon::parse($log->created_at)->format('d/m/Y H:i:s'),
                'bureau'      => $log->bureau ? [
                    'code' => $log->bureau->code,
                    'nom'  => $log->bureau->nom,
                ] : null,
                'user'        => $log->user?->name,
            ];
        });

        return Inertia::render('Admin/Audit/Bulletins', [
            'logs'    => $logs,
            'filters' => $request->only(['bureau_id', 'user_id', 'action', 'date_from', 'date_to', 'manuel']),
            'bureaux' => BureauVote::orderBy('code')->get(['id', 'code', 'nom']),
            'users'   => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
