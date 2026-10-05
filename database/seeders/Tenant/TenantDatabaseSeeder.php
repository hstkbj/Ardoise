<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\AcademicYear;
use App\Models\Tenant\Level;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

/** Données de base de toute nouvelle école : rôles, permissions, niveaux, année en cours. */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::all() as $permission) {
            Permission::updateOrCreate(['key' => $permission['key']], $permission);
        }

        $permissionIds = Permission::pluck('id', 'key');

        foreach (PermissionCatalog::ROLES as $key => [$name, $description]) {
            $role = Role::updateOrCreate(['key' => $key], ['name' => $name, 'description' => $description, 'is_system' => true]);
            $grants = PermissionCatalog::defaultGrants()[$key];

            if ($grants !== ['*']) {
                $role->permissions()->sync($permissionIds->only($grants)->values());
            }
        }

        $levels = [
            ['CP', 'primaire'], ['CE1', 'primaire'], ['CE2', 'primaire'], ['CM1', 'primaire'], ['CM2', 'primaire'],
            ['6e', 'college'], ['5e', 'college'], ['4e', 'college'], ['3e', 'college'],
            ['2nde', 'lycee'], ['1re', 'lycee'], ['Tle', 'lycee'],
        ];

        foreach ($levels as $i => [$name, $cycle]) {
            Level::updateOrCreate(['name' => $name], ['cycle' => $cycle, 'position' => $i + 1]);
        }

        if (! AcademicYear::exists()) {
            $start = now()->month >= 8 ? now()->year : now()->year - 1;
            $year = AcademicYear::create([
                'name' => $start.'-'.($start + 1),
                'starts_on' => "{$start}-09-01",
                'ends_on' => ($start + 1).'-07-15',
                'period_type' => 'trimester',
                'status' => 'active',
            ]);

            foreach ([['Trimestre 1', '09-01', '12-20', 0], ['Trimestre 2', '01-05', '03-31', 1], ['Trimestre 3', '04-10', '07-15', 1]] as $i => [$name, $from, $to, $offset]) {
                $year->terms()->create([
                    'name' => $name,
                    'position' => $i + 1,
                    'starts_on' => ($start + $offset)."-{$from}",
                    'ends_on' => ($start + $offset)."-{$to}",
                ]);
            }
        }
    }
}
