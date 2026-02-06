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
        // Primero, agregar la columna como nullable temporalmente (sin foreign key aún)
        Schema::table('academic_loads', function (Blueprint $table) {
            $table->unsignedBigInteger('academic_period_id')->nullable()->after('id');
        });

        // Crear el periodo "Histórico/Inicial"
        $historicoPeriodId = DB::table('academic_periods')->insertGetId([
            'name' => 'Histórico/Inicial',
            'code' => 'HISTORICO',
            'start_date' => '2000-01-01',
            'end_date' => '2099-12-31',
            'is_active' => false,
            'is_open_for_grades' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Asignar el periodo histórico a todos los registros existentes
        DB::table('academic_loads')
            ->whereNull('academic_period_id')
            ->update(['academic_period_id' => $historicoPeriodId]);

        // Ahora hacer la columna obligatoria y agregar la foreign key
        Schema::table('academic_loads', function (Blueprint $table) {
            $table->unsignedBigInteger('academic_period_id')->nullable(false)->change();
            $table->foreign('academic_period_id')->references('id')->on('academic_periods')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('academic_loads', function (Blueprint $table) {
            $table->dropForeign(['academic_period_id']);
            $table->dropColumn('academic_period_id');
        });

        // Eliminar el periodo histórico si existe
        DB::table('academic_periods')
            ->where('code', 'HISTORICO')
            ->delete();
    }
};

