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
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('ticket_code');
            $table->string('payment_status')->default('pending')->after('payment_method');
            $table->timestamp('paid_at')->nullable()->after('payment_status');
            $table->foreignId('verified_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['payment_method', 'payment_status', 'paid_at', 'verified_by']);
        });
    }
};
