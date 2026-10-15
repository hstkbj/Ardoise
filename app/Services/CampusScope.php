<?php

namespace App\Services;

use App\Models\Tenant\User;
use Illuminate\Support\Facades\Auth;

/**
 * Portée par établissement (site) de l'utilisateur connecté.
 *
 * L'administrateur de l'école voit tous les sites. Un membre du personnel (secrétaire,
 * comptable, directeur…) rattaché à un ou plusieurs sites ne voit que ceux-là ; sans
 * rattachement, il voit tous les sites (réglage par défaut). Enseignants, parents, tâches
 * planifiées et files d'attente ne sont pas concernés (leur périmètre est géré ailleurs).
 */
class CampusScope
{
    /** @return array<int, int>|null null = aucune restriction */
    public function ids(): ?array
    {
        $user = Auth::user();

        return $user instanceof User ? $user->restrictedCampusIds() : null;
    }

    public function isRestricted(): bool
    {
        return $this->ids() !== null;
    }

    /** Un site donné est-il accessible ? (null = « tous les sites », réservé à l'administrateur) */
    public function allows(?int $campusId): bool
    {
        $ids = $this->ids();

        return $ids === null || ($campusId !== null && in_array($campusId, $ids, true));
    }

    public function ensureAllows(?int $campusId): void
    {
        abort_unless($this->allows($campusId), 403, 'Cet établissement n’est pas dans votre périmètre.');
    }
}
