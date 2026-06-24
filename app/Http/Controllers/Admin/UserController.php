<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\BureauVote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Liste des utilisateurs avec filtre par rôle
     */
    public function index(Request $request)
    {
        $query = User::with(['bureauVote', 'roles'])
            ->withCount('voteLogs');

        if ($request->filled('role')) {
            $query->role($request->role); // scope Spatie
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }

        $users = $query->orderBy('name')->paginate(15)->withQueryString();

        // Transformer pour Inertia
        $users->getCollection()->transform(function ($user) {
            return [
                'id'              => $user->id,
                'name'            => $user->name,
                'email'           => $user->email,
                'role'            => $user->roles->first()?->name ?? 'none',
                'bureau'          => $user->bureauVote ? [
                    'id'   => $user->bureauVote->id,
                    'code' => $user->bureauVote->code,
                    'nom'  => $user->bureauVote->nom,
                ] : null,
                'vote_logs_count' => $user->vote_logs_count,
                'created_at'      => $user->created_at?->format('d/m/Y H:i'),
            ];
        });

        return Inertia::render('Admin/Users/Index', [
            'users'   => $users,
            'filters' => $request->only(['role', 'search']),
        ]);
    }

    /**
     * Formulaire de création
     */
    public function create()
    {
        // Bureaux disponibles (sans opérateur assigné)
        $availableBureaux = BureauVote::whereDoesntHave('users')
            ->orderBy('code')
            ->get(['id', 'code', 'nom']);

        return Inertia::render('Admin/Users/Create', [
            'available_bureaux' => $availableBureaux,
        ]);
    }

    /**
     * Enregistrement
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|max:255|unique:users,email',
            'password'       => ['required', Password::min(8)],
            'role'           => ['required', Rule::in(['admin', 'operator'])],
            'bureau_vote_id' => [
                Rule::requiredIf($request->role === 'operator'),
                'nullable',
                'exists:bureaux_vote,id',
            ],
        ]);

        // Contrainte métier #1 + #2 : un bureau = un opérateur
        if ($validated['role'] === 'operator') {
            $existingOperator = User::where('bureau_vote_id', $validated['bureau_vote_id'])
                ->role('operator')
                ->exists();

            if ($existingOperator) {
                return back()->withErrors([
                    'bureau_vote_id' => 'Ce bureau a déjà un opérateur assigné.',
                ])->withInput();
            }
        } else {
            $validated['bureau_vote_id'] = null;
        }

        $user = User::create([
            'name'           => $validated['name'],
            'email'          => $validated['email'],
            'password'       => Hash::make($validated['password']),
            'bureau_vote_id' => $validated['bureau_vote_id'] ?? null,
        ]);

        $user->assignRole($validated['role']);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Utilisateur créé avec succès');
    }

    /**
     * Formulaire d'édition
     */
    public function edit(User $user)
    {
        $user->load('bureauVote', 'roles');

        // Bureaux disponibles = tous SAUF celui déjà assigné à cet user
        $availableBureaux = BureauVote::where(function ($q) use ($user) {
            $q->whereDoesntHave('users')
              ->orWhere('id', $user->bureau_vote_id);
        })->orderBy('code')->get(['id', 'code', 'nom']);

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'role'           => $user->roles->first()?->name ?? 'operator',
                'bureau_vote_id' => $user->bureau_vote_id,
            ],
            'available_bureaux' => $availableBureaux,
        ]);
    }

    /**
     * Mise à jour
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password'       => ['nullable', Password::min(8)],
            'role'           => ['required', Rule::in(['admin', 'operator'])],
            'bureau_vote_id' => [
                Rule::requiredIf($request->role === 'operator'),
                'nullable',
                'exists:bureaux_vote,id',
            ],
        ]);

        // Vérification unicité bureau (sauf si c'est le même bureau)
        if ($validated['role'] === 'operator') {
            $existingOperator = User::where('bureau_vote_id', $validated['bureau_vote_id'])
                ->where('id', '!=', $user->id)
                ->role('operator')
                ->exists();

            if ($existingOperator) {
                return back()->withErrors([
                    'bureau_vote_id' => 'Ce bureau a déjà un autre opérateur assigné.',
                ])->withInput();
            }
        } else {
            $validated['bureau_vote_id'] = null;
        }

        // Mise à jour user
        $userData = [
            'name'           => $validated['name'],
            'email'          => $validated['email'],
            'bureau_vote_id' => $validated['bureau_vote_id'] ?? null,
        ];

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $user->update($userData);

        // Sync rôle Spatie
        $user->syncRoles([$validated['role']]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Utilisateur mis à jour');
    }

    /**
     * Suppression — Contrainte : impossible si vote_logs existe
     */
    public function destroy(User $user)
    {
        if ($user->voteLogs()->exists()) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Impossible de supprimer un utilisateur ayant participé à un comptage');
        }

        // Libérer le bureau avant suppression
        $user->update(['bureau_vote_id' => null]);
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Utilisateur supprimé');
    }
}