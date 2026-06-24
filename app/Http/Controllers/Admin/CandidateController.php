<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VoteOption;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CandidateController extends Controller
{
    public function index()
    {
        $candidates = VoteOption::where('type', 'candidat')
            ->orderBy('ordre_affichage')
            ->withCount('voteLogs')
            ->get();

        return Inertia::render('Admin/Candidats/Index', [
            'candidates' => $candidates,
        ]);
    }

    public function create()
    {
        $nextOrder = (VoteOption::where('type', 'candidat')->max('ordre_affichage') ?? 0) + 1;

        return Inertia::render('Admin/Candidats/Create', [
            'next_order' => $nextOrder,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom'             => 'required|string|max:255',
            'ordre_affichage' => 'required|integer|min:1',
        ]);

        $validated['type'] = 'candidat';

        VoteOption::create($validated);

        return redirect()
            ->route('admin.candidats.index')
            ->with('success', 'Candidat créé avec succès');
    }

    public function edit(VoteOption $candidat)
    {
        if ($candidat->type !== 'candidat') {
            abort(404);
        }

        return Inertia::render('Admin/Candidats/Edit', [
            'candidat' => $candidat,
        ]);
    }

    public function update(Request $request, VoteOption $candidat)
    {
        if ($candidat->type !== 'candidat') {
            abort(404);
        }

        $validated = $request->validate([
            'nom'             => 'required|string|max:255',
            'ordre_affichage' => 'required|integer|min:1',
        ]);

        $candidat->update($validated);

        return redirect()
            ->route('admin.candidats.index')
            ->with('success', 'Candidat mis à jour');
    }

    public function destroy(VoteOption $candidat)
    {
        if ($candidat->type !== 'candidat') {
            abort(404);
        }

        if ($candidat->voteLogs()->exists()) {
            return redirect()
                ->route('admin.candidats.index')
                ->with('error', 'Impossible de supprimer un candidat ayant déjà des votes enregistrés');
        }

        $candidat->delete();

        return redirect()
            ->route('admin.candidats.index')
            ->with('success', 'Candidat supprimé');
    }
}