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

    // Vérification PV
    Route::get('pv', [PvEntryController::class, 'index'])->name('pv.index');
    Route::post('pv', [PvEntryController::class, 'store'])->name('pv.store');

    // Clôture
    Route::get('cloture', [CloseController::class, 'index'])->name('cloture.index');
    Route::post('cloture', [CloseController::class, 'close'])->name('cloture.close');
});
