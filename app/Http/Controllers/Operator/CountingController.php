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

        // Compteurs en temps réel (inclut les votes normaux + procurations via quantity)
        $options = VoteOption::orderBy('ordre_affichage')->get()->map(function ($opt) use ($bureau) {
            $plus = VoteLog::where('bureau_vote_id', $bureau->id)
                ->where('vote_option_id', $opt->id)
                ->where('action', '+1')
                ->sum('quantity');
            $minus = VoteLog::where('bureau_vote_id', $bureau->id)
                ->where('vote_option_id', $opt->id)
                ->where('action', '-1')
                ->sum('quantity');

            $procuration = VoteLog::where('bureau_vote_id', $bureau->id)
                ->where('vote_option_id', $opt->id)
                ->where('is_procuration', true)
                ->sum('quantity');

            return [
                'id'              => $opt->id,
                'nom'             => $opt->nom,
                'type'            => $opt->type,
                'photo'           => $opt->photo,
                'ordre_affichage' => $opt->ordre_affichage,
                'count'           => $plus - $minus,
                'procuration'     => $procuration,
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

        $lockKey = "vote_lock_{$user->id}_{$validated['vote_option_id']}";
        if (Cache::has($lockKey)) {
            return response()->json(['error' => 'Clic trop rapide'], 429);
        }
        Cache::put($lockKey, true, 0.5);

        DB::transaction(function () use ($validated, $bureau, $user) {
            if ($validated['action'] === '-1') {
                $plus = VoteLog::where('bureau_vote_id', $bureau->id)
                    ->where('vote_option_id', $validated['vote_option_id'])
                    ->where('action', '+1')
                    ->sum('quantity');
                $minus = VoteLog::where('bureau_vote_id', $bureau->id)
                    ->where('vote_option_id', $validated['vote_option_id'])
                    ->where('action', '-1')
                    ->sum('quantity');
                if ($minus >= $plus) {
                    throw new \Exception('Impossible : le compteur ne peut pas être négatif');
                }
            }

            VoteLog::create([
                'bureau_vote_id' => $bureau->id,
                'vote_option_id' => $validated['vote_option_id'],
                'user_id' => $user->id,
                'action' => $validated['action'],
                'quantity' => 1,
                'is_procuration' => false,
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'count' => $this->currentCount($bureau->id, $validated['vote_option_id']),
        ]);
    }

    /**
     * Saisie manuelle des votes par procuration.
     */
    public function voteManuel(Request $request)
    {
        $validated = $request->validate([
            'vote_option_id' => 'required|exists:vote_options,id',
            'quantity'       => 'required|integer|min:1|max:9999',
        ]);

        $user = auth()->user();
        $bureau = $user->bureauVote;

        if (!$bureau) {
            return response()->json(['error' => 'Aucun bureau assigné'], 403);
        }

        if (!in_array($bureau->status, ['pending', 'counting'])) {
            return response()->json(['error' => 'Le bureau n\'est plus en phase de comptage'], 403);
        }

        DB::transaction(function () use ($validated, $bureau, $user) {
            VoteLog::create([
                'bureau_vote_id' => $bureau->id,
                'vote_option_id' => $validated['vote_option_id'],
                'user_id'        => $user->id,
                'action'         => '+1',
                'quantity'       => $validated['quantity'],
                'is_procuration' => true,
                'created_at'     => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'count' => $this->currentCount($bureau->id, $validated['vote_option_id']),
        ]);
    }

    /**
     * Calcule le compteur courant (votes normaux + procurations) pour une option.
     */
    private function currentCount(int $bureauId, int $voteOptionId): int
    {
        $plus = VoteLog::where('bureau_vote_id', $bureauId)
            ->where('vote_option_id', $voteOptionId)
            ->where('action', '+1')
            ->sum('quantity');
        $minus = VoteLog::where('bureau_vote_id', $bureauId)
            ->where('vote_option_id', $voteOptionId)
            ->where('action', '-1')
            ->sum('quantity');

        return $plus - $minus;
    }
}
