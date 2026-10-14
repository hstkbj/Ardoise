<?php

namespace Tests\Feature;

use App\Models\Central\Plan;
use App\Models\Central\PlatformAdmin;
use App\Models\Central\PlatformSetting;
use App\Models\Central\SubscriptionPayment;
use App\Models\Central\Tenant;
use App\Notifications\Platform\PlatformAlert;
use App\Services\Billing\SubscriptionBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Factures d'abonnement et alertes des superadmins. */
class SubscriptionInvoiceAndAlertsTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    protected function pendingPayment(array $attributes = []): SubscriptionPayment
    {
        return SubscriptionPayment::create($attributes + [
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::where('name', 'Établissement')->value('id'),
            'customer' => 'Awa Admin',
            'amount' => 100000,
            'months' => 2,
            'method' => 'FedaPay',
            'provider' => 'fedapay',
            'provider_reference' => (string) random_int(1000, 9999),
            'reference' => 'SUB-'.uniqid(),
            'status' => 'pending',
        ]);
    }

    protected function superadmin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrCreate(['email' => 'root@ardoise.test'], ['name' => 'Plateforme', 'password' => 'platform-password']);
    }

    public function test_paid_subscriptions_get_consecutive_invoice_numbers_and_a_downloadable_invoice(): void
    {
        $this->freezeTime();
        $this->createSchool();
        PlatformSetting::create(['section' => 'invoicing', 'values' => ['company_name' => 'Ardoise SAS', 'tax_id' => 'IFU-0042', 'vat_rate' => '18']]);
        $billing = app(SubscriptionBilling::class);

        $first = $billing->markPaid($this->pendingPayment());
        $second = $billing->markPaid($this->pendingPayment());

        $year = now()->format('Y');
        $this->assertSame("FAC-{$year}-000001", $first->invoice_number);
        $this->assertSame("FAC-{$year}-000002", $second->invoice_number);
        $this->assertSame(Carbon::parse($first->meta['period_start'])->addMonths(2)->toDateString(), $first->meta['period_end']);

        $html = $this->withToken($this->tokenFor('school_admin'))->get("/api/v1/billing/payments/{$first->id}/invoice")->assertOk()->getContent();
        $this->assertStringContainsString("FAC-{$year}-000001", $html);
        $this->assertStringContainsString('IFU / NIF : IFU-0042', $html);
        $this->assertStringContainsString('84 746 FCFA', $html);
        $this->assertStringContainsString('TVA (18 %)', $html);
    }

    public function test_an_invoice_is_only_available_to_its_school_once_paid(): void
    {
        $this->createSchool();
        $pending = $this->pendingPayment();
        $token = $this->tokenFor('school_admin');

        $this->withToken($token)->getJson("/api/v1/billing/payments/{$pending->id}/invoice")->assertNotFound();

        $otherSchool = Tenant::create(['name' => 'Autre école', 'code' => 'autre-ecole', 'database' => 'tenant_autre_ecole', 'status' => 'active']);
        $other = $this->pendingPayment(['tenant_id' => $otherSchool->id, 'status' => 'paid', 'paid_at' => now()]);
        $this->withToken($token)->getJson("/api/v1/billing/payments/{$other->id}/invoice")->assertNotFound();

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor('secretary'))->getJson("/api/v1/billing/payments/{$pending->id}/invoice")->assertForbidden();
    }

    public function test_the_superadmins_are_alerted_when_a_subscription_is_paid_or_fails(): void
    {
        Notification::fake();
        $this->createSchool();
        $admin = $this->superadmin();
        $billing = app(SubscriptionBilling::class);

        $billing->markPaid($this->pendingPayment());
        Notification::assertSentTo($admin, PlatformAlert::class, fn (PlatformAlert $alert) => $alert->event === 'subscription.paid' && str_contains($alert->body, '100 000 FCFA'));

        config(['ardoise.fedapay.secret_key' => 'sk_sandbox_platform', 'ardoise.fedapay.environment' => 'sandbox']);
        $failed = $this->pendingPayment(['provider_reference' => '9002']);
        Http::fake(['https://sandbox-api.fedapay.com/v1/transactions/9002' => Http::response(['v1/transaction' => ['id' => 9002, 'status' => 'declined', 'amount' => 100000]])]);
        app(SubscriptionBilling::class)->syncFromProvider($failed);

        Notification::assertSentTo($admin, PlatformAlert::class, fn (PlatformAlert $alert) => $alert->event === 'subscription.payment_failed' && $alert->level === 'warning');
    }

    public function test_a_payment_recorded_by_hand_is_not_reported_back_to_the_platform(): void
    {
        Notification::fake();
        $this->createSchool();
        $this->superadmin();

        app(SubscriptionBilling::class)->markPaid($this->pendingPayment(['provider' => 'manual', 'provider_reference' => null]));

        Notification::assertNothingSent();
    }

    public function test_an_expired_school_is_reported_once_and_the_alert_appears_in_the_console(): void
    {
        $this->freezeTime();
        $tenant = $this->createSchool();
        $tenant->update(['expires_at' => now()->subDays(config('tenancy.grace_days') + 2)]);
        $admin = $this->superadmin();

        $this->artisan('subscriptions:check', ['--no-mail' => true])->assertSuccessful();
        $this->artisan('subscriptions:check', ['--no-mail' => true])->assertSuccessful();

        $this->assertSame(1, $admin->notifications()->count());
        $this->actingAs($admin, 'platform')->getJson('/api/v1/platform/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unread', 1)
            ->assertJsonPath('data.0.type', 'subscription.expired')
            ->assertJsonPath('data.0.title', 'École bloquée : École Test');
    }

    public function test_alerts_can_be_switched_off_per_event(): void
    {
        Notification::fake();
        $this->createSchool();
        $this->superadmin();
        PlatformSetting::create(['section' => 'alerts', 'values' => ['events' => ['backup.failed']]]);

        app(SubscriptionBilling::class)->markPaid($this->pendingPayment());

        Notification::assertNothingSent();
    }
}
