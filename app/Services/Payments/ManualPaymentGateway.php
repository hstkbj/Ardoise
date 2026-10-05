<?php

namespace App\Services\Payments;

use App\Models\Tenant\FeeAssignment;
use App\Models\Tenant\Setting;
use Illuminate\Support\Str;

/**
 * Pas d'agrégateur branché : la demande est enregistrée « en attente » et le
 * parent reçoit les instructions ; la comptabilité valide à réception.
 * Remplacer par un pilote mobile money (Orange Money, MTN MoMo, Wave…).
 */
class ManualPaymentGateway implements PaymentGateway
{
    public function initiate(FeeAssignment $assignment, int $amount, string $method, ?string $phone): array
    {
        $contact = Setting::get('contact', 'phone');

        return [
            'status' => 'pending',
            'transaction_ref' => 'REQ-'.Str::upper(Str::random(8)),
            'redirect_url' => null,
            'message' => 'Votre demande de paiement a été transmise à l’établissement. '
                .'Réglez '.number_format($amount, 0, ',', ' ').' FCFA par '.$method
                .($contact ? ' (contact : '.$contact.')' : '').' ; le paiement sera confirmé par la comptabilité.',
        ];
    }
}
