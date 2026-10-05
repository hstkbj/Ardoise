<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/** Jetons Sanctum stockés dans la base de chaque école. */
class PersonalAccessToken extends SanctumToken
{
    protected $connection = 'tenant';
}
