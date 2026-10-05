<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Élèves, inscriptions (historique des classes), parents et liens parent-enfant. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('matricule')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender', 1)->nullable();              // F | M
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status')->default('active');          // active | transferred | archived
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->date('enrolled_on');
            $table->date('left_on')->nullable();
            $table->string('status')->default('active');          // active | left | transferred
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['class_room_id', 'status']);
        });

        Schema::create('parent_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('profession')->nullable();
            $table->string('status')->default('pending');         // pending (code jamais utilisé) | active | inactive
            $table->text('access_code')->nullable();              // chiffré : réimpression de la fiche
            $table->timestamp('access_code_generated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('parent_student', function (Blueprint $table) {
            $table->foreignId('parent_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('relation')->nullable();               // Mère | Père | Tuteur | Autre
            $table->boolean('is_primary')->default(false);
            $table->primary(['parent_profile_id', 'student_id']);
        });
    }

    public function down(): void
    {
        foreach (['parent_student', 'parent_profiles', 'enrollments', 'students'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
