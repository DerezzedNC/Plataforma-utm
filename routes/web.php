<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\BuildingController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\AreaController;
use App\Http\Controllers\Admin\CareerController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Student\DocumentController as StudentDocumentController;
use App\Http\Controllers\Student\ProfileController as StudentProfileController;
use App\Http\Controllers\Student\ScheduleController as StudentScheduleController;
use App\Http\Controllers\Teacher\AttendanceController as TeacherAttendanceController;
use App\Http\Controllers\Teacher\CourseController as TeacherCourseController;
use App\Http\Controllers\Teacher\TutorController;
use App\Http\Controllers\Student\CourseController as StudentCourseController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    $user = auth()->user();
    
    // Redirigir automáticamente según el tipo de usuario
    if ($user->isAdmin()) {
        return redirect()->route('dashboard-admin');
    } elseif ($user->isMaestro()) {
        return redirect()->route('dashboard-maestro');
    }
    
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Dashboard para Administradores
Route::get('/dashboard-admin', function () {
    return Inertia::render('Admin/Dashboard');
})->middleware(['auth', 'verified', 'role:admin'])->name('dashboard-admin');

// Gestión de Estudiantes - Vista
Route::get('/admin/estudiantes', function () {
    return Inertia::render('Admin/Students/Index');
})->middleware(['auth', 'verified', 'role:admin'])->name('admin.estudiantes.index');

// Gestión de Maestros - Vista
Route::get('/admin/maestros', function () {
    return Inertia::render('Admin/Teachers/Index');
})->middleware(['auth', 'verified', 'role:admin'])->name('admin.maestros.index');

// Gestión de Grupos - Vista
Route::get('/admin/grupos', function () {
    return Inertia::render('Admin/Groups/Index');
})->middleware(['auth', 'verified', 'role:admin'])->name('admin.grupos.index');

// Gestión de Materias - Vista
Route::get('/admin/materias', function () {
    return Inertia::render('Admin/Subjects/Index');
})->middleware(['auth', 'verified', 'role:admin'])->name('admin.materias.index');

// Gestión de Edificios - Vista
Route::get('/admin/edificios', function () {
    return Inertia::render('Admin/Buildings/Index');
})->middleware(['auth', 'verified', 'role:admin'])->name('admin.edificios.index');

// Gestión de Áreas y Carreras - Vista
Route::get('/admin/carreras', function () {
    return Inertia::render('Admin/Careers/Index');
})->middleware(['auth', 'verified', 'role:admin'])->name('admin.carreras.index');

// Gestión de Horarios - Vista
Route::get('/admin/horarios', function () {
    return Inertia::render('Admin/Schedules/Index');
})->middleware(['auth', 'verified', 'role:admin'])->name('admin.horarios.index');

// Gestión de Documentos - Vista
Route::get('/admin/documentos', function () {
    return Inertia::render('Admin/Documents/Index');
})->middleware(['auth', 'verified', 'role:admin'])->name('admin.documentos.index');

// Gestión de Avisos - Vista
Route::get('/admin/avisos/crear', function () {
    return Inertia::render('Admin/Announcements/CreateAnnouncement');
})->middleware(['auth', 'verified', 'role:admin'])->name('admin.avisos.create');

// Dashboard para Maestros
Route::get('/dashboard-maestro', function () {
    return Inertia::render('DashboardMaestro');
})->middleware(['auth', 'verified', 'role:maestro'])->name('dashboard-maestro');

// Rutas para ALUMNOS
Route::middleware(['auth', 'verified', 'role:alumno'])->group(function () {
    Route::get('/cursos-extra', function () {
        return Inertia::render('CursosExtra');
    })->name('cursos-extra');

    // Cursos (Internos y Externos unificados)
    Route::get('/cursos', function () {
        return Inertia::render('Courses/Index');
    })->name('cursos.index');

    Route::get('/consultar-horario', function () {
        return Inertia::render('ConsultarHorario');
    })->name('consultar-horario');

    Route::get('/historial-academico', function () {
        return Inertia::render('HistorialAcademico');
    })->name('historial-academico');

    Route::get('/procesos-administrativos', function () {
        return Inertia::render('ProcesosAdministrativos');
    })->name('procesos-administrativos');
});

