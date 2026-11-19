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
        Schema::table('events', function (Blueprint $table) {
            // Venue photo on the private disk; events without one show a drawn cover instead.
            $table->string('cover_path')->nullable()->after('city');
            // Photographer credit shown under the photo, e.g. for Creative Commons pictures.
            $table->string('cover_credit', 255)->nullable()->after('cover_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['cover_path', 'cover_credit']);
        });
    }
};
