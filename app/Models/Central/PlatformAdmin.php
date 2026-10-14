<?php

namespace App\Models\Central;

use App\Models\Concerns\HasTwoFactorAuthentication;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** Superadmin de la plateforme. N'est PAS un utilisateur d'école. */
class PlatformAdmin extends Authenticatable
{
    use HasTwoFactorAuthentication, Notifiable;

    protected $connection = 'central';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
