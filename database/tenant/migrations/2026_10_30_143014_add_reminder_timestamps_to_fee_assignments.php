<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fee_assignments', function (Blueprint $table) {
            $table->timestamp('due_reminder_sent_at')->nullable();
            $table->timestamp('overdue_reminder_sent_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_assignments', function (Blueprint $table) {
            $table->dropColumn(['due_reminder_sent_at', 'overdue_reminder_sent_at']);
        });
    }
};
