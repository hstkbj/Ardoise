<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Évaluations, notes, bulletins, présences, emploi du temps, devoirs. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_subject_id')->constrained('class_subject')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('type');                               // devoir | interrogation | examen | composition | controle_continu | oral
            $table->date('date');
            $table->decimal('coefficient', 4, 2)->default(1);
            $table->decimal('max_score', 5, 2)->default(20);
            $table->string('status')->default('draft');           // draft | validated
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->string('comment')->nullable();
            $table->timestamps();
            $table->unique(['assessment_id', 'student_id']);
        });

        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_room_id')->constrained()->cascadeOnDelete();
            $table->decimal('general_average', 5, 2)->nullable();
            $table->unsignedSmallInteger('rank')->nullable();
            $table->unsignedSmallInteger('class_size')->default(0);
            $table->decimal('class_average', 5, 2)->nullable();
            $table->decimal('absences_hours', 6, 1)->default(0);
            $table->unsignedSmallInteger('late_count')->default(0);
            $table->string('head_teacher_comment')->nullable();
            $table->string('council_decision')->nullable();
            $table->string('status')->default('draft');           // draft | published
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'term_id']);
        });

        Schema::create('report_card_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('subject_name');
            $table->string('teacher_name')->nullable();
            $table->decimal('coefficient', 4, 2);
            $table->decimal('average', 5, 2)->nullable();
            $table->decimal('class_average', 5, 2)->nullable();
            $table->string('appreciation')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_room_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('slot', 5);                            // 07:30
            $table->string('status');                             // present | absent | late
            $table->unsignedSmallInteger('minutes_late')->nullable();
            $table->boolean('is_justified')->default(false);
            $table->string('reason')->nullable();
            $table->string('justification_path')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('justified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'date', 'slot']);
            $table->index(['class_room_id', 'date']);
        });

        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('day_of_week');           // 0 = lundi … 5 = samedi
            $table->string('starts_at', 5);
            $table->string('ends_at', 5);
            $table->string('room')->nullable();
            $table->timestamps();
        });

        Schema::create('homework', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->date('due_date');
            $table->json('attachments')->nullable();
            $table->string('status')->default('draft');           // draft | published | closed
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('homework_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homework_id')->constrained('homework')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
            $table->unique(['homework_id', 'student_id']);
        });
    }

    public function down(): void
    {
        foreach (['homework_submissions', 'homework', 'timetable_entries', 'attendances', 'report_card_subjects', 'report_cards', 'grades', 'assessments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
