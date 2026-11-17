<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('reference_code', 16)->unique(); // printed on the pass, typed by hand if needed
            $table->string('qr_token', 64)->unique();       // encoded in the QR code
            $table->string('status', 20)->default('confirmed'); // confirmed, cancelled
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->dateTime('checked_in_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            // One row per person per event: this is what removes duplicate records.
            $table->unique(['user_id', 'event_id']);
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
