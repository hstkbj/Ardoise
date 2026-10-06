<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\Plan;
use App\Models\Central\SubscriptionPayment;
use App\Services\Billing\PlanLimits;
use App\Services\Billing\SubscriptionBilling;
use App\Services\Payments\FedaPay\FedaPayClient;
use App\Services\Payments\FedaPay\FedaPayException;
use App\Support\PlanFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Abonnement de l'école, vu par sa direction : état, plan, consommation,
 * paiement en ligne (FedaPay) et historique.
 * Accessible à l'administrateur même quand l'abonnement est expiré.
 */
class BillingController extends Controller
{
    public function __construct(protected SubscriptionBilling $billing) {}

    public function show(PlanLimits $limits, FedaPayClient $fedapay): JsonResponse
    {
        $tenant = tenant()->load('plan');

        return response()->json(['data' => [
            'school' => ['name' => $tenant->name, 'code' => $tenant->code, 'domain' => $tenant->primaryDomain()],
            'subscription' => $this->billing->summary($tenant),
            'usage' => $limits->usage(),
            'modules' => PlanFeatures::options(),
            'plans' => Plan::where('status', 'active')->orderByRaw('price is null')->orderBy('price')->get()->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'price' => $plan->price,
                'period' => $plan->period,
                'features' => PlanFeatures::fromLegacy($plan->features ?? []),
                'limits' => ['schools' => $plan->max_schools, 'students' => $plan->max_students, 'users' => $plan->max_users],
                'current' => $plan->id === $tenant->plan_id,
            ]),
            'payments' => SubscriptionPayment::with('plan')->where('tenant_id', $tenant->id)->latest()->limit(20)->get()->map(fn (SubscriptionPayment $p) => $this->presentPayment($p)),
            'online_payment' => $fedapay->isConfigured(),
            'max_periods' => config('ardoise.billing.max_periods'),
        ]]);
    }

    /** POST /billing/checkout { plan_id, periods } → { url } page de paiement FedaPay */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('central.plans', 'id')->where('status', 'active')],
            'periods' => ['required', 'integer', 'min:1', 'max:'.config('ardoise.billing.max_periods')],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user = $request->user();
        $payment = $this->billing->startCheckout(tenant(), Plan::findOrFail($data['plan_id']), (int) $data['periods'], [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $data['phone'] ?? $user->phone,
        ]);

        return response()->json(['data' => [
            'reference' => $payment->reference,
            'url' => $payment->meta['checkout_url'] ?? null,
        ]], 201);
    }

    /** POST /billing/verify { reference } : au retour de FedaPay, vérifie le paiement auprès de l'API. */
    public function verify(Request $request): JsonResponse
    {
        $reference = $request->validate(['reference' => ['required', 'string', 'max:50']])['reference'];
        $payment = SubscriptionPayment::where('tenant_id', tenant()->id)->where('reference', $reference)->firstOrFail();

        try {
            $payment = $this->billing->syncFromProvider($payment);
        } catch (FedaPayException) {
            // le webhook confirmera le paiement plus tard
        }

        return response()->json(['data' => [
            'payment' => $this->presentPayment($payment->load('plan')),
            'subscription' => $this->billing->summary(tenant()->refresh()),
        ]]);
    }

    /** @return array<string, mixed> */
    protected function presentPayment(SubscriptionPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'reference' => $payment->reference,
            'plan' => $payment->plan?->name,
            'amount' => $payment->amount,
            'months' => $payment->months,
            'method' => $payment->method,
            'status' => $payment->status,
            'paid_at' => $payment->paid_at?->toDateString(),
            'created_at' => $payment->created_at?->toDateString(),
        ];
    }
}
