<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Établissements, années, périodes, niveaux, matières, enseignants, classes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('manager')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('campus_user', function (Blueprint $table) {
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['campus_id', 'user_id']);
        });

        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();                     // 2026-2027
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('period_type')->default('trimester');  // trimester | semester
            $table->string('status')->default('upcoming');        // upcoming | active | closed
            $table->timestamps();
        });

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('position');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });

        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();                     // CP … Tle
            $table->string('cycle');                              // primaire | college | lycee
            $table->unsignedTinyInteger('position');
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('contract')->default('permanent');     // permanent | vacataire
            $table->date('hired_on')->nullable();
            $table->string('status')->default('active');          // active | on_leave | inactive
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('category')->nullable();
            $table->string('level')->nullable();                  // Primaire | Collège | Lycée
            $table->decimal('default_coefficient', 4, 2)->default(1);
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('campus_teacher', function (Blueprint $table) {
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->primary(['campus_id', 'teacher_id']);
        });

        Schema::create('subject_teacher', function (Blueprint $table) {
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->primary(['subject_id', 'teacher_id']);
        });

        Schema::create('class_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained();
            $table->foreignId('head_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('room')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['academic_year_id', 'campus_id', 'name']);
        });

        // Matière enseignée dans une classe : enseignant + coefficient propres à la classe
        Schema::create('class_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('coefficient', 4, 2)->default(1);
            $table->timestamps();
            $table->unique(['class_room_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['class_subject', 'class_rooms', 'subject_teacher', 'campus_teacher', 'subjects', 'teachers', 'levels', 'terms', 'academic_years', 'campus_user', 'campuses'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
