<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The starter kit's users table stays untouched so future kit updates never
 * conflict; FlowRive's profile, preference and presence columns are added here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Profile
            $table->string('avatar_path')->nullable()->after('password');
            $table->string('job_title', 120)->nullable()->after('avatar_path');
            $table->string('phone', 40)->nullable()->after('job_title');
            $table->text('bio')->nullable()->after('phone');
            $table->string('department', 60)->nullable()->after('bio');

            // Localisation
            $table->string('timezone', 64)->default('UTC')->after('department');
            $table->string('locale', 10)->default('en')->after('timezone');
            $table->string('date_format', 20)->default('M j, Y')->after('locale');
            $table->unsignedTinyInteger('week_starts_on')->default(1)->after('date_format');
            $table->time('working_hours_start')->nullable()->after('week_starts_on');
            $table->time('working_hours_end')->nullable()->after('working_hours_start');

            // Notification delivery preferences that are per-user, not per-event
            $table->enum('digest_frequency', ['off', 'daily', 'weekly'])
                ->default('daily')
                ->after('working_hours_end');
            $table->time('quiet_hours_start')->nullable()->after('digest_frequency');
            $table->time('quiet_hours_end')->nullable()->after('quiet_hours_start');

            // Presence is derived from this at read time — never stored as a status
            $table->timestamp('last_seen_at')->nullable()->index()->after('quiet_hours_end');

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar_path', 'job_title', 'phone', 'bio', 'department',
                'timezone', 'locale', 'date_format', 'week_starts_on',
                'working_hours_start', 'working_hours_end',
                'digest_frequency', 'quiet_hours_start', 'quiet_hours_end',
                'last_seen_at', 'deleted_at',
            ]);
        });
    }
};
