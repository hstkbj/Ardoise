<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Factures d'abonnement : numéro séquentiel par année (FAC-2026-000001),
 * attribué au paiement confirmé. Les paiements déjà encaissés sont numérotés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->unique()->after('reference');
        });

        $counters = [];

        DB::table('subscription_payments')->where('status', 'paid')->orderBy('paid_at')->orderBy('id')->each(function (object $payment) use (&$counters): void {
            $year = substr((string) ($payment->paid_at ?? $payment->created_at ?? now()), 0, 4);
            $counters[$year] = ($counters[$year] ?? 0) + 1;

            DB::table('subscription_payments')->where('id', $payment->id)->update([
                'invoice_number' => sprintf('FAC-%s-%06d', $year, $counters[$year]),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
            $table->dropColumn('invoice_number');
        });
    }
};
