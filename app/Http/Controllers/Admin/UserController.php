<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\BureauVote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class UserController extends Controller
{
    /**
     * Le mot de passe (qu'il soit saisi par l'admin ou auto-généré) ne peut contenir
     * que des chiffres, des lettres (majuscules/minuscules) et # * . " @ -
     */
    private const PASSWORD_REGEX = '/^[A-Za-z0-9#*."@\-]+$/';
    private const PASSWORD_HINT = 'Uniquement chiffres, lettres, et les symboles # * . " @ -';

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
                'is_active'       => (bool) $user->is_active,
                'is_approved'     => (bool) $user->is_approved,
                // Exposé explicitement (le modèle le cache par défaut) : uniquement
                // ici, pour l'écran admin de consultation des identifiants.
                'password_plain'  => $user->password_plain,
            ];
        });

        // Comptes en attente d'autorisation : mis en avant indépendamment des
        // filtres/pagination ci-dessus, pour que l'admin ne les manque jamais.
        $pendingUsers = User::with('roles')
            ->where('is_approved', false)
            ->orderBy('name')
            ->get()
            ->map(fn($user) => [
                'id'   => $user->id,
                'name' => $user->name,
                'role' => $user->roles->first()?->name ?? 'none',
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users'         => $users,
            'filters'       => $request->only(['role', 'search']),
            'pending_users' => $pendingUsers,
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
            'password'       => ['nullable', 'string', 'min:8', 'max:64', 'confirmed', 'regex:' . self::PASSWORD_REGEX],
            'role'           => ['required', Rule::in(['admin', 'operator'])],
            'bureau_vote_id' => [
                Rule::requiredIf($request->role === 'operator'),
                'nullable',
                'exists:bureaux_vote,id',
            ],
        ], [
            'password.regex' => self::PASSWORD_HINT,
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

        // Auto-génère un mot de passe conforme si l'admin n'en a pas saisi un.
        $plainPassword = $validated['password'] ?: User::generatePassword();

        $user = User::create([
            'name'           => $validated['name'],
            'email'          => $validated['email'],
            'password'       => Hash::make($plainPassword),
            'password_plain' => $plainPassword,
            'bureau_vote_id' => $validated['bureau_vote_id'] ?? null,
            'email_verified_at' => now(),
            // Un compte créé par un admin est actif d'emblée (l'admin peut le
            // désactiver ensuite depuis la liste s'il change d'avis).
            'is_active'      => true,
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
            'password'       => ['nullable', 'string', 'min:8', 'max:64', 'confirmed', 'regex:' . self::PASSWORD_REGEX],
            'role'           => ['required', Rule::in(['admin', 'operator'])],
            'bureau_vote_id' => [
                Rule::requiredIf($request->role === 'operator'),
                'nullable',
                'exists:bureaux_vote,id',
            ],
        ], [
            'password.regex' => self::PASSWORD_HINT,
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
            $userData['password_plain'] = $validated['password'];
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

    /**
     * Active / désactive un compte. Un compte désactivé ne peut plus se connecter
     * (message explicite au login) et est déconnecté immédiatement s'il avait déjà
     * une session ouverte.
     */
    public function toggleActive(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with(
            'success',
            $user->is_active ? 'Compte activé.' : 'Compte désactivé.'
        );
    }

    /**
     * Autorise / révoque l'accès d'un compte en attente. Tant que is_approved est
     * faux, l'utilisateur peut se connecter (identifiants + is_active corrects)
     * mais reste cantonné à la page d'attente (voir EnsureUserIsApproved).
     */
    public function toggleApproved(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Vous ne pouvez pas modifier votre propre autorisation.');
        }

        $user->update(['is_approved' => ! $user->is_approved]);

        return back()->with(
            'success',
            $user->is_approved ? 'Accès autorisé.' : 'Autorisation révoquée.'
        );
    }

    /**
     * Export Excel des identifiants (nom, email, mot de passe en clair) pour
     * transmission aux opérateurs. Respecte les mêmes filtres (rôle/recherche)
     * que la liste. Fichier généré à la volée, jamais écrit sur le serveur.
     */
    public function exportPasswords(Request $request)
    {
        $query = User::with(['bureauVote', 'roles']);

        if ($request->filled('role')) {
            $query->role($request->role);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }

        $users = $query->orderBy('name')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Identifiants');

        $sheet->setCellValue('A1', 'Identifiants opérateurs / administrateurs');
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', 'Généré le ' . now()->format('d/m/Y H:i') . ' — CONFIDENTIEL, à ne pas diffuser publiquement');
        $sheet->mergeCells('A2:E2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('B91C1C');

        $headerRow = 4;
        $headers = ['Bureau', 'Nom', 'Email', 'Rôle', 'Mot de passe'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . $headerRow, $h);
            $col++;
        }
        $sheet->getStyle('A' . $headerRow . ':E' . $headerRow)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A' . $headerRow . ':E' . $headerRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F2937');
        $sheet->getStyle('A' . $headerRow . ':E' . $headerRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row = $headerRow + 1;
        foreach ($users as $user) {
            $sheet->setCellValue('A' . $row, $user->bureauVote?->code ?? '—');
            $sheet->setCellValue('B' . $row, $user->name);
            $sheet->setCellValue('C' . $row, $user->email);
            $sheet->setCellValue('D' . $row, $user->roles->first()?->name === 'admin' ? 'Administrateur' : 'Opérateur');
            $sheet->setCellValue('E' . $row, $user->password_plain ?? '(mot de passe antérieur, non récupérable)');
            $row++;
        }

        foreach (['A', 'B', 'C', 'D', 'E'] as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $filename = 'identifiants_' . now()->format('Y-m-d_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}