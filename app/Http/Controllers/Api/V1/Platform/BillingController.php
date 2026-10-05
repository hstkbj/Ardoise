<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\Central\Subscription;
use App\Models\Central\SubscriptionPayment;
use App\Models\Central\Tenant;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Abonnements et paiements SaaS. */
class BillingController extends Controller
{
    public function subscriptions(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(Subscription::with(['tenant', 'plan']), $request,
            search: ['tenant.name'],
            filters: ['status' => 'status', 'plan_id' => 'plan_id'],
            sorts: ['expires_at' => 'expires_at', 'tenant_name' => 'tenant_id'],
            default: '-id',
        );

        $page->getCollection()->transform(fn (Subscription $s) => [
            'id' => $s->id,
            'tenant_id' => $s->tenant_id,
            'tenant_name' => $s->tenant?->name,
            'plan' => $s->plan?->name,
            'status' => $s->status,
            'starts_at' => $s->starts_at?->toDateString(),
            'expires_at' => $s->expires_at?->toDateString(),
            'amount' => $s->amount,
        ]);

        return ListQuery::json($page);
    }

    public function updateSubscription(Request $request, Subscription $subscription): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'trial', 'expired', 'cancelled', 'suspended'])],
            'expires_at' => ['nullable', 'date'],
            'plan_id' => ['nullable', 'integer', 'exists:central.plans,id'],
            'amount' => ['nullable', 'integer', 'min:0'],
        ]);

        $subscription->update($data);
        $subscription->tenant->update([
            'status' => in_array($data['status'], ['active', 'trial', 'suspended', 'cancelled'], true) ? $data['status'] : 'suspended',
            'expires_at' => $data['expires_at'] ?? $subscription->tenant->expires_at,
            'plan_id' => $data['plan_id'] ?? $subscription->tenant->plan_id,
        ]);

        return response()->json(['message' => 'Abonnement mis à jour.']);
    }

    public function payments(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(SubscriptionPayment::with(['tenant', 'subscription.plan']), $request,
            search: ['reference', 'customer', 'tenant.name'],
            filters: ['status' => 'status', 'tenant_id' => 'tenant_id'],
            sorts: ['paid_at' => 'paid_at', 'amount' => 'amount'],
            default: '-paid_at',
        );

        $page->getCollection()->transform(fn (SubscriptionPayment $p) => [
            'id' => $p->id,
            'reference' => $p->reference,
            'customer' => $p->customer ?? $p->tenant?->admin_name,
            'tenant_name' => $p->tenant?->name,
            'plan' => $p->subscription?->plan?->name,
            'amount' => $p->amount,
            'method' => $p->method,
            'status' => $p->status,
            'paid_at' => $p->paid_at?->toDateString(),
        ]);

        return ListQuery::json($page);
    }

    /** Enregistre un paiement reçu et prolonge l'abonnement. */
    public function storePayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:central.tenants,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', 'max:50'],
            'paid_at' => ['nullable', 'date'],
            'extend_months' => ['nullable', 'integer', 'min:1', 'max:36'],
        ]);

        $tenant = Tenant::with('subscription')->findOrFail($data['tenant_id']);
        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $tenant->subscription?->id,
            'customer' => $tenant->admin_name,
            'amount' => $data['amount'],
            'method' => $data['method'],
            'reference' => 'SUB-'.now()->format('Y').'-'.Str::upper(Str::random(6)),
            'status' => 'paid',
            'paid_at' => $data['paid_at'] ?? now(),
        ]);

        if ($months = $data['extend_months'] ?? null) {
            $from = $tenant->expires_at && $tenant->expires_at->isFuture() ? $tenant->expires_at : now();
            $tenant->update(['status' => 'active', 'expires_at' => $from->copy()->addMonths($months)]);
            $tenant->subscription?->update(['status' => 'active', 'expires_at' => $tenant->expires_at]);
        }

        return response()->json(['data' => ['id' => $payment->id, 'reference' => $payment->reference]], 201);
    }
}
