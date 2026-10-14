<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('unpaid_waiver')->default(false);               // dérogation à la règle bulletins / impayés
            $table->string('unpaid_waiver_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['unpaid_waiver', 'unpaid_waiver_note']);
        });
    }
};
