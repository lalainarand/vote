<?php

// database/seeders/RolePermissionSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ⚠️ Obligatoire : reset le cache des permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ==========================================
        // 1. CRÉATION DES PERMISSIONS
        // ==========================================
        $permissions = [
            // Gestion des bureaux
            'create-bureau', 'edit-bureau', 'delete-bureau', 'view-bureau',
            'lock-bureau', 'unlock-bureau', 'close-bureau',
            
            // Gestion des candidats
            'create-candidate', 'edit-candidate', 'delete-candidate', 'view-candidate',
            
            // Gestion des utilisateurs
            'create-user', 'edit-user', 'delete-user', 'view-user',
            
            // Comptage & PV
            'count-votes', 'enter-pv', 'manual-pv-admin', 'override-pv',
            
            // Résultats & Audit
            'view-global-results', 'view-audit-log', 'view-anomalies',
            'export-results',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // ==========================================
        // 2. CRÉATION DES RÔLES
        // ==========================================
        
        // Rôle ADMINISTRATEUR — tous les droits
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo(Permission::all());

        // Rôle OPÉRATEUR — droits limités à son bureau
        $operator = Role::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);
        $operator->givePermissionTo([
            'count-votes',
            'enter-pv',
            'close-bureau',
            'view-bureau',
        ]);
    }
}