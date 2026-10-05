<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Base centrale : écoles (tenants), domaines, plans, abonnements, codes parents. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price')->nullable();        // FCFA, entier
            $table->string('currency', 3)->default('XOF');
            $table->string('period')->default('monthly');           // monthly | yearly
            $table->unsignedInteger('max_schools')->nullable();
            $table->unsignedInteger('max_students')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->json('features')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();                       // sous-domaine
            $table->string('database')->unique();
            $table->string('db_username')->nullable();
            $table->text('db_password')->nullable();                // chiffré
            $table->string('status')->default('trial');             // active | trial | suspended | cancelled
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('admin_name')->nullable();
            $table->string('admin_email')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('status')->default('trial');             // active | trial | expired | cancelled | suspended
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('customer')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('method')->nullable();
            $table->string('reference')->unique();
            $table->string('status')->default('paid');              // paid | pending | failed
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        /*
         * Index des codes d'accès parents : permet de retrouver l'école ET le
         * parent à partir du seul code. On ne stocke qu'une empreinte HMAC.
         */
        Schema::create('parent_access_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parent_profile_id');        // id dans la base de l'école
            $table->string('code_hash', 64)->unique();
            $table->string('code_last4', 4);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'parent_profile_id']);
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('requester_user_id')->nullable();
            $table->string('requester_name');
            $table->string('requester_email')->nullable();
            $table->string('subject');
            $table->string('category')->nullable();
            $table->string('priority')->default('normal');          // low | normal | high
            $table->string('status')->default('open');              // open | in_progress | resolved
            $table->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->string('author_type');                          // client | support
            $table->string('author_name');
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('audience')->default('Tous les tenants');
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // Statistiques collectées chaque jour dans chaque base école (stratégie « agrégation »)
        Schema::create('usage_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('students')->default(0);
            $table->unsignedInteger('teachers')->default(0);
            $table->unsignedInteger('users')->default(0);
            $table->unsignedInteger('active_users')->default(0);
            $table->unsignedInteger('storage_mb')->default(0);
            $table->unsignedInteger('sms_sent')->default(0);
            $table->json('campuses')->nullable();
            $table->json('admins')->nullable();
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'date']);
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('section')->unique();
            $table->json('values');
            $table->timestamps();
        });

        Schema::create('demo_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('school');
            $table->string('city')->nullable();
            $table->string('email');
            $table->string('phone');
            $table->string('students')->nullable();
            $table->unsignedInteger('sites')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['newsletter_subscribers', 'demo_requests', 'platform_settings', 'usage_snapshots', 'platform_announcements', 'support_messages', 'support_tickets', 'parent_access_codes', 'subscription_payments', 'subscriptions', 'domains', 'tenants', 'plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
