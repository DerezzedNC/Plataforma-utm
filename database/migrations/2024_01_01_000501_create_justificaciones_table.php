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
        // Verificar si las tablas necesarias existen
        $hasAttendances = Schema::hasTable('attendances');
        $hasUsers = Schema::hasTable('users');

        Schema::create('justificaciones', function (Blueprint $table) use ($hasAttendances, $hasUsers) {
            $table->id();
            
            // Crear foreign key a attendances solo si existe
            if ($hasAttendances) {
                $table->foreignId('attendance_id')->constrained('attendances')->onDelete('cascade');
            } else {
                $table->unsignedBigInteger('attendance_id');
            }
            
            // Crear foreign key a users solo si existe
            if ($hasUsers) {
                $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            } else {
                $table->unsignedBigInteger('student_id');
            }
            
            $table->text('motivo');
            $table->string('evidencia')->nullable(); // Ruta del archivo
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente');
            $table->text('observacion_admin')->nullable();
            
            // Crear foreign key a users para revisado_por solo si existe
            if ($hasUsers) {
                $table->foreignId('revisado_por')->nullable()->constrained('users')->onDelete('set null');
            } else {
                $table->unsignedBigInteger('revisado_por')->nullable();
            }
            
            $table->timestamp('revisado_en')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index('attendance_id');
            $table->index('student_id');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('justificaciones');
    }
};

