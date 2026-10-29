<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the announcement fields used by the admin, student, and teacher areas.
     *
     * This migration is intentionally additive so existing announcement rows and
     * the rest of the database are left untouched.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (! Schema::hasColumn('announcements', 'title')) {
                $table->string('title')->default('');
            }

            if (! Schema::hasColumn('announcements', 'content')) {
                $table->text('content')->nullable();
            }

            if (! Schema::hasColumn('announcements', 'visibility')) {
                $table->string('visibility', 20)->default('all');
            }

            if (! Schema::hasColumn('announcements', 'target_role')) {
                $table->string('target_role', 20)->nullable();
            }

            if (! Schema::hasColumn('announcements', 'class_id')) {
                $table->unsignedBigInteger('class_id')->nullable();
            }

            if (! Schema::hasColumn('announcements', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable();
            }

            if (! Schema::hasColumn('announcements', 'is_published')) {
                $table->boolean('is_published')->default(false);
            }

            if (! Schema::hasColumn('announcements', 'published_at')) {
                $table->timestamp('published_at')->nullable();
            }

            $table->index('class_id', 'announcements_class_id_index');
            $table->index('created_by', 'announcements_created_by_index');
            $table->index(['is_published', 'published_at'], 'announcements_published_index');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex('announcements_class_id_index');
            $table->dropIndex('announcements_created_by_index');
            $table->dropIndex('announcements_published_index');

            $columns = [
                'title',
                'content',
                'visibility',
                'target_role',
                'class_id',
                'created_by',
                'is_published',
                'published_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('announcements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