// Rutas API para documentos de estudiantes
Route::middleware(['auth', 'verified', 'role:alumno'])->prefix('student')->name('student.')->group(function () {
    Route::get('documents', [StudentDocumentController::class, 'index'])->name('documents.index');
    Route::post('documents', [StudentDocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}', [StudentDocumentController::class, 'show'])->name('documents.show');
    
    // Rutas para perfil del estudiante
    Route::get('profile', [StudentProfileController::class, 'show'])->name('profile.show');
    Route::put('profile/personal-info', [StudentProfileController::class, 'updatePersonalInfo'])->name('profile.update-personal');
    Route::post('profile/photo', [StudentProfileController::class, 'updateProfilePhoto'])->name('profile.update-photo');
    
    // Rutas para horario del estudiante
    Route::get('schedule', [StudentScheduleController::class, 'show'])->name('schedule.show');
    
    // Rutas para cursos del estudiante
    Route::get('courses', [StudentCourseController::class, 'index'])->name('courses.index');
    Route::get('courses/{course}', [StudentCourseController::class, 'show'])->name('courses.show');
});

// Rutas para MAESTROS
Route::middleware(['auth', 'verified', 'role:maestro'])->group(function () {
    // Seleccionar Grupo de Alumnos
    Route::get('/maestros/seleccionar-grupo', function () {
        return Inertia::render('Maestros/SeleccionarGrupo');
    })->name('maestros.seleccionar-grupo');

    // Lista de Alumnos con Control de Asistencias
    Route::get('/maestros/lista-alumnos', function () {
        return Inertia::render('Maestros/ListaAlumnos');
    })->name('maestros.lista-alumnos');

    // Gestión de Cursos (Internos y Externos unificados)
    Route::get('/maestros/cursos', function () {
        return Inertia::render('Maestros/Courses/Index');
    })->name('maestros.cursos.index');
});

// Rutas API para maestros
Route::middleware(['auth', 'verified', 'role:maestro'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('groups', [TeacherAttendanceController::class, 'getGroups'])->name('groups');
    Route::get('subjects', [TeacherAttendanceController::class, 'getSubjects'])->name('subjects');
    Route::get('students', [TeacherAttendanceController::class, 'getStudents'])->name('students');
    Route::post('attendances', [TeacherAttendanceController::class, 'store'])->name('attendances.store');
    Route::get('attendances', [TeacherAttendanceController::class, 'getAttendances'])->name('attendances.get');
    Route::get('attendances/history/student', [TeacherAttendanceController::class, 'getStudentHistory'])->name('attendances.history.student');
    Route::get('attendances/history/all', [TeacherAttendanceController::class, 'getAllStudentsHistory'])->name('attendances.history.all');
    
    // Rutas para gestión de cursos
    Route::get('courses', [TeacherCourseController::class, 'index'])->name('courses.index');
    Route::get('courses/careers', [TeacherCourseController::class, 'getCareers'])->name('courses.careers');
    Route::post('courses', [TeacherCourseController::class, 'store'])->name('courses.store');
    Route::get('courses/{course}', [TeacherCourseController::class, 'show'])->name('courses.show');
    Route::put('courses/{course}', [TeacherCourseController::class, 'update'])->name('courses.update');
    Route::delete('courses/{course}', [TeacherCourseController::class, 'destroy'])->name('courses.destroy');
    
    // Rutas para tutores
    Route::get('tutor/dashboard', [TutorController::class, 'dashboard'])->name('tutor.dashboard');
    Route::post('tutor/announcements/{announcement}/forward', [TutorController::class, 'forward'])->name('tutor.announcements.forward');
    Route::post('tutor/announcements/{announcement}/read', [TutorController::class, 'markAsRead'])->name('tutor.announcements.read');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Rutas para ADMINISTRADORES (APIs)
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Gestión de Estudiantes
    Route::get('students', [StudentController::class, 'index'])->name('students.index');
    Route::post('students', [StudentController::class, 'store'])->name('students.store');
    Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::delete('students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
    
    // Gestión de Maestros
    Route::get('teachers', [TeacherController::class, 'index'])->name('teachers.index');
    Route::post('teachers', [TeacherController::class, 'store'])->name('teachers.store');
    Route::get('teachers/{teacher}', [TeacherController::class, 'show'])->name('teachers.show');
    Route::put('teachers/{teacher}', [TeacherController::class, 'update'])->name('teachers.update');
    Route::delete('teachers/{teacher}', [TeacherController::class, 'destroy'])->name('teachers.destroy');
    
    // Gestión de Horarios
    Route::get('schedules', [ScheduleController::class, 'index'])->name('schedules.index');
    Route::post('schedules', [ScheduleController::class, 'store'])->name('schedules.store');
    Route::post('schedules/batch', [ScheduleController::class, 'storeBatch'])->name('schedules.batch');
    // Rutas específicas ANTES de las rutas con parámetros dinámicos
    Route::get('schedules/groups/list', [ScheduleController::class, 'getGroups'])->name('schedules.groups');
    Route::get('schedules/subjects/list', [ScheduleController::class, 'getSubjects'])->name('schedules.subjects');
    Route::post('schedules/check-conflicts', [ScheduleController::class, 'checkConflicts'])->name('schedules.check-conflicts');
    Route::get('schedules/check-assignment/{group}/{subject}', [ScheduleController::class, 'checkAssignment'])->name('schedules.check-assignment');
    // Rutas con parámetros dinámicos al final
    Route::get('schedules/{schedule}', [ScheduleController::class, 'show'])->name('schedules.show');
    Route::put('schedules/{schedule}', [ScheduleController::class, 'update'])->name('schedules.update');
    Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');
    
    // Gestión de Salones - Obtener salones disponibles
    Route::get('rooms/available', [RoomController::class, 'getAvailableRooms'])->name('rooms.available');
    
    // Gestión de Grupos
    // IMPORTANTE: Las rutas específicas deben ir ANTES de las rutas con parámetros
    Route::get('groups/available-options', [GroupController::class, 'getAvailableOptions'])->name('groups.available-options');
    Route::put('groups/change-student-group/{student}', [GroupController::class, 'changeStudentGroup'])->name('groups.change-student-group');
    
    Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
    Route::post('groups', [GroupController::class, 'store'])->name('groups.store');
    Route::get('groups/{group}/students', [GroupController::class, 'getStudents'])->name('groups.students');
    Route::get('groups/{group}/available-students', [GroupController::class, 'getAvailableStudents'])->name('groups.available-students');
    Route::post('groups/{group}/assign-students', [GroupController::class, 'assignStudents'])->name('groups.assign-students');
    Route::delete('groups/{group}/students/{student}', [GroupController::class, 'removeStudent'])->name('groups.remove-student');
    Route::get('groups/tutors/available', [GroupController::class, 'getAvailableTutors'])->name('groups.tutors.available');
    Route::put('groups/{group}/assign-tutor', [GroupController::class, 'assignTutor'])->name('groups.assign-tutor');
    Route::get('groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::put('groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    Route::delete('groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');
    
    // Gestión de Documentos
    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('documents/history', [DocumentController::class, 'history'])->name('documents.history');
    Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::put('documents/{document}/status', [DocumentController::class, 'updateStatus'])->name('documents.update-status');
    Route::post('documents/{document}/cancel', [DocumentController::class, 'cancel'])->name('documents.cancel');
    
    // Gestión de Materias
    Route::get('subjects', [SubjectController::class, 'index'])->name('subjects.index');
    Route::post('subjects', [SubjectController::class, 'store'])->name('subjects.store');
    Route::get('subjects/{subject}', [SubjectController::class, 'show'])->name('subjects.show');
    Route::put('subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
    Route::delete('subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');
    
    // Gestión de Edificios
    Route::get('buildings', [BuildingController::class, 'index'])->name('buildings.index');
    Route::post('buildings', [BuildingController::class, 'store'])->name('buildings.store');
    Route::get('buildings/{building}', [BuildingController::class, 'show'])->name('buildings.show');
    Route::put('buildings/{building}', [BuildingController::class, 'update'])->name('buildings.update');
    Route::delete('buildings/{building}', [BuildingController::class, 'destroy'])->name('buildings.destroy');
    
    // Gestión de Aulas/Laboratorios
    Route::get('rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::post('rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::get('rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');
    Route::put('rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
    Route::delete('rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');
    
    // Gestión de Áreas
    Route::get('areas', [AreaController::class, 'index'])->name('areas.index');
    Route::post('areas', [AreaController::class, 'store'])->name('areas.store');
    Route::get('areas/{area}', [AreaController::class, 'show'])->name('areas.show');
    Route::put('areas/{area}', [AreaController::class, 'update'])->name('areas.update');
    Route::delete('areas/{area}', [AreaController::class, 'destroy'])->name('areas.destroy');
    
    // Gestión de Carreras
    Route::get('careers', [CareerController::class, 'index'])->name('careers.index');
    Route::post('careers', [CareerController::class, 'store'])->name('careers.store');
    Route::get('careers/{career}', [CareerController::class, 'show'])->name('careers.show');
    Route::put('careers/{career}', [CareerController::class, 'update'])->name('careers.update');
    Route::delete('careers/{career}', [CareerController::class, 'destroy'])->name('careers.destroy');
    Route::post('careers/{career}/assign-subjects', [CareerController::class, 'assignSubjects'])->name('careers.assign-subjects');
    Route::delete('careers/{career}/subjects/{subject}', [CareerController::class, 'removeSubject'])->name('careers.remove-subject');
    
    // Gestión de Avisos
    Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::get('announcements/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');
    Route::get('announcements/teachers/list', [AnnouncementController::class, 'getTeachers'])->name('announcements.teachers');
    Route::get('announcements/tutors/list', [AnnouncementController::class, 'getTutors'])->name('announcements.tutors');
});

require __DIR__.'/auth.php';
