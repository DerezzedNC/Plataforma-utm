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
        // Solo ejecutar si la tabla group_posts existe
        if (!Schema::hasTable('group_posts')) {
            return;
        }

        // Verificar si las foreign keys ya existen
        $hasGroupForeignKey = $this->hasForeignKey('group_posts', 'group_posts_group_id_foreign');
        $hasUserForeignKey = $this->hasForeignKey('group_posts', 'group_posts_posted_by_foreign');
        $hasAnnouncementForeignKey = $this->hasForeignKey('group_posts', 'group_posts_announcement_id_foreign');

        // Agregar foreign key a groups si la tabla existe y la foreign key no existe
        if (Schema::hasTable('groups') && !$hasGroupForeignKey && Schema::hasColumn('group_posts', 'group_id')) {
            Schema::table('group_posts', function (Blueprint $table) {
                $table->foreign('group_id')
                    ->references('id')
                    ->on('groups')
                    ->onDelete('cascade');
            });
        }

        // Agregar foreign key a users si la tabla existe y la foreign key no existe
        if (Schema::hasTable('users') && !$hasUserForeignKey && Schema::hasColumn('group_posts', 'posted_by')) {
            Schema::table('group_posts', function (Blueprint $table) {
                $table->foreign('posted_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
            });
        }

        // Agregar foreign key a announcements si la tabla existe y la foreign key no existe
        if (Schema::hasTable('announcements') && !$hasAnnouncementForeignKey && Schema::hasColumn('group_posts', 'announcement_id')) {
            Schema::table('group_posts', function (Blueprint $table) {
                $table->foreign('announcement_id')
                    ->references('id')
                    ->on('announcements')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('group_posts')) {
            return;
        }

        Schema::table('group_posts', function (Blueprint $table) {
            // Intentar eliminar las foreign keys si existen
            try {
                $table->dropForeign(['group_id']);
            } catch (\Exception $e) {
                // Ignorar si no existe
            }
            
            try {
                $table->dropForeign(['posted_by']);
            } catch (\Exception $e) {
                // Ignorar si no existe
            }
            
            try {
                $table->dropForeign(['announcement_id']);
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
