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
        // Verificar que la tabla schedules existe antes de intentar modificarla
        if (!Schema::hasTable('schedules')) {
            return;
        }

        // Verificar si la columna ya existe para evitar errores en re-ejecuciones
        if (Schema::hasColumn('schedules', 'academic_period_id')) {
            return;
        }

        // Primero, agregar la columna como nullable temporalmente
        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('academic_period_id')->nullable()->after('id');
        });

        // Obtener el periodo histórico (debe existir de la migración anterior)
        $historicoPeriodId = DB::table('academic_periods')
            ->where('code', 'HISTORICO')
            ->value('id');

        if ($historicoPeriodId) {
            // Asignar el periodo histórico a todos los registros existentes
            DB::table('schedules')
                ->whereNull('academic_period_id')
                ->update(['academic_period_id' => $historicoPeriodId]);
        }

        // Ahora hacer la columna obligatoria y agregar la foreign key
        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('academic_period_id')->nullable(false)->change();
            $table->foreign('academic_period_id')->references('id')->on('academic_periods')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['academic_period_id']);
            $table->dropColumn('academic_period_id');
        });
    }
};

