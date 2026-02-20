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
        // Solo ejecutar si ambas tablas existen
        if (!Schema::hasTable('student_details') || !Schema::hasTable('groups')) {
            return;
        }

        // Verificar si la columna group_id existe
        if (!Schema::hasColumn('student_details', 'group_id')) {
            return;
        }

        // Verificar si la foreign key ya existe
        $hasForeignKey = $this->hasForeignKey('student_details', 'student_details_group_id_foreign');

        if (!$hasForeignKey) {
            Schema::table('student_details', function (Blueprint $table) {
                $table->foreign('group_id')
                    ->references('id')
                    ->on('groups')
                    ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('student_details')) {
            return;
        }

        Schema::table('student_details', function (Blueprint $table) {
            try {
                $table->dropForeign(['group_id']);
            } catch (\Exception $e) {
                // Ignorar si no existe
            }
        });
    }

    /**
     * Verificar si una foreign key existe (PostgreSQL)
     */
    private function hasForeignKey(string $table, string $constraintName): bool
    {
        try {
            $result = DB::selectOne(
                "SELECT constraint_name 
                 FROM information_schema.table_constraints 
                 WHERE table_name = ? AND constraint_name = ? AND constraint_type = 'FOREIGN KEY'",
                [$table, $constraintName]
            );
            return $result !== null;
        } catch (\Exception $e) {
            return false;
        }
    }
};
