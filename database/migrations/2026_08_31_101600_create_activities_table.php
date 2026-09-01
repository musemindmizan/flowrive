<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The activity feed. Genuinely polymorphic — an entry points at a task, a
 * comment, a member or a project — but project_id and workspace_id are
 * denormalised so the feed never joins just to filter.
 *
 * Append-only: no updated_at, and no foreign key on the subject, because the
 * log outlives the things it describes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('verb', 40);
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();

            $table->timestamp('created_at')->nullable();

            // id DESC is already chronological on an append-only table, so the
            // sort disappears and the index stays narrow
            $table->index(['project_id', 'id']);
            $table->index(['workspace_id', 'id']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
