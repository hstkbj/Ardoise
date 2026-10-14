<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base école :
 *  - mot de passe provisoire à changer à la première connexion ;
 *  - double authentification (TOTP) ;
 *  - permission « journal d'activité » (accordée à la direction) et index du journal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->text('two_factor_secret')->nullable()->after('must_change_password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('action');
        });

        if (! DB::table('permissions')->where('key', 'activity_log.view')->exists()) {
            $permissionId = DB::table('permissions')->insertGetId(['key' => 'activity_log.view', 'group' => 'activity_log', 'label' => 'Consulter — journal d’activité']);
            $directorId = DB::table('roles')->where('key', 'director')->value('id');

            if ($directorId) {
                DB::table('permission_role')->insert(['permission_id' => $permissionId, 'role_id' => $directorId]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'activity_log.view')->delete();

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['action']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['must_change_password', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
