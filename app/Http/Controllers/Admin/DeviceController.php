<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuthorizedDevice;
use App\Models\DeviceLoginAttempt;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeviceController extends Controller
{
    /**
     * Liste des 26 tablettes autorisées + journal des tentatives bloquées.
     */
    public function index()
    {
        $devices = AuthorizedDevice::with(['approvedBy:id,name', 'lastUsedBy:id,name,bureau_vote_id', 'lastUsedBy.bureauVote:id,code,nom'])
            ->orderByDesc('is_approved')
            ->orderBy('device_name')
            ->get()
            ->map(fn($d) => [
                'id'            => $d->id,
                'device_name'   => $d->device_name,
                'device_token'  => $d->device_token,
                'browser'       => $d->browser,
                'platform'      => $d->platform,
                'ip_address'    => $d->ip_address,
                'is_approved'   => $d->is_approved,
                'approved_by'   => $d->approvedBy?->name,
                'approved_at'   => $d->approved_at?->format('d/m/Y H:i'),
                'last_used_at'  => $d->last_used_at?->format('d/m/Y H:i'),
                // Dernier opérateur connecté depuis cet appareil, purement informatif :
                // l'appareil n'est jamais réservé à cet opérateur (voir modèle).
                'last_used_by'  => $d->lastUsedBy ? [
                    'name'   => $d->lastUsedBy->name,
                    'bureau' => $d->lastUsedBy->bureauVote ? [
                        'code' => $d->lastUsedBy->bureauVote->code,
                        'nom'  => $d->lastUsedBy->bureauVote->nom,
                    ] : null,
                ] : null,
                'pairing_url'   => route('device.pair', $d->device_token),
            ]);

        $attempts = DeviceLoginAttempt::with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn($a) => [
                'id'         => $a->id,
                'user'       => $a->user?->name,
                'device_token' => $a->device_token,
                'browser'    => $a->browser,
                'platform'   => $a->platform,
                'ip_address' => $a->ip_address,
                'created_at' => $a->created_at?->format('d/m/Y H:i:s'),
            ]);

        return Inertia::render('Admin/Devices/Index', [
            'devices'  => $devices,
            'attempts' => $attempts,
        ]);
    }

    /**
     * Enregistre une nouvelle tablette autorisée (approuvée d'office : c'est
     * l'admin qui l'enregistre qui fait office d'approbation). Le token est
     * généré côté serveur ; l'appairage physique se fait via le lien fourni.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'device_name' => 'required|string|max:255|unique:authorized_devices,device_name',
        ], [
            'device_name.unique' => 'Ce nom d\'appareil est déjà utilisé.',
        ]);

        AuthorizedDevice::create([
            'device_token' => AuthorizedDevice::generateToken(),
            'device_name'  => $validated['device_name'],
            'is_approved'  => true,
            'approved_by'  => $request->user()->id,
            'approved_at'  => now(),
        ]);

        return redirect()
            ->route('admin.devices.index')
            ->with('success', 'Appareil enregistré. Partagez son lien d\'appairage avec la tablette concernée.');
    }

    /**
     * Génère plusieurs tablettes d'un coup (ex: les 26 de départ), numérotées
     * automatiquement.
     */
    public function storeBulk(Request $request)
    {
        $validated = $request->validate([
            'count'  => 'required|integer|min:1|max:100',
            'prefix' => 'nullable|string|max:100',
        ]);

        $prefix = $validated['prefix'] ?: 'Tablette';

        // Numérotation résiliente aux "trous" (ex: "Tablette 05" supprimée) : on
        // avance jusqu'au premier nom encore libre plutôt que de se fier à un
        // simple compte, sinon le nom (désormais unique en base) pourrait déjà
        // exister et faire échouer la création.
        $number = 1;
        $created = 0;

        while ($created < $validated['count']) {
            $candidate = "{$prefix} " . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $number++;

            if (AuthorizedDevice::where('device_name', $candidate)->exists()) {
                continue;
            }

            AuthorizedDevice::create([
                'device_token' => AuthorizedDevice::generateToken(),
                'device_name'  => $candidate,
                'is_approved'  => true,
                'approved_by'  => $request->user()->id,
                'approved_at'  => now(),
            ]);

            $created++;
        }

        return redirect()
            ->route('admin.devices.index')
            ->with('success', "{$validated['count']} appareil(s) généré(s).");
    }

    /**
     * Révoque / réautorise un appareil (bascule is_approved). Ne supprime rien :
     * l'historique (dernière utilisation, IP...) est conservé.
     */
    public function toggleApproved(AuthorizedDevice $device)
    {
        $device->update(['is_approved' => ! $device->is_approved]);

        return back()->with(
            'success',
            $device->is_approved ? 'Appareil réautorisé.' : 'Appareil révoqué.'
        );
    }

    /**
     * Suppression définitive (ex: tablette remplacée/mise au rebut).
     */
    public function destroy(AuthorizedDevice $device)
    {
        $device->delete();

        return redirect()
            ->route('admin.devices.index')
            ->with('success', 'Appareil supprimé.');
    }
}
