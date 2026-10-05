<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Models\Tenant\User;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\HttpSmsGateway;
use App\Services\Sms\SmsGateway;
use App\Tenancy\TenantManager;
use App\Tenancy\TenantResolver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Une seule instance par requête : elle porte l'école connectée
        $this->app->singleton(TenantManager::class);
        $this->app->singleton(TenantResolver::class);

        // Pilote de paiement en ligne (à remplacer par un agrégateur mobile money)
        $this->app->bind(\App\Services\Payments\PaymentGateway::class, \App\Services\Payments\ManualPaymentGateway::class);

        $this->app->singleton(SmsGateway::class, fn () => match (config('ardoise.sms.driver')) {
            'http' => new HttpSmsGateway(config('ardoise.sms.http')),
            default => new LogSmsGateway,
        });
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        /*
         * RBAC : « students.view », « grades.publish »…
         * L'administrateur de l'école a tous les droits ; les autres rôles
         * ont les permissions qui leur sont attribuées dans /admin/roles.
         */
        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User || ! str_contains($ability, '.')) {
                return null;
            }

            return $user->hasPermission($ability) ? true : null;
        });

        // Connexion par code parent : 5 essais/minute et 30/heure par adresse IP
        RateLimiter::for('parent-code', fn (Request $request) => [
            Limit::perMinute(5)->by('code-min:'.$request->ip()),
            Limit::perHour(30)->by('code-hour:'.$request->ip()),
        ]);

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(10)->by('login:'.$request->ip()),
            Limit::perMinute(5)->by('login-id:'.strtolower((string) $request->input('login')).'|'.$request->ip()),
        ]);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)->by($request->user()?->getAuthIdentifier().'|'.$request->ip()));

        ResetPassword::createUrlUsing(function ($user, string $token) {
            $base = request()->getSchemeAndHttpHost();

            return $base.'/reset-password?token='.$token.'&login='.urlencode($user->email);
        });
    }
}
