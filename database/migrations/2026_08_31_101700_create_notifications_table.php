<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's standard notifications shape, plus the two columns the
 * notification centre needs: `category` for its tabs and `actor_id` so the
 * list can show who did the thing without unpacking the JSON payload.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');

            $table->enum('category', ['mention', 'task', 'project', 'system'])->default('system');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // One user, optional category tab, unread first, newest first
            $table->index(['notifiable_id', 'category', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
