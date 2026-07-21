<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\BureauController;
use App\Http\Controllers\Admin\CandidateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ResultController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Operator\DashboardController as OperatorDashboardController;
use App\Http\Controllers\Operator\CountingController;
use App\Http\Controllers\Operator\PvEntryController;
use App\Http\Controllers\Operator\CloseController;

// ── Page d'accueil ────────────────────────────────────────────────────────
Route::get('/', fn() => redirect()->route('redirect'));

// ── Redirection post-login selon rôle ─────────────────────────────────────
Route::middleware('auth')->get('/redirect', function () {
    return auth()->user()->hasRole('admin')
        ? redirect()->route('admin.dashboard')
        : redirect()->route('operator.dashboard');
})->name('redirect');

// ── Routes Admin ──────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {

    // Dashboard
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Gestion bureaux
    Route::resource('bureaux', BureauController::class)
        ->parameters(['bureaux' => 'bureau']);
    Route::patch('bureaux/{bureau}/lock', [BureauController::class, 'lock'])->name('bureaux.lock');
    Route::patch('bureaux/{bureau}/unlock', [BureauController::class, 'unlock'])->name('bureaux.unlock');
    Route::get('bureaux/{bureau}/pv-manuel', [BureauController::class, 'manualPv'])->name('bureaux.pv-manuel');
    Route::post('bureaux/{bureau}/pv-manuel', [BureauController::class, 'storeManualPv'])->name('bureaux.pv-manuel.store');
    Route::get('/bureaux/{bureau}/photos', [BureauController::class, 'photos'])->name('admin.bureaux.photos');

    // Gestion candidats
    Route::resource('candidats', CandidateController::class);

    // Gestion utilisateurs
    Route::resource('users', UserController::class);

    // Résultats globaux
    Route::get('resultats', [ResultController::class, 'index'])->name('resultats.index');
    Route::get('resultats/export', [ResultController::class, 'export'])->name('resultats.export');

    // Audit log
    Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
    Route::get('bulletins/audit', [AuditController::class, 'bulletins'])->name('audit.bulletins');
    Route::get('electeurs/audit', [AuditController::class, 'electeurs'])->name('audit.electeurs');
    Route::get('/debug/procuration-collisions', function () {
    $collisions = \App\Models\VoteLog::where('is_procuration', true)
        ->where(function ($q) {
            $q->whereNull('is_reset')->orWhere('is_reset', false);
        })
        ->orderBy('bureau_vote_id')
        ->orderBy('created_at')
        ->get(['bureau_vote_id', 'quantity', 'created_at'])
        ->groupBy(fn ($v) => $v->bureau_vote_id . '-' . $v->quantity)
        ->filter(fn ($g) => $g->count() > 1)
        ->map(function ($g) {
            $dates = $g->pluck('created_at')->values();
            $ecarts = [];
            for ($i = 1; $i < $dates->count(); $i++) {
                $ecarts[] = \Carbon\Carbon::parse($dates[$i])
                    ->diffInSeconds(\Carbon\Carbon::parse($dates[$i - 1]));
            }
            return [
                'nb_occurrences' => $g->count(),
                'ecarts_secondes' => $ecarts,
                'dates' => $dates->map(fn($d) => (string) $d)->all(),
            ];
        });

    // Ne garder que les cas où au moins un écart dépasse le seuil (ex: 60s)
    $suspects = $collisions->filter(function ($c) {
        return collect($c['ecarts_secondes'])->contains(fn ($e) => $e > 60);
    });

    dd([
        'total_combinaisons_avec_doublons' => $collisions->count(),
        'suspects_ecart_plus_de_60s'       => $suspects->count(),
        'detail_suspects'                  => $suspects,
    ]);
});
});

// ── Routes Opérateur ──────────────────────────────────────────────────────
Route::middleware(['auth', 'role:operator'])->prefix('operator')->name('operator.')->group(function () {

    // Dashboard
    Route::get('/dashboard', [OperatorDashboardController::class, 'index'])->name('dashboard');

    // Comptage
    Route::get('comptage', [CountingController::class, 'index'])->name('comptage.index');
    Route::post('comptage/vote', [CountingController::class, 'vote'])->name('comptage.vote');
    Route::post('comptage/vote-manuel', [CountingController::class, 'voteManuel'])
        ->name('comptage.vote-manuel');
    Route::post('comptage/bulletin', [CountingController::class, 'bulletinVote'])
        ->name('comptage.bulletin');
    Route::post('comptage/bulletin-manuel', [CountingController::class, 'bulletinVoteManuel'])
        ->name('comptage.bulletin-manuel');
    Route::post('/comptage/upload-bulletin-image', [CountingController::class, 'uploadBulletinImage'])
        ->name('operator.comptage.upload-bulletin-image');
    Route::post('/comptage/reset', [CountingController::class, 'resetVotes'])->name('comptage.reset');
    Route::post('/comptage/restore-reset/{voteReset}', [CountingController::class, 'restoreReset'])->name('operator.comptage.restore-reset');

    // Vérification PV
    Route::get('pv', [PvEntryController::class, 'index'])->name('pv.index');
    Route::post('pv', [PvEntryController::class, 'store'])->name('pv.store');

    // Clôture
    Route::get('cloture', [CloseController::class, 'index'])->name('cloture.index');
    Route::post('cloture', [CloseController::class, 'close'])->name('cloture.close');
});
