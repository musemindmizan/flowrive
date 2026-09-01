<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant root. Every domain row belongs to exactly one workspace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 60)->unique();

            $table->enum('plan', ['free', 'team', 'business'])->default('team');
            $table->unsignedSmallInteger('seat_limit')->default(25);
            $table->unsignedBigInteger('storage_limit_bytes')->default(2 * 1024 * 1024 * 1024);
            $table->enum('default_member_role', ['admin', 'manager', 'member'])->default('member');

            // Owner is history: soft-delete the user rather than erasing the record
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();

            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};
