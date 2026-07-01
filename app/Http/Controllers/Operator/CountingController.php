<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\VoteOption;
use App\Models\VoteLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class CountingController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $bureau = $user->bureauVote;

        if (!$bureau) {
            abort(403, 'Aucun bureau assigné');
        }

        // Vérifier que le bureau est en statut comptable
        if (!in_array($bureau->status, ['pending', 'counting'])) {
            return Inertia::render('Operator/Comptage', [
                'bureau' => $bureau,
                'locked' => true,
                'candidates' => [],
                'blanc_nul' => [],
            ]);
        }

        // Passer en counting si pending
        if ($bureau->status === 'pending') {
            $bureau->update(['status' => 'counting']);
        }

        // Compteurs en temps réel
        $options = VoteOption::orderBy('ordre_affichage')->get()->map(function ($opt) use ($bureau) {
            $plus = VoteLog::where('bureau_vote_id', $bureau->id)
                ->where('vote_option_id', $opt->id)
                ->where('action', '+1')
                ->count();
            $minus = VoteLog::where('bureau_vote_id', $bureau->id)
                ->where('vote_option_id', $opt->id)
                ->where('action', '-1')
                ->count();

            return [
                'id'              => $opt->id,
                'nom'             => $opt->nom,
                'type'            => $opt->type,
                'photo'           => $opt->photo,
                'ordre_affichage' => $opt->ordre_affichage,
                'count'           => $plus - $minus,
            ];
        });

        return Inertia::render('Operator/Comptage', [
            'bureau' => $bureau,
            'locked' => false,
            'candidates' => $options->where('type', 'candidat')->values(),
            'blanc_nul' => $options->whereIn('type', ['blanc', 'nul'])->values(),
        ]);
    }

    /**
     * Enregistrement d'un vote (+1 ou -1)
     * Avec anti-double-clic : lock par user+option pendant 500ms
     */
    public function vote(Request $request)
    {
        $validated = $request->validate([
            'vote_option_id' => 'required|exists:vote_options,id',
            'action' => 'required|in:+1,-1',
        ]);

        $user = auth()->user();
        $bureau = $user->bureauVote;

        if (!$bureau) {
            return back()->with('error', 'Aucun bureau assigné');
        }

        if (!in_array($bureau->status, ['pending', 'counting'])) {
            return back()->with('error', 'Le bureau n\'est plus en phase de comptage');
        }

        // Anti-double-clic : clé unique par user+option
        $lockKey = "vote_lock_{$user->id}_{$validated['vote_option_id']}";
        if (Cache::has($lockKey)) {
            return response()->json(['error' => 'Clic trop rapide'], 429);
        }
        Cache::put($lockKey, true, 0.5); // 500ms

        // Transaction + insertion atomique
        DB::transaction(function () use ($validated, $bureau, $user) {
            // Vérification -1 : ne pas aller en négatif
            if ($validated['action'] === '-1') {
                $plus = VoteLog::where('bureau_vote_id', $bureau->id)
                    ->where('vote_option_id', $validated['vote_option_id'])
                    ->where('action', '+1')
                    ->count();
                $minus = VoteLog::where('bureau_vote_id', $bureau->id)
                    ->where('vote_option_id', $validated['vote_option_id'])
                    ->where('action', '-1')
                    ->count();
                if ($minus >= $plus) {
                    throw new \Exception('Impossible : le compteur ne peut pas être négatif');
                }
            }

            VoteLog::create([
                'bureau_vote_id' => $bureau->id,
                'vote_option_id' => $validated['vote_option_id'],
                'user_id' => $user->id,
                'action' => $validated['action'],
                'created_at' => now(),
            ]);
        });

        // Retourner le compteur mis à jour
        $plus = VoteLog::where('bureau_vote_id', $bureau->id)
            ->where('vote_option_id', $validated['vote_option_id'])
            ->where('action', '+1')
            ->count();
        $minus = VoteLog::where('bureau_vote_id', $bureau->id)
            ->where('vote_option_id', $validated['vote_option_id'])
            ->where('action', '-1')
            ->count();

        return response()->json([
            'success' => true,
            'count' => $plus - $minus,
        ]);
    }
}
