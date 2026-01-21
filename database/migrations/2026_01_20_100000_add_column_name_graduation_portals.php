<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('graduation_portals', function (Blueprint $table) {
            $table->string('pin_hash', 255)->nullable()->after('pin');
            $table->integer('max_file_size_mb')->default(5)->after('accepted_formats');
            $table->timestamp('closed_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('graduation_portals', function (Blueprint $table) {
            $table->dropColumn([
                'pin_hash',
                'max_file_size_mb',
                'closed_at'
            ]);
        });
    }
};
