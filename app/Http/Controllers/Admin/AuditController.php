<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BulletinLog;
use App\Models\BureauVote;
use App\Models\User;
use App\Models\VoteLog;
use App\Models\VoteOption;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditController extends Controller
{

    public function index(Request $request)
    {
        $query = VoteLog::with(['bureau', 'voteOption', 'user'])
            ->where(function ($q) {
                $q->whereNull('is_reset')->orWhere('is_reset', false);
            })
            ->orderBy('created_at', 'desc');

        // ── Filtres existants ──────────────────────────────────────────────
        if ($request->filled('bureau_id')) {
            $query->where('bureau_vote_id', $request->bureau_id);
        }
        if ($request->filled('option_id')) {          // 🆕 remplace user_id
            $query->where('vote_option_id', $request->option_id);
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

        $stats = [
            'total'            => (clone $query)->sum('quantity'),
            'procuration'      => (clone $query)->where('is_procuration', true)->sum('quantity'),
            'hors_procuration' => (clone $query)->where('is_procuration', false)->sum('quantity'),
            'plus'             => (clone $query)->where('action', '+1')->sum('quantity'),
            'minus'            => (clone $query)->where('action', '-1')->sum('quantity'),
            'count'            => (clone $query)->count(),
        ];

        $logs = $query->paginate(50)->withQueryString();

        $logs->getCollection()->transform(function ($log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'quantity' => $log->quantity,
                'is_procuration' => $log->is_procuration,
                'is_restored' => $log->is_restored ?? false,
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
            'logs'      => $logs,
            'stats'     => $stats,
            'filters'   => $request->only(['bureau_id', 'option_id', 'action', 'date_from', 'date_to', 'procuration']),
            'bureaux'   => BureauVote::orderBy('code')->get(['id', 'code', 'nom']),
            'candidats' => VoteOption::orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    public function bulletins(Request $request)
    {
        $query = BulletinLog::with(['bureau', 'user'])
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

        // Dans AuditController@bulletins, ajoute une clé 'net' dans $stats :
        $stats = [
            // 🎯 VRAI NOMBRE D'ÉLECTEURS (Net : +1 moins -1)
            'net_electeurs' => (clone $query)->where('action', '+1')->sum('quantity')
                - (clone $query)->where('action', '-1')->sum('quantity'),

            // Nombre total de lignes (opérations) dans la table
            'total_logs'    => (clone $query)->count(),

            // Somme brute de toutes les quantités (pour info)
            'total_quantity' => (clone $query)->sum('quantity'),

            // Détails
            'manuel'        => (clone $query)->where('is_manuel', true)->sum('quantity'),
            'unitaire'      => (clone $query)->where('is_manuel', false)->sum('quantity'),
            'plus'          => (clone $query)->where('action', '+1')->sum('quantity'),
            'minus'         => (clone $query)->where('action', '-1')->sum('quantity'),
        ];

        $logs = $query->paginate(50)->withQueryString();

        $logs->getCollection()->transform(function ($log) {
            return [
                'id'          => $log->id,
                'quantity'    => $log->quantity,
                'action'      => $log->action,
                'is_manuel'   => $log->is_manuel,
                'is_restored' => $log->is_restored ?? false,
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
            'stats'   => $stats, // 🆕
            'filters' => $request->only(['bureau_id', 'user_id', 'action', 'date_from', 'date_to', 'manuel']),
            'bureaux' => BureauVote::orderBy('code')->get(['id', 'code', 'nom']),
            'users'   => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

public function electeurs(Request $request)
{
    $excludeReset = function ($query) {
        $query->where(function ($q) {
            $q->whereNull('is_reset')->orWhere('is_reset', false);
        });
    };

    $bureauQuery = BureauVote::query();
    if ($request->filled('bureau_id')) {
        $bureauQuery->where('id', $request->bureau_id);
    }
    $bureaux = $bureauQuery->orderBy('code')->get(['id', 'code', 'nom']);

    $allSessions = collect();
    $totalAnnulations = 0;

    foreach ($bureaux as $bureau) {

        $allBulletinLogs = BulletinLog::where('bureau_vote_id', $bureau->id)
            ->where('is_manuel', false)
            ->tap($excludeReset)
            ->orderBy('created_at')
            ->get(['id', 'user_id', 'action', 'created_at']);

        if ($allBulletinLogs->isEmpty()) {
            continue;
        }

        $stack = [];
        foreach ($allBulletinLogs as $log) {
            if ($log->action === '+1') {
                $stack[] = $log;
            } elseif ($log->action === '-1' && count($stack) > 0) {
                array_pop($stack);
                $totalAnnulations++;
            }
        }
        $clics = collect($stack)->values();

        if ($clics->isEmpty()) {
            continue;
        }

        // 🆕 UNE session par clic (= UN bulletin), quel que soit le nombre
        // de quantités distinctes détectées dedans. Le total de sessions
        // reste donc toujours égal au nombre net de clics (2081).
        foreach ($clics as $i => $clic) {
            $debut = $clic->created_at;
            $fin   = $clics[$i + 1]->created_at ?? now()->addYears(10);

            $votesProcuration = VoteLog::where('bureau_vote_id', $bureau->id)
                ->where('is_procuration', true)
                ->tap($excludeReset)
                ->whereBetween('created_at', [$debut, $fin])
                ->get(['quantity', 'user_id', 'vote_option_id']);

            if ($votesProcuration->isNotEmpty()) {
                $parQuantite = $votesProcuration->groupBy('quantity');

                $allSessions->push([
                    'bureau_id'            => $bureau->id,
                    'bureau'               => $bureau->code . ' — ' . $bureau->nom,
                    'user_id'              => $clic->user_id,
                    'type'                 => 'procuration',
                    // 🆕 Somme des quantités distinctes = électeurs de CETTE session (1 bulletin/clic)
                    'electeurs'            => (int) $parQuantite->keys()->sum(),
                    'nb_candidats'         => $votesProcuration->count(),
                    'nb_quantites_fenetre' => $parQuantite->count(), // info : combien de valeurs distinctes regroupées ici
                    'created_at'           => $debut,
                ]);
            } else {
                $allSessions->push([
                    'bureau_id'            => $bureau->id,
                    'bureau'               => $bureau->code . ' — ' . $bureau->nom,
                    'user_id'              => $clic->user_id,
                    'type'                 => 'individuel',
                    'electeurs'            => 1,
                    'nb_candidats'         => null,
                    'nb_quantites_fenetre' => 1,
                    'created_at'           => $debut,
                ]);
            }
        }
    }

    $totalElecteurs             = $allSessions->sum('electeurs');
    $totalElecteursIndividuels  = $allSessions->where('type', 'individuel')->sum('electeurs');
    $totalElecteursProcuration  = $allSessions->where('type', 'procuration')->sum('electeurs');

    // 🎯 Correspond maintenant exactement au nombre net de clics BulletinLog (2081)
    $nbBulletinsTotal        = $allSessions->count();
    $nbBulletinsIndividuels  = $allSessions->where('type', 'individuel')->count();
    $nbBulletinsProcuration  = $allSessions->where('type', 'procuration')->count();

    $nbFenetresMultiQuantites = $allSessions->where('nb_quantites_fenetre', '>', 1)->count();

    $sorted  = $allSessions->sortByDesc('created_at')->values();
    $page    = (int) $request->input('page', 1);
    $perPage = 50;
    $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
        $sorted->forPage($page, $perPage)->values(),
        $sorted->count(),
        $perPage,
        $page,
        ['path' => $request->url(), 'query' => $request->query()]
    );

    return Inertia::render('Admin/Audit/Electeurs', [
        'sessions' => $paginated,
        'stats' => [
            'total_electeurs'              => $totalElecteurs,
            'total_electeurs_individuels'  => $totalElecteursIndividuels,
            'total_electeurs_procuration'  => $totalElecteursProcuration,
            'nb_bulletins_total'           => $nbBulletinsTotal,       // 🎯 doit valoir 2081
            'nb_bulletins_individuels'     => $nbBulletinsIndividuels,
            'nb_bulletins_procuration'     => $nbBulletinsProcuration,
            'nb_annulations'               => $totalAnnulations,
            'nb_fenetres_multi_bulletins'  => $nbFenetresMultiQuantites,
        ],
        'filters' => $request->only(['bureau_id']),
        'bureaux' => BureauVote::orderBy('code')->get(['id', 'code', 'nom']),
    ]);
}
}
