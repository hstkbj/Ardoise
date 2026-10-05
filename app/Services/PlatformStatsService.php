<?php

namespace App\Services;

use App\Models\Central\Tenant;
use App\Models\Central\UsageSnapshot;
use App\Models\Tenant\Campus;
use App\Models\Tenant\Enrollment;
use App\Models\Tenant\Teacher;
use App\Models\Tenant\User;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Statistiques plateforme sans compromettre l'isolation :
 *  1. connexion temporaire à la base d'une école (statistiques détaillées, à la demande) ;
 *  2. agrégation quotidienne dans usage_snapshots (tableaux de bord, listes globales).
 * Seuls des compteurs et des informations de contact administratives remontent en base centrale.
 */
class PlatformStatsService
{
    public function __construct(protected TenantManager $manager) {}

    /** Stratégie 1 : lecture ponctuelle dans la base de l'école. */
    public function live(Tenant $tenant): array
    {
        return $this->manager->run($tenant, fn () => [
            'students' => Enrollment::where('status', 'active')->distinct()->count('student_id'),
            'teachers' => Teacher::where('status', '!=', 'inactive')->count(),
            'users' => User::count(),
            'active_users' => User::where('last_login_at', '>=', now()->subDays(30))->count(),
            'campuses' => Campus::count(),
            'last_activity' => User::max('last_login_at'),
        ]);
    }

    /** Stratégie 2 : instantané quotidien stocké en base centrale. */
    public function snapshot(Tenant $tenant): ?UsageSnapshot
    {
        try {
            $data = $this->manager->run($tenant, function () use ($tenant) {
                return [
                    'students' => Enrollment::where('status', 'active')->distinct()->count('student_id'),
                    'teachers' => Teacher::where('status', '!=', 'inactive')->count(),
                    'users' => User::count(),
                    'active_users' => User::where('last_login_at', '>=', now()->subDays(7))->count(),
                    'last_activity' => User::max('last_login_at'),
                    'campuses' => Campus::get()->map(fn ($c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'city' => $c->city,
                        'status' => $c->status,
                        'students_count' => Enrollment::where('status', 'active')->whereHas('classRoom', fn ($q) => $q->where('campus_id', $c->id))->count(),
                    ])->all(),
                    'admins' => User::whereHas('roles', fn ($q) => $q->whereIn('key', ['school_admin', 'director']))
                        ->get(['id', 'name', 'email', 'status', 'last_login_at'])
                        ->map(fn ($u) => ['id' => $u->id, 'full_name' => $u->name, 'email' => $u->email, 'status' => $u->status, 'role' => $u->primaryRole(), 'last_login_at' => $u->last_login_at])
                        ->all(),
                    'storage_mb' => $this->storageMb($tenant),
                ];
            });
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $data['sms_sent'] = (int) Cache::get('sms-count:'.$tenant->id.':'.now()->format('Y-m'), 0);

        return UsageSnapshot::updateOrCreate(['tenant_id' => $tenant->id, 'date' => now()->toDateString()], $data);
    }

    protected function storageMb(Tenant $tenant): int
    {
        $bytes = 0;

        foreach ([storage_path('app/private/tenants/'.$tenant->code), storage_path('app/public/tenants/'.$tenant->code)] as $dir) {
            if (File::isDirectory($dir)) {
                foreach (File::allFiles($dir) as $file) {
                    $bytes += $file->getSize();
                }
            }
        }

        return (int) ceil($bytes / 1048576);
    }
}
