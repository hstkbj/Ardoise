<?php

namespace App\Models\Central;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** Superadmin de la plateforme. N'est PAS un utilisateur d'école. */
class PlatformAdmin extends Authenticatable
{
    use Notifiable;

    protected $connection = 'central';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'last_login_at' => 'datetime'];
    }
}
