<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Concrete rather than polymorphic: a file always belongs to a project and
 * only sometimes to a task. That makes the project Files tab one indexed read
 * and the workspace storage meter a single SUM().
 *
 * Only metadata is relational — the bytes live on the storage disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();

            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum', 64)->nullable();

            $table->timestamps();

            // Project Files tab, newest first
            $table->index(['project_id', 'id']);
            // Task detail attachments
            $table->index(['task_id', 'id']);
            // Storage quota: SUM(size_bytes) for one workspace
            $table->index('workspace_id');
            // Dedupe within a workspace
            $table->index(['workspace_id', 'checksum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
