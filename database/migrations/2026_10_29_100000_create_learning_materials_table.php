<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->string('file_mime', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('external_url', 2048)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['subject_id', 'class_id'], 'learning_materials_subject_class_index');
            $table->index(['class_id', 'is_published', 'published_at'], 'learning_materials_class_published_index');
            $table->index('teacher_id', 'learning_materials_teacher_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_materials');
    }
};
