<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_card_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->unsignedTinyInteger('assignment_weight')->default(60);
            $table->unsignedTinyInteger('quiz_weight')->default(40);
            $table->unsignedTinyInteger('passing_score')->default(75);
            $table->timestamps();

            $table->unique(['subject_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_settings');
    }
};
