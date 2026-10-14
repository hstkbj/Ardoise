<?php

namespace App\Services\Billing;

use App\Models\Central\PlatformSetting;
use App\Models\Central\SubscriptionPayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Factures des abonnements payés.
 *
 *  - Numérotation continue par année (FAC-2026-000001), attribuée quand le paiement est confirmé.
 *  - Émetteur : Paramètres plateforme → Facturation (raison sociale, adresse, IFU / NIF, RCCM, TVA).
 *  - PDF si barryvdh/laravel-dompdf est installé, sinon page HTML imprimable.
 */
class SubscriptionInvoice
{
    /** Attribue le numéro de facture d'un paiement encaissé (idempotent). */
    public function assignNumber(SubscriptionPayment $payment): string
    {
        if ($payment->invoice_number) {
            return $payment->invoice_number;
        }

        $year = ($payment->paid_at ?? now())->format('Y');

        for ($attempt = 0; ; $attempt++) {
            try {
                return DB::connection('central')->transaction(function () use ($payment, $year) {
                    $last = SubscriptionPayment::where('invoice_number', 'like', "FAC-{$year}-%")->lockForUpdate()->max('invoice_number');
                    $number = sprintf('FAC-%s-%06d', $year, $last ? ((int) substr($last, -6)) + 1 : 1);
                    $payment->forceFill(['invoice_number' => $number])->save();

                    return $number;
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }
            }
        }
    }

    /** @return array<string, mixed> */
    public function data(SubscriptionPayment $payment): array
    {
        $payment->loadMissing('tenant', 'plan');
        $settings = PlatformSetting::section('invoicing');
        $identity = PlatformSetting::section('identity');
        $vatRate = (float) str_replace(',', '.', (string) ($settings['vat_rate'] ?? 0));
        $total = (int) $payment->amount;
        $net = $vatRate > 0 ? (int) round($total / (1 + $vatRate / 100)) : $total;
        $meta = $payment->meta ?? [];

        return [
            'payment' => $payment,
            'number' => $payment->invoice_number ?? $this->assignNumber($payment),
            'date' => ($payment->paid_at ?? $payment->updated_at)?->format('d/m/Y'),
            'seller' => [
                'name' => $settings['company_name'] ?? $identity['platform_name'] ?? config('app.name'),
                'address' => $settings['address'] ?? null,
                'tax_id' => $settings['tax_id'] ?? null,
                'registration' => $settings['registration'] ?? null,
                'email' => $settings['email'] ?? $identity['support_email'] ?? null,
                'phone' => $settings['phone'] ?? $identity['support_phone'] ?? null,
            ],
            'buyer' => [
                'name' => $payment->tenant?->name,
                'contact' => $payment->customer ?? $payment->tenant?->admin_name,
                'email' => $payment->tenant?->admin_email,
                'code' => $payment->tenant?->code,
            ],
            'description' => 'Abonnement '.config('app.name').($payment->plan ? ' — formule '.$payment->plan->name : ''),
            'period' => isset($meta['period_start'], $meta['period_end'])
                ? 'Du '.date('d/m/Y', strtotime($meta['period_start'])).' au '.date('d/m/Y', strtotime($meta['period_end']))
                : ($payment->months ? $payment->months.' mois' : null),
            'net' => $net,
            'vat_rate' => $vatRate,
            'vat' => $total - $net,
            'total' => $total,
            'currency' => 'FCFA',
            'footer' => $settings['footer'] ?? ($vatRate > 0 ? null : 'TVA non applicable.'),
        ];
    }

    public function hasPdfRenderer(): bool
    {
        return class_exists(Pdf::class);
    }

    public function html(SubscriptionPayment $payment, bool $forPdf = false): string
    {
        return view('pdf.subscription-invoice', $this->data($payment) + ['forPdf' => $forPdf])->render();
    }

    /** Contenu binaire du PDF (dompdf requis). */
    public function pdf(SubscriptionPayment $payment): string
    {
        return Pdf::loadHTML($this->html($payment, forPdf: true))->setPaper('a4')->output();
    }

    public function filename(SubscriptionPayment $payment): string
    {
        return 'facture-'.($payment->invoice_number ?? $payment->reference).'.pdf';
    }

    public function download(SubscriptionPayment $payment): Response
    {
        abort_unless($payment->status === 'paid', 404, 'Facture disponible une fois le paiement confirmé.');
        $this->assignNumber($payment);

        if ($this->hasPdfRenderer()) {
            return response($this->pdf($payment), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$this->filename($payment).'"',
            ]);
        }

        return response($this->html($payment))->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
