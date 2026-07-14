<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\BulletinImage;
use App\Models\BulletinLog;
use App\Models\VoteLog;
use App\Models\VoteOption;
use App\Models\VoteReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
                'bulletin_count' => 0,
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
            'bulletin_count' => $this->currentBulletinCount($bureau->id),
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

    public function uploadBulletinImage(Request $request)
    {
        $user = auth()->user();
        $bureau = $user->bureauVote;

        if (!$bureau) {
            return response()->json([
                'error' => 'Aucun bureau assigné'
            ], 403);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        $filename = 'compteur_' . $bureau->id . '_' . now()->format('Ymd_His') . '_' . uniqid() . '.jpg';


        $path = $request->file('image')->storeAs(
            'compteurs_scans/' . $bureau->id,
            $filename,
            'public'
        );

        $image = BulletinImage::create([
            'bureau_vote_id' => $bureau->id,
            'user_id'        => $user->id,
            'path'           => $path,
            'filename'       => $filename,
            'taken_at'       => now(),
        ]);

        return response()->json([
            'success'   => true,
            'message'   => 'Image enregistrée avec succès',
            'id'        => $image->id,
            'url'       => $image->url,
            'filename'  => $filename,
            'taken_at'  => $image->taken_at->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Incrémente/décrémente le compteur de bulletins dépouillés (clic unitaire).
     * Même logique anti-double-clic que vote().
     */
    public function bulletinVote(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:+1,-1',
        ]);

        $user = auth()->user();
        $bureau = $user->bureauVote;

        if (!$bureau) {
            return response()->json(['error' => 'Aucun bureau assigné'], 403);
        }

        if (!in_array($bureau->status, ['pending', 'counting'])) {
            return response()->json(['error' => 'Le bureau n\'est plus en phase de comptage'], 403);
        }

        $lockKey = "bulletin_lock_{$user->id}_{$bureau->id}";
        if (Cache::has($lockKey)) {
            return response()->json(['error' => 'Clic trop rapide'], 429);
        }
        Cache::put($lockKey, true, 0.5);

        DB::transaction(function () use ($validated, $bureau, $user) {
            if ($validated['action'] === '-1') {
                $current = $this->currentBulletinCount($bureau->id);
                if ($current <= 0) {
                    throw new \Exception('Impossible : le compteur de bulletins ne peut pas être négatif');
                }
            }

            BulletinLog::create([
                'bureau_vote_id' => $bureau->id,
                'user_id'        => $user->id,
                'action'         => $validated['action'],
                'quantity'       => 1,
                'is_manuel'      => false,
                'created_at'     => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'count' => $this->currentBulletinCount($bureau->id),
        ]);
    }

    /**
     * Saisie manuelle groupée du nombre de bulletins (ex: comptage par paquet de 50).
     */
    public function bulletinVoteManuel(Request $request)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:9999',
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
            BulletinLog::create([
                'bureau_vote_id' => $bureau->id,
                'user_id'        => $user->id,
                'action'         => '+1',
                'quantity'       => $validated['quantity'],
                'is_manuel'      => true,
                'created_at'     => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'count' => $this->currentBulletinCount($bureau->id),
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

    /**
     * Calcule le compteur courant de bulletins dépouillés pour un bureau.
     */
    private function currentBulletinCount(int $bureauId): int
    {
        $plus = BulletinLog::where('bureau_vote_id', $bureauId)
            ->where('action', '+1')
            ->sum('quantity');
        $minus = BulletinLog::where('bureau_vote_id', $bureauId)
            ->where('action', '-1')
            ->sum('quantity');

        return $plus - $minus;
    }

    public function resetVotes(Request $request)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $user = auth()->user();
        $bureau = $user->bureauVote;

        if (!$bureau) {
            return response()->json(['error' => 'Aucun bureau assigné'], 403);
        }

        if (!in_array($bureau->status, ['pending', 'counting', 'anomaly'])) {
            return response()->json([
                'error' => 'Le comptage ne peut plus être réinitialisé une fois le PV saisi.',
            ], 403);
        }

        $snapshot = ['candidates' => [], 'bulletin_count' => 0];

        DB::transaction(function () use ($bureau, $user, $validated, &$snapshot) {
            // 1. Compenser chaque option candidat à zéro, en séparant normal / procuration
            $options = VoteOption::all();
            foreach ($options as $opt) {
                $normalPlus = VoteLog::where('bureau_vote_id', $bureau->id)
                    ->where('vote_option_id', $opt->id)
                    ->where('action', '+1')
                    ->where('is_procuration', false)
                    ->sum('quantity');
                $normalMinus = VoteLog::where('bureau_vote_id', $bureau->id)
                    ->where('vote_option_id', $opt->id)
                    ->where('action', '-1')
                    ->where('is_procuration', false)
                    ->sum('quantity');
                $normalCount = $normalPlus - $normalMinus;

                $procPlus = VoteLog::where('bureau_vote_id', $bureau->id)
                    ->where('vote_option_id', $opt->id)
                    ->where('action', '+1')
                    ->where('is_procuration', true)
                    ->sum('quantity');
                $procMinus = VoteLog::where('bureau_vote_id', $bureau->id)
                    ->where('vote_option_id', $opt->id)
                    ->where('action', '-1')
                    ->where('is_procuration', true)
                    ->sum('quantity');
                $procCount = $procPlus - $procMinus;

                $snapshot['candidates'][$opt->id] = $normalCount + $procCount;

                if ($normalCount !== 0) {
                    VoteLog::create([
                        'bureau_vote_id' => $bureau->id,
                        'vote_option_id' => $opt->id,
                        'user_id'        => $user->id,
                        'action'         => $normalCount > 0 ? '-1' : '+1',
                        'quantity'       => abs($normalCount),
                        'is_procuration' => false,
                        'is_reset'       => true,
                        'created_at'     => now(),
                    ]);
                }

                if ($procCount !== 0) {
                    VoteLog::create([
                        'bureau_vote_id' => $bureau->id,
                        'vote_option_id' => $opt->id,
                        'user_id'        => $user->id,
                        'action'         => $procCount > 0 ? '-1' : '+1',
                        'quantity'       => abs($procCount),
                        'is_procuration' => true,
                        'is_reset'       => true,
                        'created_at'     => now(),
                    ]);
                }
            }

            // 2. Compenser le compteur de bulletins à zéro (inchangé)
            $bulletinCount = BulletinLog::currentCountForBureau($bureau->id);
            $snapshot['bulletin_count'] = $bulletinCount;

            if ($bulletinCount !== 0) {
                BulletinLog::create([
                    'bureau_vote_id' => $bureau->id,
                    'user_id'        => $user->id,
                    'action'         => $bulletinCount > 0 ? '-1' : '+1',
                    'quantity'       => abs($bulletinCount),
                    'is_manuel'      => true,
                    'created_at'     => now(),
                ]);
            }

            // 3. Snapshot de l'ancien état, pour audit/traçabilité (photos non touchées)
            VoteReset::create([
                'bureau_vote_id' => $bureau->id,
                'user_id'        => $user->id,
                'snapshot'       => $snapshot,
                'reason'         => $validated['reason'] ?? null,
                'created_at'     => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Comptage réinitialisé. Vous pouvez ressaisir les votes.',
        ]);
    }
}
