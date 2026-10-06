<?php

namespace Tests\Feature;

use App\Models\Tenant\FeeAssignment;
use App\Models\Tenant\Payment;
use App\Models\Tenant\Setting;
use App\Models\Tenant\User;
use App\Notifications\SchoolNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Frais scolaires payés par les parents sur le compte FedaPay de l'école. */
class FedaPaySchoolFeesTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected const API = 'https://sandbox-api.fedapay.com/v1';

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    protected function enableFedaPay(string $adminToken): void
    {
        $this->withToken($adminToken)->putJson('/api/v1/settings/online_payments', [
            'enabled' => 'yes',
            'environment' => 'sandbox',
            'public_key' => 'pk_sandbox_school',
            'secret_key' => 'sk_sandbox_school',
            'webhook_secret' => 'wh_sandbox_school',
        ])->assertOk();
    }

    protected function parentToken(string $code): string
    {
        // Les gardes d'authentification gardent l'utilisateur de la requête précédente
        Auth::forgetGuards();
        $this->flushHeaders();

        return $this->postJson('/api/v1/parent/login', ['code' => $code])->assertOk()->json('token');
    }

    public function test_school_keys_are_stored_encrypted_and_never_returned(): void
    {
        $this->createSchool();
        $admin = $this->tokenFor('school_admin');
        $this->enableFedaPay($admin);

        $this->withToken($admin)->getJson('/api/v1/settings/online_payments')
            ->assertOk()
            ->assertJsonPath('data.secret_key', null)
            ->assertJsonPath('data.secret_key_set', true)
            ->assertJsonPath('data.webhook_secret_set', true);

        $stored = $this->inSchool(fn () => Setting::where('section', 'online_payments')->first()->values);
        $this->assertNotSame('sk_sandbox_school', $stored['secret_key']);

        // Un secret laissé vide conserve la valeur enregistrée
        $this->withToken($admin)->putJson('/api/v1/settings/online_payments', ['enabled' => 'yes', 'environment' => 'sandbox', 'secret_key' => ''])->assertOk();
        $this->withToken($admin)->getJson('/api/v1/settings/online_payments')->assertJsonPath('data.secret_key_set', true);
    }

    public function test_a_parent_is_redirected_to_fedapay_with_the_school_keys(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData(150000);
        $this->enableFedaPay($this->tokenFor('school_admin'));
        Http::preventStrayRequests();
        Http::fake([
            self::API.'/transactions' => Http::response(['v1/transaction' => ['id' => 7001, 'status' => 'pending']]),
            self::API.'/transactions/7001/token' => Http::response(['token' => 'tok', 'url' => 'https://sandbox-process.fedapay.com/tok']),
        ]);

        $this->withToken($this->parentToken($data['parent_code']))
            ->postJson("/api/v1/parent/payments/{$data['assignment_id']}/checkout", ['amount' => 50000])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'fedapay')
            ->assertJsonPath('data.redirect_url', 'https://sandbox-process.fedapay.com/tok');

        Http::assertSent(fn ($request) => $request->url() === self::API.'/transactions'
            && $request['amount'] === 50000
            && $request->hasHeader('Authorization', 'Bearer sk_sandbox_school'));
        $this->assertSame('7001', $this->inSchool(fn () => Payment::where('method', 'FedaPay')->value('transaction_ref')));
    }

    public function test_an_approved_school_webhook_confirms_the_payment_and_notifies_the_parent(): void
    {
        Notification::fake();
        $this->createSchool();
        $data = $this->seedSchoolData(150000);
        $this->enableFedaPay($this->tokenFor('school_admin'));
        $paymentId = $this->inSchool(fn () => Payment::create([
            'reference' => 'REC-T1', 'fee_assignment_id' => $data['assignment_id'], 'student_id' => $data['student_id'],
            'amount' => 50000, 'method' => 'FedaPay', 'transaction_ref' => '7001', 'status' => 'pending',
        ])->id);
        Http::preventStrayRequests();
        Http::fake([self::API.'/transactions/7001' => Http::response(['v1/transaction' => ['id' => 7001, 'status' => 'approved', 'amount' => 50000]])]);

        $body = json_encode(['name' => 'transaction.approved', 'entity' => ['id' => 7001]]);
        $timestamp = time();
        $this->call('POST', '/api/v1/webhooks/fedapay/'.$this->tenant->code, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FEDAPAY_SIGNATURE' => 't='.$timestamp.',s='.hash_hmac('sha256', $timestamp.'.'.$body, 'wh_sandbox_school'),
        ], $body)->assertOk();

        $this->inSchool(function () use ($paymentId, $data) {
            $this->assertSame('paid', Payment::find($paymentId)->status);
            $this->assertSame(50000, FeeAssignment::find($data['assignment_id'])->paid_amount);
            Notification::assertSentTo(User::find($data['parent_user_id']), SchoolNotification::class, fn ($n) => $n->title === 'Paiement reçu');
        });
    }

    public function test_without_online_payments_the_request_goes_to_the_accountant(): void
    {
        Notification::fake();
        $this->createSchool();
        $data = $this->seedSchoolData();
        $accountantId = $this->inSchool(function () {
            $this->tokenFor('accountant');

            return User::where('email', 'accountant@ecole.test')->value('id');
        });
        Http::preventStrayRequests();

        $this->withToken($this->parentToken($data['parent_code']))
            ->postJson("/api/v1/parent/payments/{$data['assignment_id']}/checkout", ['method' => 'Mobile money'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->inSchool(fn () => Notification::assertSentTo(User::find($accountantId), SchoolNotification::class));
    }
}
