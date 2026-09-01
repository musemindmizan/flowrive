<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The board is data, not an enum: members rename and add columns. `category`
 * stays fixed so "what counts as done" survives any rename, and reports group
 * by it rather than by the display name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('name', 60);
            $table->enum('category', ['backlog', 'todo', 'in_progress', 'review', 'done']);
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedSmallInteger('wip_limit')->nullable();

            $table->timestamps();

            $table->unique(['project_id', 'name']);
            $table->index(['project_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_columns');
    }
};
