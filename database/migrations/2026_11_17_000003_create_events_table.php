<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            // Sub-events (e.g. a workshop inside an expo) point at their parent event.
            $table->foreignId('parent_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 20); // expo, summit, workshop
            $table->text('description')->nullable();
            $table->string('venue');
            $table->string('city')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // Capacity & attendance rules
            $table->dateTime('registration_opens_at');
            $table->dateTime('registration_closes_at');
            $table->unsignedInteger('capacity');
            $table->decimal('ticket_price', 10, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft, published, cancelled
            $table->timestamps();

            $table->index(['chapter_id', 'status']);
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
