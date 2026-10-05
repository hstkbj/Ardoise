<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle de la base d'une école. La connexion « tenant » n'a de base
 * qu'après App\Tenancy\TenantManager::connect().
 * Les données entrantes passent toujours par un FormRequest (validated()).
 */
abstract class TenantModel extends Model
{
    protected $connection = 'tenant';

    protected $guarded = ['id'];
}
