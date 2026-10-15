<?php

namespace App\Models\Tenant;

use App\Models\Concerns\HasTwoFactorAuthentication;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasTwoFactorAuthentication, Notifiable;

    protected $connection = 'tenant';

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** Ordre de priorité pour déterminer l'espace d'arrivée. */
    public const ROLE_PRIORITY = ['school_admin', 'director', 'academic_manager', 'accountant', 'secretary', 'teacher', 'parent', 'student'];

    protected ?array $permissionCache = null;

    protected ?array $campusIdsCache = null;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function campuses(): BelongsToMany
    {
        return $this->belongsToMany(Campus::class);
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function parentProfile(): HasOne
    {
        return $this->hasOne(ParentProfile::class);
    }

    /** Numéro utilisé pour les SMS (le parent n'a pas de téléphone de connexion). */
    public function routeNotificationForSms(): ?string
    {
        return $this->phone ?? $this->parentProfile?->phone;
    }

    public function roleKeys(): array
    {
        return $this->relationLoaded('roles') ? $this->roles->pluck('key')->all() : $this->roles()->pluck('key')->all();
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roleKeys(), true);
    }

    public function hasAnyRole(array $roles): bool
    {
        return (bool) array_intersect($roles, $this->roleKeys());
    }

    public function primaryRole(): ?string
    {
        $keys = $this->roleKeys();

        foreach (self::ROLE_PRIORITY as $role) {
            if (in_array($role, $keys, true)) {
                return $role;
            }
        }

        return $keys[0] ?? null;
    }

    public function isSchoolAdmin(): bool
    {
        return $this->hasRole('school_admin');
    }

    /** Personnel de l'école (accès à l'espace /admin). */
    public function isStaff(): bool
    {
        return $this->hasAnyRole(['school_admin', 'director', 'academic_manager', 'accountant', 'secretary']);
    }

    /** Enseignant sans autre rôle d'administration : ses données sont limitées à ses classes. */
    public function isTeacherOnly(): bool
    {
        return $this->hasRole('teacher') && ! $this->isStaff();
    }

    /**
     * Sites auxquels le personnel est limité ; null = tous les sites (administrateur, personnel
     * sans rattachement à un site, ou profil sans périmètre de site : enseignant, parent). Lecture directe de la table
     * pivot : le modèle Campus est lui-même filtré par cette portée.
     *
     * @return array<int, int>|null
     */
    public function restrictedCampusIds(): ?array
    {
        if (! $this->isStaff() || $this->isSchoolAdmin()) {
            return null;
        }

        $ids = $this->campusIdsCache ??= \Illuminate\Support\Facades\DB::connection('tenant')
            ->table('campus_user')->where('user_id', $this->id)->pluck('campus_id')->map(fn ($id) => (int) $id)->all();

        return $ids === [] ? null : $ids;
    }

    public function permissionKeys(): array
    {
        if ($this->isSchoolAdmin()) {
            return ['*'];
        }

        return $this->permissionCache ??= Permission::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $this->roles()->select('roles.id')))
            ->pluck('key')
            ->all();
    }

    public function hasPermission(string $permission): bool
    {
        $keys = $this->permissionKeys();

        return in_array('*', $keys, true) || in_array($permission, $keys, true);
    }
}
