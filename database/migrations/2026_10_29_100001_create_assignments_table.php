<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('instructions');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('attachment_mime', 150)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->dateTime('due_at');
            $table->unsignedSmallInteger('max_score')->default(100);
            $table->string('status', 20)->default('draft');
            $table->boolean('allows_text_response')->default(true);
            $table->boolean('allows_resubmission')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['subject_id', 'class_id'], 'assignments_subject_class_index');
            $table->index(['class_id', 'status', 'due_at'], 'assignments_class_status_due_index');
            $table->index(['teacher_id', 'status'], 'assignments_teacher_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
