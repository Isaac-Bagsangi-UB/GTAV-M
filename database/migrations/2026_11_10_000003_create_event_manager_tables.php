<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('Summit');
            $table->text('description')->nullable();
            $table->string('venue');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('registration_cutoff');
            $table->unsignedInteger('capacity');
            $table->decimal('ticket_price', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ticket_code')->unique();
            $table->timestamp('registered_at');
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('checked_in_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('checked_in_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('registrations');
        Schema::dropIfExists('events');
        Schema::dropIfExists('chapters');
    }
};
