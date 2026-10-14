<?php

namespace Tests\Feature;

use App\Models\Tenant\FeeAssignment;
use App\Models\Tenant\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Webhook FedaPay des frais scolaires (HTTP simulé) : signature, idempotence, échec. */
class FedaPayWebhookTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected const API = 'https://sandbox-api.fedapay.com/v1';

    protected const SECRET = 'wh_sandbox_school';

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    /** @return array{0: array<string, int|string>, 1: int} données de l'école, id du paiement en attente */
    protected function pendingOnlinePayment(): array
    {
        $this->createSchool();
        $data = $this->seedSchoolData(150000);
        $this->withToken($this->tokenFor('school_admin'))->putJson('/api/v1/settings/online_payments', [
            'enabled' => 'yes', 'environment' => 'sandbox', 'public_key' => 'pk_sandbox_school',
            'secret_key' => 'sk_sandbox_school', 'webhook_secret' => self::SECRET,
        ])->assertOk();

        $paymentId = $this->inSchool(fn () => Payment::create([
            'reference' => 'REC-W1', 'fee_assignment_id' => $data['assignment_id'], 'student_id' => $data['student_id'],
            'amount' => 50000, 'method' => 'FedaPay', 'transaction_ref' => '7001', 'status' => 'pending',
        ])->id);

        return [$data, $paymentId];
    }

    protected function webhook(string $secret = self::SECRET, ?int $timestamp = null)
    {
        $body = json_encode(['name' => 'transaction.approved', 'entity' => ['id' => 7001]]);
        $timestamp ??= time();

        return $this->call('POST', '/api/v1/webhooks/fedapay/'.$this->tenant->code, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FEDAPAY_SIGNATURE' => 't='.$timestamp.',s='.hash_hmac('sha256', $timestamp.'.'.$body, $secret),
        ], $body);
    }

    public function test_a_webhook_with_a_wrong_signature_is_rejected_and_changes_nothing(): void
    {
        Notification::fake();
        [, $paymentId] = $this->pendingOnlinePayment();
        Http::preventStrayRequests();

        $this->webhook('another-secret')->assertStatus(400);

        $this->assertSame('pending', $this->inSchool(fn () => Payment::find($paymentId)->status));
    }

    public function test_a_webhook_with_an_expired_timestamp_is_rejected(): void
    {
        [, $paymentId] = $this->pendingOnlinePayment();
        Http::preventStrayRequests();

        $this->webhook(self::SECRET, time() - 86400)->assertStatus(400);

        $this->assertSame('pending', $this->inSchool(fn () => Payment::find($paymentId)->status));
    }

    public function test_receiving_the_same_approved_webhook_twice_counts_the_payment_once(): void
    {
        Notification::fake();
        [$data, $paymentId] = $this->pendingOnlinePayment();
        Http::preventStrayRequests();
        Http::fake([self::API.'/transactions/7001' => Http::response(['v1/transaction' => ['id' => 7001, 'status' => 'approved', 'amount' => 50000]])]);

        $this->webhook()->assertOk();
        $this->webhook()->assertOk();

        $this->inSchool(function () use ($paymentId, $data) {
            $this->assertSame('paid', Payment::find($paymentId)->status);
            $this->assertSame(50000, FeeAssignment::find($data['assignment_id'])->paid_amount);
        });
        Http::assertSentCount(1);
    }

    public function test_a_declined_transaction_marks_the_payment_failed_without_crediting_the_fee(): void
    {
        [$data, $paymentId] = $this->pendingOnlinePayment();
        Http::preventStrayRequests();
        Http::fake([self::API.'/transactions/7001' => Http::response(['v1/transaction' => ['id' => 7001, 'status' => 'declined', 'amount' => 50000]])]);

        $this->webhook()->assertOk();

        $this->inSchool(function () use ($paymentId, $data) {
            $this->assertSame('failed', Payment::find($paymentId)->status);
            $this->assertSame(0, (int) FeeAssignment::find($data['assignment_id'])->paid_amount);
        });
    }

    public function test_an_unreachable_fedapay_api_asks_for_a_retry_and_keeps_the_payment_pending(): void
    {
        [, $paymentId] = $this->pendingOnlinePayment();
        Http::preventStrayRequests();
        Http::fake([self::API.'/transactions/7001' => Http::response(['message' => 'Service indisponible'], 503)]);

        $this->webhook()->assertStatus(503);

        $this->assertSame('pending', $this->inSchool(fn () => Payment::find($paymentId)->status));
    }
}
