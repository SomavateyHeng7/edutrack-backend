<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_logs', 'ip_address')) {
                $table->string('ip_address')->nullable()->after('description');
            }
            // Make entity_type and entity_id nullable for login events
            $table->string('entity_type')->nullable()->change();
            $table->string('entity_id')->nullable()->change();
        });

        // Expand the action enum to include LOGIN and LOGOUT
        // PostgreSQL: alter the enum type
        DB::statement("ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check");
        DB::statement("ALTER TABLE audit_logs ALTER COLUMN action TYPE VARCHAR(255)");
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('audit_logs', 'ip_address')) {
                $table->dropColumn('ip_address');
            }
            $table->string('entity_type')->nullable(false)->change();
            $table->string('entity_id')->nullable(false)->change();
        });
    }
};
