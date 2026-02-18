<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Group;
use App\Models\Subject;
use App\Models\CourseUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignOldAttendancesToUnit1 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendances:assign-to-unit1';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Asigna los registros de asistencia antiguos (sin course_unit_id) a la Unidad 1 de cada materia';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando asignación de asistencias antiguas a la Unidad 1...');
        
        // Obtener todos los IDs válidos de course_units
        $validCourseUnitIds = CourseUnit::pluck('id')->toArray();
        
        // Obtener todos los registros de asistencia sin course_unit_id o con course_unit_id inválido
        $oldAttendances = Attendance::where(function($query) use ($validCourseUnitIds) {
            $query->whereNull('course_unit_id')
                  ->orWhere('course_unit_id', 0)
                  ->orWhereNotIn('course_unit_id', $validCourseUnitIds);
        })->get();
        
        if ($oldAttendances->isEmpty()) {
            $this->info('No se encontraron registros de asistencia sin course_unit_id válido.');
            $this->info('Verificando estadísticas...');
            
            $total = Attendance::count();
            $withNull = Attendance::whereNull('course_unit_id')->count();
            $withZero = Attendance::where('course_unit_id', 0)->count();
            $withInvalid = !empty($validCourseUnitIds) 
                ? Attendance::whereNotNull('course_unit_id')
                    ->where('course_unit_id', '!=', 0)
                    ->whereNotIn('course_unit_id', $validCourseUnitIds)
                    ->count()
                : 0;
            
            $this->info("Total de asistencias: {$total}");
            $this->info("Con course_unit_id NULL: {$withNull}");
            $this->info("Con course_unit_id = 0: {$withZero}");
            $this->info("Con course_unit_id inválido: {$withInvalid}");
            
            return 0;
        }
        
        $this->info("Se encontraron {$oldAttendances->count()} registros de asistencia sin course_unit_id válido.");
        
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        
        // Agrupar por schedule_id para optimizar las consultas
        $attendancesBySchedule = $oldAttendances->groupBy('schedule_id');
        
        $bar = $this->output->createProgressBar($attendancesBySchedule->count());
        $bar->start();
        
        foreach ($attendancesBySchedule as $scheduleId => $attendances) {
            try {
                $schedule = Schedule::with('period')->find($scheduleId);
                
                if (!$schedule) {
                    $this->newLine();
                    $this->warn("Schedule ID {$scheduleId} no encontrado. Omitiendo {$attendances->count()} registros.");
                    $skipped += $attendances->count();
                    $bar->advance();
                    continue;
                }
                
                if (!$schedule->period) {
                    $this->newLine();
                    $this->warn("Schedule ID {$scheduleId} no tiene periodo asociado. Omitiendo {$attendances->count()} registros.");
                    $skipped += $attendances->count();
                    $bar->advance();
                    continue;
                }
                
                $activePeriod = $schedule->period;
                
                // Buscar el grupo usando carrera y grupo del schedule
                $group = Group::where('carrera', $schedule->carrera)
                    ->where('grupo', $schedule->grupo)
                    ->where('academic_period_id', $activePeriod->id)
                    ->first();
                
                if (!$group) {
                    $this->newLine();
                    $this->warn("No se encontró grupo para schedule_id {$scheduleId} (carrera: {$schedule->carrera}, grupo: {$schedule->grupo}, periodo: {$activePeriod->id}). Omitiendo {$attendances->count()} registros.");
                    $skipped += $attendances->count();
                    $bar->advance();
                    continue;
                }
                
                // Buscar la materia usando el nombre del schedule
                $subject = Subject::where('nombre', $schedule->materia)->first();
                
                if (!$subject) {
                    $this->newLine();
                    $this->warn("No se encontró materia '{$schedule->materia}' para schedule_id {$scheduleId}. Omitiendo {$attendances->count()} registros.");
                    $skipped += $attendances->count();
                    $bar->advance();
                    continue;
                }
                
                // Buscar el academic_load
                $academicLoad = \App\Models\AcademicLoad::where('academic_period_id', $activePeriod->id)
                    ->where('group_id', $group->id)
                    ->where('subject_id', $subject->id)
                    ->first();
                
                if (!$academicLoad) {
                    $this->newLine();
                    $this->warn("No se encontró academic_load para schedule_id {$scheduleId} (group_id: {$group->id}, subject_id: {$subject->id}, period_id: {$activePeriod->id}). Omitiendo {$attendances->count()} registros.");
                    $skipped += $attendances->count();
                    $bar->advance();
                    continue;
                }
                
                // Obtener la primera unidad (Unidad 1) de este academic_load
                $firstCourseUnit = CourseUnit::where('academic_load_id', $academicLoad->id)
                    ->orderBy('id')
                    ->first();
                
                if (!$firstCourseUnit) {
                    $this->newLine();
                    $this->warn("No se encontró unidad de curso para academic_load_id {$academicLoad->id}. Omitiendo {$attendances->count()} registros.");
                    $skipped += $attendances->count();
                    $bar->advance();
                    continue;
                }
                
                // Actualizar todos los registros de asistencia de este schedule que no tengan course_unit_id válido
                $count = Attendance::whereIn('id', $attendances->pluck('id'))
                    ->where(function($query) use ($validCourseUnitIds) {
                        $query->whereNull('course_unit_id')
                              ->orWhere('course_unit_id', 0)
                              ->orWhereNotIn('course_unit_id', $validCourseUnitIds);
                    })
                    ->update(['course_unit_id' => $firstCourseUnit->id]);
                
                $updated += $count;
                
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Error procesando schedule_id {$scheduleId}: " . $e->getMessage());
                Log::error('Error asignando asistencias a Unidad 1', [
                    'schedule_id' => $scheduleId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $errors += $attendances->count();
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        
        $this->newLine();
        $this->info("Proceso completado:");
        $this->info("  - Actualizados: {$updated}");
        $this->info("  - Omitidos (sin datos completos): {$skipped}");
        $this->info("  - Errores: {$errors}");
        
        return 0;
    }
}
