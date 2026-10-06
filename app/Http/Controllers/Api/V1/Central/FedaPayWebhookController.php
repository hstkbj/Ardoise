<?php

namespace App\Http\Controllers\Api\V1\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\SubscriptionPayment;
use App\Models\Central\Tenant;
use App\Models\Tenant\Payment;
use App\Services\Billing\SubscriptionBilling;
use App\Services\Payments\FedaPay\FedaPayClient;
use App\Services\Payments\FedaPay\FedaPayException;
use App\Services\Payments\SchoolFedaPay;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhooks FedaPay (événements transaction.*).
 *
 *  - POST /webhooks/fedapay          compte de la plateforme : abonnements des écoles
 *  - POST /webhooks/fedapay/{code}   compte d'une école : frais payés par les parents
 *
 * La signature (X-FEDAPAY-SIGNATURE) est vérifiée, puis le statut est relu
 * auprès de l'API FedaPay : le contenu du webhook n'est jamais cru tel quel.
 */
class FedaPayWebhookController extends Controller
{
    public function platform(Request $request, SubscriptionBilling $billing): JsonResponse
    {
        if (! FedaPayClient::verifySignature($request->getContent(), $request->header('X-FEDAPAY-SIGNATURE'), config('ardoise.fedapay.webhook_secret'), config('ardoise.fedapay.webhook_tolerance'))) {
            return response()->json(['message' => 'Signature invalide.'], 400);
        }

        $transactionId = $this->transactionId($request);
        $payment = $transactionId ? SubscriptionPayment::where('provider', 'fedapay')->where('provider_reference', $transactionId)->first() : null;

        if ($payment) {
            try {
                $billing->syncFromProvider($payment);
            } catch (FedaPayException $e) {
                Log::warning('Webhook FedaPay (abonnement) : vérification impossible', ['reference' => $payment->reference, 'error' => $e->getMessage()]);

                return response()->json(['message' => 'Vérification impossible, réessayez.'], 503);
            }
        }

        return response()->json(['received' => true]);
    }

    public function school(Request $request, string $tenantCode, TenantManager $tenancy, SchoolFedaPay $fedapay): JsonResponse
    {
        $tenant = Tenant::where('code', $tenantCode)->first();

        if (! $tenant) {
            return response()->json(['message' => 'École inconnue.'], 404);
        }

        return $tenancy->run($tenant, function () use ($request, $fedapay) {
            if (! FedaPayClient::verifySignature($request->getContent(), $request->header('X-FEDAPAY-SIGNATURE'), $fedapay->webhookSecret(), config('ardoise.fedapay.webhook_tolerance'))) {
                return response()->json(['message' => 'Signature invalide.'], 400);
            }

            $transactionId = $this->transactionId($request);
            $payment = $transactionId ? Payment::where('method', 'FedaPay')->where('transaction_ref', $transactionId)->first() : null;

            if ($payment) {
                try {
                    $fedapay->sync($payment);
                } catch (FedaPayException $e) {
                    Log::warning('Webhook FedaPay (école) : vérification impossible', ['tenant' => tenant()->code, 'reference' => $payment->reference, 'error' => $e->getMessage()]);

                    return response()->json(['message' => 'Vérification impossible, réessayez.'], 503);
                }
            }

            return response()->json(['received' => true]);
        });
    }

    /** Identifiant de la transaction dans l'événement : { name, entity: { id, … } }. */
    protected function transactionId(Request $request): ?string
    {
        $event = (string) ($request->input('name') ?? $request->input('type') ?? '');

        if ($event !== '' && ! str_starts_with($event, 'transaction.')) {
            return null;
        }

        $id = $request->input('entity.id') ?? $request->input('object_id') ?? $request->input('data.object.id');

        return $id !== null ? (string) $id : null;
    }
}
