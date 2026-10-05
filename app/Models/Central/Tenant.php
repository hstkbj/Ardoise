<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Une organisation scolaire cliente, avec sa propre base de données. */
class Tenant extends CentralModel
{
    protected $hidden = ['db_password', 'db_username'];

    protected function casts(): array
    {
        return [
            'db_password' => 'encrypted',
            'data' => 'array',
            'trial_ends_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function usageSnapshots(): HasMany
    {
        return $this->hasMany(UsageSnapshot::class);
    }

    public function latestUsage(): HasOne
    {
        return $this->hasOne(UsageSnapshot::class)->latestOfMany('date');
    }

    /** Domaine principal : {code}.{base_domain} */
    public function primaryDomain(): string
    {
        return $this->domains()->value('domain') ?? $this->code.'.'.config('tenancy.base_domain');
    }

    /** Une école suspendue, annulée ou expirée (après le délai de grâce) ne peut plus être utilisée. */
    public function isAccessible(): bool
    {
        if (in_array($this->status, ['suspended', 'cancelled'], true)) {
            return false;
        }

        $end = $this->status === 'trial' ? ($this->trial_ends_at ?? $this->expires_at) : $this->expires_at;

        return $end === null || $end->copy()->addDays(config('tenancy.grace_days'))->isFuture();
    }
}
