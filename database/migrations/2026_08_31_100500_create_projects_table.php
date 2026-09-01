<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregate root. `next_task_number` allocates human-readable task keys
 * (WEB-118) inside a transaction — never MAX(number)+1, which races.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            $table->string('key', 6);
            $table->string('name', 120);
            $table->text('description')->nullable();

            $table->enum('status', ['planning', 'in_progress', 'in_review', 'on_hold', 'completed'])
                ->default('planning');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('visibility', ['members', 'workspace'])->default('members');

            $table->string('color', 20)->default('brand');
            $table->string('cover_path')->nullable();

            $table->date('start_date')->nullable();
            $table->date('deadline')->nullable();

            $table->foreignId('lead_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            // Task key allocator
            $table->unsignedInteger('next_task_number')->default(1);

            // Counter caches — the dashboard cannot afford COUNT(*) per card
            $table->unsignedInteger('tasks_total')->default(0);
            $table->unsignedInteger('tasks_completed')->default(0);

            $table->timestamp('archived_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'key']);
            $table->index(['workspace_id', 'status']);
            $table->index('deadline');
            $table->fullText(['name', 'description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
