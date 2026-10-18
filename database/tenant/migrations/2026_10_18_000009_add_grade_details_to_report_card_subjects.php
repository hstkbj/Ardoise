<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Détail imprimé sur le bulletin : notes d'interrogation et leur moyenne, notes de devoir, rang dans la matière. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_card_subjects', function (Blueprint $table) {
            $table->json('interro_scores')->nullable()->after('coefficient');   // notes sur 20, ordre chronologique
            $table->decimal('interro_average', 5, 2)->nullable()->after('interro_scores');
            $table->json('devoir_scores')->nullable()->after('interro_average');
            $table->unsignedSmallInteger('rank')->nullable()->after('average');
        });
    }

    public function down(): void
    {
        Schema::table('report_card_subjects', function (Blueprint $table) {
            $table->dropColumn(['interro_scores', 'interro_average', 'devoir_scores', 'rank']);
        });
    }
};
