<?php

namespace App\Services;

use App\Models\Tenant\ParentProfile;
use App\Models\Tenant\Role;
use App\Models\Tenant\Student;
use App\Models\Tenant\Teacher;
use App\Models\Tenant\User;
use App\Support\Phone;

/** Création des comptes de connexion liés aux enseignants et aux parents. */
class AccountService
{
    public function __construct(protected ParentAccessService $codes) {}

    public function assignRole(User $user, string $roleKey): void
    {
        if ($role = Role::where('key', $roleKey)->first()) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    }

    /** Compte enseignant : connexion par e-mail/téléphone + mot de passe (invitation). */
    public function ensureTeacherAccount(Teacher $teacher): User
    {
        $email = $teacher->email ?: null;
        $phone = Phone::normalize($teacher->phone);

        $user = $teacher->user
            ?? ($email ? User::where('email', $email)->first() : null)
            ?? User::create(['name' => $teacher->full_name, 'status' => 'invited']);

        // E-mail et téléphone sont uniques : on ne les reprend que s'ils sont libres
        $user->update([
            'name' => $teacher->full_name,
            'email' => $email && ! User::where('email', $email)->whereKeyNot($user->id)->exists() ? $email : $user->email,
            'phone' => $phone && ! User::where('phone', $phone)->whereKeyNot($user->id)->exists() ? $phone : $user->phone,
        ]);
        $this->assignRole($user, 'teacher');

        if (! $teacher->user_id) {
            $teacher->update(['user_id' => $user->id]);
        }

        return $user;
    }

    /**
     * Profil parent + compte sans mot de passe + code d'accès.
     *
     * @return array{0: ParentProfile, 1: string|null} profil, code généré (null si déjà existant)
     */
    public function createParent(array $data): array
    {
        $user = User::create([
            'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
            'email' => null,
            'phone' => null,
            'status' => 'active',
        ]);
        $this->assignRole($user, 'parent');

        $parent = ParentProfile::create(array_merge($data, ['user_id' => $user->id, 'status' => 'pending']));
        $code = $this->codes->issue($parent);

        return [$parent, $code];
    }

    /**
     * Parent principal saisi à l'inscription (formulaire ou import) : retrouvé
     * par son téléphone, sinon créé avec son code d'accès (renvoyé en clair).
     *
     * @param  array{guardian_id?: int|null, guardian_name?: string|null, guardian_phone?: string|null, guardian_relation?: string|null}  $data
     */
    public function attachGuardian(Student $student, array $data): ?string
    {
        if (! empty($data['guardian_id'])) {
            $student->parents()->syncWithoutDetaching([$data['guardian_id'] => ['relation' => $data['guardian_relation'] ?? null, 'is_primary' => true]]);

            return null;
        }

        if (empty($data['guardian_name'])) {
            return null;
        }

        $phone = Phone::normalize($data['guardian_phone'] ?? null);
        $parent = $phone
            ? ParentProfile::get()->first(fn ($p) => Phone::normalize($p->phone) === $phone)
            : null;
        $code = null;

        if (! $parent) {
            $parts = preg_split('/\s+/', trim($data['guardian_name']));
            $first = count($parts) > 1 ? implode(' ', array_slice($parts, 0, -1)) : $parts[0];
            $last = count($parts) > 1 ? end($parts) : '';
            [$parent, $code] = $this->createParent(['first_name' => $first, 'last_name' => $last, 'phone' => $data['guardian_phone'] ?? null]);
        }

        $student->parents()->syncWithoutDetaching([$parent->id => ['relation' => $data['guardian_relation'] ?? null, 'is_primary' => true]]);

        return $code;
    }

    public function syncParentAccount(ParentProfile $parent): void
    {
        $parent->user?->update(['name' => $parent->full_name]);
    }
}
