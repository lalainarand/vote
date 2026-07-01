<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VoteLog;
use App\Models\VoteOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CandidateController extends Controller
{
    public function index()
    {
        $candidates = VoteOption::where('type', 'candidat')
            ->orderBy('ordre_affichage')
            ->get()
            ->map(function ($option) {
                $plus = VoteLog::where('vote_option_id', $option->id)
                    ->where('action', '+1')
                    ->sum('quantity');

                $minus = VoteLog::where('vote_option_id', $option->id)
                    ->where('action', '-1')
                    ->sum('quantity');

                $procuration = VoteLog::where('vote_option_id', $option->id)
                    ->where('is_procuration', true)
                    ->sum('quantity');

                return [
                    'id'              => $option->id,
                    'nom'             => $option->nom,
                    'photo'           => $option->photo,
                    'ordre_affichage' => $option->ordre_affichage,
                    'vote_logs_count' => $plus - $minus,
                    'procuration'     => (int) $procuration,
                    'has_logs'        => VoteLog::where('vote_option_id', $option->id)->exists(),
                ];
            });

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
            'photo'           => 'nullable|image|mimes:jpg,jpeg,png,webp',
        ]);

        $validated['type'] = 'candidat';

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('candidats', 'public');
            $validated['photo'] = $path;
        }

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
        $validated = $request->validate([
            'nom'             => 'required|string|max:255',
            'ordre_affichage' => 'required|integer|min:1',
            'photo'           => 'nullable|image|mimes:jpg,jpeg,png,webp',
        ]);

        if ($request->hasFile('photo')) {
            // supprimer l'ancienne photo si elle existe
            if ($candidat->photo) {
                Storage::disk('public')->delete($candidat->photo);
            }
            $validated['photo'] = $request->file('photo')->store('candidats', 'public');
        }

        $candidat->update($validated);

        return redirect()
            ->route('admin.candidats.index')
            ->with('success', 'Candidat mis à jour avec succès');
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
