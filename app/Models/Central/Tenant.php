<?php

namespace App\Models\Central;

use App\Support\PlanFeatures;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

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

    /** Essai en cours. */
    public const STATE_TRIAL = 'trial';

    /** Abonnement payé et en cours. */
    public const STATE_ACTIVE = 'active';

    /** Échéance dépassée, délai de grâce en cours : accès complet avec rappel. */
    public const STATE_GRACE = 'grace';

    /** Délai de grâce écoulé : seul l'administrateur peut se connecter, pour payer. */
    public const STATE_EXPIRED = 'expired';

    /** Suspendue ou résiliée par la plateforme : aucun accès. */
    public const STATE_SUSPENDED = 'suspended';

    /** Date de fin de la période en cours (essai ou abonnement payé), null = sans limite. */
    public function billingEndsAt(): ?Carbon
    {
        return $this->status === 'trial' ? ($this->trial_ends_at ?? $this->expires_at) : $this->expires_at;
    }

    public function graceEndsAt(): ?Carbon
    {
        return $this->billingEndsAt()?->copy()->addDays(config('tenancy.grace_days'));
    }

    public function billingState(): string
    {
        if (in_array($this->status, ['suspended', 'cancelled'], true)) {
            return self::STATE_SUSPENDED;
        }

        $end = $this->billingEndsAt();

        if ($end === null || $end->isFuture()) {
            return $this->status === 'trial' ? self::STATE_TRIAL : self::STATE_ACTIVE;
        }

        return $this->graceEndsAt()->isFuture() ? self::STATE_GRACE : self::STATE_EXPIRED;
    }

    /** L'école est utilisable par tous ses membres (essai, abonnement payé ou délai de grâce). */
    public function isAccessible(): bool
    {
        return in_array($this->billingState(), [self::STATE_TRIAL, self::STATE_ACTIVE, self::STATE_GRACE], true);
    }

    /** Abonnement impayé après le délai de grâce : l'administrateur peut seulement payer. */
    public function requiresPayment(): bool
    {
        return $this->billingState() === self::STATE_EXPIRED;
    }

    /**
     * Modules inclus dans le plan. Une école sans plan (contrat sur mesure) a tous les modules.
     *
     * @return list<string>
     */
    public function features(): array
    {
        if (! $this->plan_id || ! $this->plan) {
            return PlanFeatures::keys();
        }

        return PlanFeatures::fromLegacy($this->plan->features ?? []);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features(), true);
    }

    /**
     * Limites du plan (null = illimité).
     *
     * @return array{schools: int|null, students: int|null, users: int|null}
     */
    public function limits(): array
    {
        return [
            'schools' => $this->plan?->max_schools,
            'students' => $this->plan?->max_students,
            'users' => $this->plan?->max_users,
        ];
    }

    /** URL d'une page de l'école, ex. https://palmiers.ardoise.app/login */
    public function url(string $path = '/'): string
    {
        $base = parse_url((string) config('app.url'));
        $scheme = $base['scheme'] ?? 'https';
        $port = isset($base['port']) ? ':'.$base['port'] : '';

        return $scheme.'://'.$this->primaryDomain().$port.'/'.ltrim($path, '/');
    }
}
