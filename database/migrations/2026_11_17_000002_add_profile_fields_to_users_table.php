<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the profile and chapter fields that complement the role and company columns of the users table.
     * Chapter managers and gate staff belong to one regional chapter.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title')->nullable();
            $table->string('phone', 40)->nullable();
            $table->foreignId('chapter_id')->nullable()->constrained()->nullOnDelete();
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropConstrainedForeignId('chapter_id');
            $table->dropColumn(['job_title', 'phone']);
        });
    }
};
