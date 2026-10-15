<?php

namespace App\Models\Concerns;

use App\Services\CampusScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Limite automatiquement les requêtes aux sites de l'utilisateur connecté (voir CampusScope).
 * Le modèle décrit comment se rattacher à un site dans constrainToCampuses().
 */
trait RestrictedToUserCampuses
{
    public static function bootRestrictedToUserCampuses(): void
    {
        static::addGlobalScope('campuses', function (Builder $query) {
            $ids = app(CampusScope::class)->ids();

            if ($ids !== null) {
                (new static)->constrainToCampuses($query, $ids);
            }
        });
    }

    /** @param  array<int, int>  $campusIds */
    abstract public function constrainToCampuses(Builder $query, array $campusIds): void;
}
