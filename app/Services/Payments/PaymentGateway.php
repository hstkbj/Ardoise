<?php

namespace App\Services\Payments;

use App\Models\Tenant\FeeAssignment;

interface PaymentGateway
{
    /**
     * Démarre un paiement en ligne pour une échéance.
     *
     * @return array{status: string, message: string, transaction_ref: string|null, redirect_url: string|null}
     */
    public function initiate(FeeAssignment $assignment, int $amount, string $method, ?string $phone): array;
}
