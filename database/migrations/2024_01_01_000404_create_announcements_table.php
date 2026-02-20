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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->foreignId('sender_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->enum('target_type', ['all_tutors', 'specific_teachers', 'all_teachers'])
                ->default('all_tutors');
            $table->enum('priority', ['baja', 'media', 'alta'])
                ->default('media');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};




