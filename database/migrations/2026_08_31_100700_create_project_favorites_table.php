<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sidebar's "Pinned projects" list, per user. Composite primary key —
 * a surrogate id would buy nothing here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->primary(['user_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_favorites');
    }
};
