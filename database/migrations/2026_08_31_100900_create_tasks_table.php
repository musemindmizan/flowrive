<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The busiest table in the product, so it stays narrow and every index below
 * is earned by a screen that runs that query on every load.
 *
 * `board_column_id` is the only status — there is deliberately no status enum
 * to drift away from it. `position` is a fractional rank so a Kanban drag
 * writes exactly one row instead of renumbering the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            // Denormalised tenant id: immutable after insert, keeps every
            // tenant-scoped query and index off a join
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_column_id')->constrained()->restrictOnDelete();

            $table->unsignedInteger('number');
            $table->string('title', 200);
            $table->mediumText('description')->nullable();

            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');

            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('estimate_hours', 5, 2)->nullable();

            // Fractional rank: new value is the midpoint of its neighbours
            $table->decimal('position', 20, 10)->default(0);

            $table->timestamp('completed_at')->nullable();

            // Counter caches
            $table->unsignedSmallInteger('comments_count')->default(0);
            $table->unsignedSmallInteger('attachments_count')->default(0);
            $table->unsignedSmallInteger('subtasks_total')->default(0);
            $table->unsignedSmallInteger('subtasks_completed')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Display key: projects.key + number, e.g. WEB-118
            $table->unique(['project_id', 'number']);

            // Board: one project's cards, grouped by column, in rank order
            $table->index(['project_id', 'board_column_id', 'position']);
            // Dashboard "My tasks": open tasks for one person, soonest due first
            $table->index(['assignee_id', 'completed_at', 'due_date']);
            // Overdue and upcoming deadlines across the workspace
            $table->index(['workspace_id', 'completed_at', 'due_date']);
            // Task list facets
            $table->index(['project_id', 'priority', 'due_date']);

            // ⌘K palette, always filtered by workspace_id first
            $table->fullText(['title', 'description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
