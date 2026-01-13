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
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('career_id')->nullable()->after('grado')->constrained()->onDelete('cascade');
            // Mantenemos el campo carrera por ahora para migración de datos, pero lo haremos nullable
            // $table->dropColumn('carrera'); // Se puede eliminar después de migrar datos
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropForeign(['career_id']);
            $table->dropColumn('career_id');
        });
    }
};
