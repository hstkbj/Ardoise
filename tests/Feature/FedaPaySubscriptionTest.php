<?php

namespace Tests\Feature;

use App\Mail\SubscriptionPaidMail;
use App\Models\Central\Plan;
use App\Models\Central\SubscriptionPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Paiement de l'abonnement d'une école via FedaPay (compte de la plateforme). */
class FedaPaySubscriptionTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected const API = 'https://sandbox-api.fedapay.com/v1';

    protected const WEBHOOK_SECRET = 'wh_sandbox_platform_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['ardoise.fedapay' => array_merge(config('ardoise.fedapay'), [
            'environment' => 'sandbox',
            'secret_key' => 'sk_sandbox_platform',
            'webhook_secret' => self::WEBHOOK_SECRET,
        ])]);
    }

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    protected function pricedPlan(): Plan
    {
        return tap(Plan::where('name', 'Établissement')->firstOrFail())->update(['price' => 50000, 'period' => 'monthly']);
    }

    protected function fakeCheckout(int $transactionId = 9001): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::API.'/transactions' => Http::response(['v1/transaction' => ['id' => $transactionId, 'status' => 'pending']]),
            self::API."/transactions/{$transactionId}/token" => Http::response(['token' => 'tok_123', 'url' => 'https://sandbox-process.fedapay.com/tok_123']),
        ]);
    }

    protected function signedWebhook(string $uri, array $payload, string $secret = self::WEBHOOK_SECRET)
    {
        $body = json_encode($payload);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        return $this->call('POST', $uri, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_FEDAPAY_SIGNATURE' => "t={$timestamp},s={$signature}",
        ], $body);
    }

    public function test_the_admin_starts_a_fedapay_checkout_for_a_plan(): void
    {
        $this->createSchool();
        $plan = $this->pricedPlan();
        $this->fakeCheckout();

        $this->withToken($this->tokenFor('school_admin'))
            ->postJson('/api/v1/billing/checkout', ['plan_id' => $plan->id, 'periods' => 3])
            ->assertCreated()
            ->assertJsonPath('data.url', 'https://sandbox-process.fedapay.com/tok_123');

        $this->assertDatabaseHas('subscription_payments', [
            'tenant_id' => $this->tenant->id, 'plan_id' => $plan->id, 'amount' => 150000, 'months' => 3,
            'provider' => 'fedapay', 'provider_reference' => '9001', 'status' => 'pending',
        ]);
        Http::assertSent(fn ($request) => $request->url() === self::API.'/transactions'
            && $request['amount'] === 150000
            && $request['currency'] === ['iso' => 'XOF']
            && $request->hasHeader('Authorization', 'Bearer sk_sandbox_platform'));
    }

    public function test_a_plan_without_price_cannot_be_paid_online(): void
    {
        $this->createSchool();
        Http::preventStrayRequests();
        $plan = Plan::where('name', 'Essentiel')->firstOrFail();

        $this->withToken($this->tokenFor('school_admin'))
            ->postJson('/api/v1/billing/checkout', ['plan_id' => $plan->id, 'periods' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id' => 'Ce plan est sur devis']);
    }

    public function test_only_the_admin_can_pay_the_subscription(): void
    {
        $this->createSchool();

        $this->withToken($this->tokenFor('director'))
            ->postJson('/api/v1/billing/checkout', ['plan_id' => $this->pricedPlan()->id, 'periods' => 1])
            ->assertForbidden();
    }

    public function test_an_approved_webhook_extends_the_subscription_and_unblocks_the_school(): void
    {
        Mail::fake();
        $this->freezeTime();
        $tenant = $this->createSchool();
        $tenant->update(['expires_at' => now()->subDays(40)]);
        $plan = $this->pricedPlan();
        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'amount' => 100000, 'months' => 2, 'method' => 'FedaPay',
            'provider' => 'fedapay', 'provider_reference' => '9001', 'reference' => 'SUB-TEST-1', 'status' => 'pending',
        ]);
        Http::preventStrayRequests();
        Http::fake([self::API.'/transactions/9001' => Http::response(['v1/transaction' => ['id' => 9001, 'status' => 'approved', 'amount' => 100000]])]);

        $this->signedWebhook('/api/v1/webhooks/fedapay', ['name' => 'transaction.approved', 'entity' => ['id' => 9001, 'status' => 'approved']])->assertOk();

        $this->assertSame('paid', $payment->fresh()->status);
        $tenant->refresh();
        $this->assertSame('active', $tenant->status);
        $this->assertSame($plan->id, $tenant->plan_id);
        $this->assertSame(now()->addMonths(2)->toDateTimeString(), $tenant->expires_at->toDateTimeString());
        $this->assertFalse($tenant->requiresPayment());
        Mail::assertQueued(SubscriptionPaidMail::class, fn ($mail) => $mail->hasTo('admin@ecole.test') && str_contains($mail->render(), 'SUB-TEST-1'));
    }

    public function test_a_webhook_with_an_invalid_signature_is_rejected(): void
    {
        $tenant = $this->createSchool();
        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id, 'amount' => 100000, 'months' => 1, 'method' => 'FedaPay',
            'provider' => 'fedapay', 'provider_reference' => '9001', 'reference' => 'SUB-TEST-2', 'status' => 'pending',
        ]);
        Http::preventStrayRequests();

        $this->signedWebhook('/api/v1/webhooks/fedapay', ['name' => 'transaction.approved', 'entity' => ['id' => 9001]], 'wrong-secret')
            ->assertStatus(400);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_verifying_a_declined_payment_marks_it_failed_without_extension(): void
    {
        $tenant = $this->createSchool();
        $expiresAt = $tenant->expires_at;
        SubscriptionPayment::create([
            'tenant_id' => $tenant->id, 'amount' => 50000, 'months' => 1, 'method' => 'FedaPay',
            'provider' => 'fedapay', 'provider_reference' => '9002', 'reference' => 'SUB-TEST-3', 'status' => 'pending',
        ]);
        Http::preventStrayRequests();
        Http::fake([self::API.'/transactions/9002' => Http::response(['v1/transaction' => ['id' => 9002, 'status' => 'declined', 'amount' => 50000]])]);

        $this->withToken($this->tokenFor('school_admin'))
            ->postJson('/api/v1/billing/verify', ['reference' => 'SUB-TEST-3'])
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'failed');

        $this->assertTrue($tenant->fresh()->expires_at->equalTo($expiresAt));
    }
}
