<?php

namespace Database\Seeders;

use App\Models\Central\Plan;
use App\Models\Central\PlatformAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Base centrale : formules et compte superadmin. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['Essentiel', 'Une école sur un seul site.', 'monthly', 1, 300, 40, ['Notes et bulletins', 'Absences', 'Espace parent']],
            ['Établissement', 'Établissement complet avec gestion financière.', 'monthly', 1, 1200, 150, ['Tout Essentiel', 'Frais et paiements', 'Emplois du temps', 'Notifications SMS']],
            ['Groupe scolaire', 'Plusieurs sites sous une même direction.', 'yearly', 10, 10000, 1000, ['Tout Établissement', 'Tableaux consolidés', 'Support prioritaire']],
        ];

        foreach ($plans as [$name, $description, $period, $schools, $students, $users, $features]) {
            Plan::updateOrCreate(['name' => $name], [
                'description' => $description,
                'price' => null, // tarifs à définir dans /superadmin/plans
                'period' => $period,
                'max_schools' => $schools,
                'max_students' => $students,
                'max_users' => $users,
                'features' => $features,
                'status' => 'active',
            ]);
        }

        $password = config('ardoise.superadmin.password') ?: Str::password(16, symbols: false);
        $admin = PlatformAdmin::firstOrNew(['email' => strtolower(config('ardoise.superadmin.email'))]);

        if (! $admin->exists) {
            $admin->fill(['name' => config('ardoise.superadmin.name'), 'password' => $password])->save();
            $this->command?->info('Superadmin : '.$admin->email.' / '.$password);
        }
    }
}
