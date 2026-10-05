<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Annonces, documents, frais, échéances et paiements. Montants en FCFA (entiers). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->json('audiences')->nullable();                // school | class | teachers | parents | students
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('class_room_id')->nullable()->constrained()->nullOnDelete();
            $table->json('channels')->nullable();                 // in_app | email | sms | push
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('category');                           // eleve | enseignant | administratif | certificat | justificatif
            $table->string('name');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime')->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('category');                           // scolarite | inscription | cantine | transport | autre
            $table->unsignedBigInteger('amount');
            $table->unsignedTinyInteger('installments')->default(1);
            $table->date('first_due_date')->nullable();
            $table->unsignedTinyInteger('interval_months')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('fee_level', function (Blueprint $table) {
            $table->foreignId('fee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->primary(['fee_id', 'level_id']);
        });

        // Échéance due par un élève
        Schema::create('fee_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('installment_no');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->date('due_date')->nullable();
            $table->string('status')->default('pending');         // pending | partial | paid
            $table->timestamps();
            $table->unique(['fee_id', 'student_id', 'installment_no']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('fee_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('method');
            $table->string('transaction_ref')->nullable();
            $table->date('paid_at')->nullable();
            $table->string('status')->default('paid');            // paid | pending | failed | cancelled
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        foreach (['payments', 'fee_assignments', 'fee_level', 'fees', 'documents', 'announcements'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
