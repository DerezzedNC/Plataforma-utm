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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->string('tipo_documento'); // 'Constancia', 'Boleta', 'Kardex', etc.
            $table->text('motivo')->nullable();
            $table->string('estado')->default('pendiente_revisar');
            $table->timestamp('solicitado_en')->nullable();
            $table->timestamp('listo_en')->nullable();
            $table->timestamp('entregado_en')->nullable();
            $table->foreignId('administrador_id')->nullable()->constrained('users');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
