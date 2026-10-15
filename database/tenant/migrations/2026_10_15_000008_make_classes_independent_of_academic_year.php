<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une classe n'appartient plus à une année scolaire : l'année est portée par les
 * inscriptions (enrollments), les frais et les périodes. Les écoles créées après ce
 * changement n'ont jamais la colonne ; celles qui existent sont converties ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('class_rooms', 'academic_year_id')) {
            return;
        }

        Schema::table('class_rooms', function (Blueprint $table) {
            $table->dropForeign(['academic_year_id']);
        });

        Schema::table('class_rooms', function (Blueprint $table) {
            $table->dropUnique(['academic_year_id', 'campus_id', 'name']);
        });

        Schema::table('class_rooms', function (Blueprint $table) {
            $table->dropColumn('academic_year_id');
        });

        // Une classe reconduite d'année en année ne doit exister qu'une fois par établissement
        Schema::table('class_rooms', function (Blueprint $table) {
            $table->unique(['campus_id', 'name']);
        });
    }

    public function down(): void
    {
        // Retour arrière volontairement non géré : l'année d'origine des classes est perdue.
    }
};
