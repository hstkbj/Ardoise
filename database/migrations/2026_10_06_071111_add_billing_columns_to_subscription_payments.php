<?php

use App\Support\PlanFeatures;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paiement en ligne des abonnements (FedaPay) et modules des plans.
 *  - subscription_payments : plan payé, durée, prestataire et transaction.
 *  - plans.features : libellés libres convertis en clés de modules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('tenant_id')->constrained('plans')->nullOnDelete();
            $table->unsignedSmallInteger('months')->nullable()->after('amount');
            $table->string('provider')->default('manual')->after('method');
            $table->string('provider_reference')->nullable()->after('provider')->index();
            $table->json('meta')->nullable()->after('paid_at');
        });

        DB::table('plans')->orderBy('id')->each(function (object $plan): void {
            $features = json_decode((string) $plan->features, true) ?: [];
            DB::table('plans')->where('id', $plan->id)->update(['features' => json_encode(PlanFeatures::fromLegacy($features))]);
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropIndex(['provider_reference']);
            $table->dropColumn(['months', 'provider', 'provider_reference', 'meta']);
        });
    }
};
