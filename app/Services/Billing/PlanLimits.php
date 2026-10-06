<?php

namespace App\Services\Billing;

use App\Models\Tenant\Campus;
use App\Models\Tenant\Student;
use App\Models\Tenant\User;
use Illuminate\Validation\ValidationException;

/**
 * Limites du plan de l'école connectée : établissements, élèves, comptes.
 * Les comptes parents ne sont pas comptés (ils sont illimités).
 */
class PlanLimits
{
    /** @var array<string, array{0: string, 1: string}> ressource => [libellé, champ du formulaire] */
    protected const LABELS = [
        'schools' => ['établissement(s) actif(s)', 'name'],
        'students' => ['élève(s) actif(s)', 'first_name'],
        'users' => ['compte(s) utilisateur (hors parents)', 'email'],
    ];

    /** @return array{schools: int, students: int, users: int} */
    public function usage(): array
    {
        return [
            'schools' => $this->count('schools'),
            'students' => $this->count('students'),
            'users' => $this->count('users'),
        ];
    }

    public function count(string $resource): int
    {
        return match ($resource) {
            'schools' => Campus::where('status', 'active')->count(),
            'students' => Student::where('status', 'active')->count(),
            'users' => User::where('status', '!=', 'suspended')
                ->whereDoesntHave('roles', fn ($q) => $q->where('key', 'parent'))
                ->count(),
        };
    }

    public function remaining(string $resource): ?int
    {
        $limit = tenant()?->limits()[$resource] ?? null;

        return $limit === null ? null : max(0, $limit - $this->count($resource));
    }

    /**
     * Refuse l'ajout si la limite du plan est atteinte.
     *
     * @throws ValidationException
     */
    public function ensureCanAdd(string $resource, int $quantity = 1): void
    {
        $remaining = $this->remaining($resource);

        if ($remaining !== null && $quantity > $remaining) {
            [$label, $field] = self::LABELS[$resource];
            $limit = tenant()->limits()[$resource];

            throw ValidationException::withMessages([
                $field => "Limite de votre plan atteinte : {$limit} {$label}. Passez à un plan supérieur depuis la page Abonnement.",
            ]);
        }
    }
}
