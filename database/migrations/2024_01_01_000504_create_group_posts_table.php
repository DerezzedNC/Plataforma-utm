<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Verificar que las tablas necesarias existen antes de crear las foreign keys
        $hasGroups = Schema::hasTable('groups');
        $hasUsers = Schema::hasTable('users');
        $hasAnnouncements = Schema::hasTable('announcements');

        // Crear la tabla primero sin foreign keys si las tablas referenciadas no existen
        Schema::create('group_posts', function (Blueprint $table) use ($hasGroups, $hasUsers, $hasAnnouncements) {
            $table->id();
            
            // Crear columnas sin foreign key primero si las tablas no existen
            if (!$hasGroups) {
                $table->unsignedBigInteger('group_id');
            } else {
                $table->foreignId('group_id')
                    ->constrained('groups')
                    ->onDelete('cascade');
            }
            
            if (!$hasAnnouncements) {
                $table->unsignedBigInteger('announcement_id')->nullable();
            } else {
                $table->foreignId('announcement_id')
                    ->nullable()
                    ->constrained('announcements')
                    ->onDelete('cascade');
            }
            
            $table->string('title');
            $table->text('content');
            
            if (!$hasUsers) {
                $table->unsignedBigInteger('posted_by');
            } else {
                $table->foreignId('posted_by')
                    ->constrained('users')
                    ->onDelete('cascade');
            }
            
            $table->timestamps();
        });

        // Si las tablas no existían pero ahora sí, agregar las foreign keys usando DB::statement
        // Esto se ejecutará en una migración posterior cuando las tablas estén creadas
        if (!$hasGroups || !$hasUsers || !$hasAnnouncements) {
            // Las foreign keys se agregarán en una migración posterior
            // o se pueden agregar manualmente después de que las tablas existan
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_posts');
    }
};




