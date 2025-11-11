<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_verified_at', 'remember_token']);
            $table->string('id')->change();
            $table->enum('role', ['STUDENT', 'ADVISOR', 'CHAIRPERSON', 'SUPER_ADMIN'])->default('CHAIRPERSON')->after('name');
            $table->string('faculty_id')->after('role');
            $table->string('department_id')->after('faculty_id');
            $table->string('advisor_id')->nullable()->after('department_id');
            $table->float('gpa')->nullable()->after('advisor_id');
            $table->integer('credits')->nullable()->after('gpa');
            $table->integer('scholarship_hour')->nullable()->after('credits');
            $table->string('reset_token')->nullable()->after('scholarship_hour');
            $table->timestamp('reset_token_expiry')->nullable()->after('reset_token');
            
            $table->foreign('faculty_id')->references('id')->on('faculties');
            $table->foreign('department_id')->references('id')->on('departments');
            $table->foreign('advisor_id')->references('id')->on('users');
            
            $table->index(['department_id']);
            $table->index(['faculty_id']);
            $table->index(['role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['faculty_id']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['advisor_id']);
            $table->dropIndex(['department_id']);
            $table->dropIndex(['faculty_id']);
            $table->dropIndex(['role']);
            
            $table->dropColumn([
                'role', 'faculty_id', 'department_id', 'advisor_id', 
                'gpa', 'credits', 'scholarship_hour', 'reset_token', 'reset_token_expiry'
            ]);
            
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
        });
    }
};
