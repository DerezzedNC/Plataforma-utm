<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';
// Heroicons - Outline version
import { 
    CheckIcon,
    UserGroupIcon,
    CalendarIcon,
    ArrowLeftIcon,
    DocumentTextIcon,
    ChartBarIcon,
    XMarkIcon,
    ArrowDownTrayIcon
} from '@heroicons/vue/24/outline';

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Estados reactivos
const loading = ref(true);
const saving = ref(false);
const loadingHistory = ref(false);
const fechaSeleccionada = ref('');
const asistencias = ref({});
const showHistory = ref(false);
const studentHistory = ref([]);
const selectedStudent = ref(null);

// Obtener parámetros de la URL
const urlParams = new URLSearchParams(window.location.search);
const grado = urlParams.get('grado') || '';
const grupo = urlParams.get('grupo') || '';
const carrera = urlParams.get('carrera') || '';
const scheduleId = urlParams.get('schedule_id') || '';
const materia = urlParams.get('materia') || '';

// Datos de alumnos
const alumnos = ref([]);

// Información del grupo seleccionado
const infoGrupo = computed(() => {
    if (!carrera || !grupo) return 'Grupo no seleccionado';
    return `${grado ? grado + '° - ' : ''}Grupo ${grupo} - ${carrera}${materia ? ' - ' + materia : ''}`;
});

// Cargar alumnos del grupo
const loadStudents = async () => {
    if (!scheduleId || !carrera || !grupo) {
        alert('Faltan parámetros necesarios. Por favor, selecciona un grupo y materia desde el inicio.');
        return;
    }

    try {
        loading.value = true;
        const response = await axios.get('/teacher/students', {
            params: {
                carrera: carrera,
                grupo: grupo,
                schedule_id: scheduleId,
            }
        });
        
        // La respuesta siempre debe ser un array
        if (Array.isArray(response.data)) {
            // Forzar reactividad: crear un nuevo array para que Vue detecte los cambios
            alumnos.value = response.data.map(alumno => ({ ...alumno }));
            
            // Log temporal para depuración - mostrar porcentajes actualizados
            console.log('Alumnos recargados con porcentajes actualizados:', alumnos.value.map(a => ({
                nombre: a.nombre,
                porcentaje: a.asistencia,
                total_clases: a.total_clases,
                presentes: a.presentes
            })));
            
            if (alumnos.value.length === 0) {
                alert('No se encontraron estudiantes en este grupo. Verifica que los estudiantes estén asignados al grupo correcto.');
            }
        } else {
            // Si la respuesta no es un array, algo salió mal
            alumnos.value = [];
            console.error('Respuesta inesperada:', response.data);
            throw new Error('Formato de respuesta inesperado');
        }
        
        // Inicializar asistencias en false para todos los alumnos
        alumnos.value.forEach(alumno => {
            // Usar asignación directa para asegurar reactividad
            if (!(alumno.id in asistencias.value)) {
                asistencias.value[alumno.id] = false;
            }
        });
        
        // Cargar asistencias previas si existe fecha
        if (fechaSeleccionada.value) {
            await loadAttendances();
        }
    } catch (error) {
        console.error('Error cargando alumnos:', error);
        const errorMessage = error.response?.data?.error || error.response?.data?.message || error.message || 'Error desconocido';
        alert(`Error al cargar los alumnos del grupo:\n\n${errorMessage}\n\nPor favor verifica:\n- Que el grupo tenga alumnos asignados\n- Que la tabla de asistencias esté creada (ejecuta: php artisan migrate)`);
    } finally {
        loading.value = false;
    }
};

// Cargar asistencias de una fecha específica
const loadAttendances = async () => {
    if (!scheduleId || !fechaSeleccionada.value) return;

    try {
        const response = await axios.get('/teacher/attendances', {
            params: {
                schedule_id: scheduleId,
                fecha: fechaSeleccionada.value,
            }
        });
        
        // Actualizar asistencias con los datos cargados
        Object.keys(response.data).forEach(studentIdStr => {
            const studentId = parseInt(studentIdStr);
            if (!isNaN(studentId)) {
                asistencias.value[studentId] = response.data[studentIdStr].presente;
            }
        });
    } catch (error) {
        console.error('Error cargando asistencias:', error);
    }
};

// Watch para cargar asistencias cuando cambia la fecha
watch(fechaSeleccionada, async () => {
    if (fechaSeleccionada.value && alumnos.value.length > 0) {
        // Recargar alumnos para actualizar porcentajes con todas las asistencias
        await loadStudents();
    }
});


// Función para obtener color de asistencia histórica
const getColorAsistencia = (porcentaje) => {
    const num = parseInt(porcentaje);
    if (num >= 70) return darkMode.value ? 'bg-green-900 text-green-200' : 'bg-green-100 text-green-800';
    if (num >= 50) return darkMode.value ? 'bg-yellow-900 text-yellow-200' : 'bg-yellow-100 text-yellow-800';
    return darkMode.value ? 'bg-red-900 text-red-200' : 'bg-red-100 text-red-800';
};

// Función para guardar asistencias
const guardarAsistencias = async () => {
    if (!scheduleId || !fechaSeleccionada.value) {
        alert('Por favor selecciona una fecha');
        return;
    }

    if (alumnos.value.length === 0) {
        alert('No hay alumnos para guardar asistencias');
        return;
    }

    try {
        saving.value = true;
        
        // Preparar datos de asistencias - asegurar que presente sea booleano
        const asistenciasData = alumnos.value.map(alumno => ({
            student_id: parseInt(alumno.id),
            presente: Boolean(asistencias.value[alumno.id] || false),
            observaciones: null,
        }));

        const response = await axios.post('/teacher/attendances', {
            schedule_id: parseInt(scheduleId),
            fecha: fechaSeleccionada.value,
            asistencias: asistenciasData,
        });

        let message = `Asistencias guardadas exitosamente para el ${fechaSeleccionada.value}\n\nCreadas: ${response.data.created}\nActualizadas: ${response.data.updated}`;
        
        if (response.data.errors && response.data.errors.length > 0) {
            message += '\n\nErrores:\n' + response.data.errors.join('\n');
        }
        
        alert(message);
        
        // Guardar las asistencias actuales antes de recargar
        const asistenciasActuales = { ...asistencias.value };
        
        // Recargar la lista completa de alumnos para actualizar porcentajes
        await loadStudents();
        
        // Restaurar las asistencias marcadas para la fecha actual
        if (fechaSeleccionada.value) {
            await loadAttendances();
        } else {
            // Si no hay fecha, restaurar las asistencias que se guardaron
            Object.keys(asistenciasActuales).forEach(studentId => {
                if (asistenciasActuales[studentId] !== undefined) {
                    asistencias.value[studentId] = asistenciasActuales[studentId];
                }
            });
        }
    } catch (error) {
        console.error('Error guardando asistencias:', error);
        const errorMessage = error.response?.data?.error || error.response?.data?.message || error.message || 'Error desconocido';
        const errorDetails = error.response?.data?.errors ? '\n\nDetalles:\n' + JSON.stringify(error.response.data.errors, null, 2) : '';
        alert(`Error al guardar las asistencias:\n\n${errorMessage}${errorDetails}`);
    } finally {
        saving.value = false;
    }
};

// Función para marcar todos presente (si vinieron todos)
const marcarTodosPresentes = () => {
    alumnos.value.forEach(alumno => {
        asistencias.value[alumno.id] = true;
    });
};

// Contadores
const totalPresentes = computed(() => Object.values(asistencias.value).filter(Boolean).length);
const totalAusentes = computed(() => alumnos.value.length - totalPresentes.value);

// Cargar historial de asistencias de todos los estudiantes
const loadAllHistory = async () => {
    if (!scheduleId || !carrera || !grupo) return;

    try {
        loadingHistory.value = true;
        const response = await axios.get('/teacher/attendances/history/all', {
            params: {
                schedule_id: scheduleId,
                carrera: carrera,
                grupo: grupo,
            }
        });
        studentHistory.value = response.data;
    } catch (error) {
        console.error('Error cargando historial:', error);
    } finally {
        loadingHistory.value = false;
    }
};

// Cargar historial de un estudiante específico
const loadStudentHistory = async (studentId) => {
    if (!scheduleId || !studentId) return;

    try {
        loadingHistory.value = true;
        const response = await axios.get('/teacher/attendances/history/student', {
            params: {
                schedule_id: scheduleId,
                student_id: studentId,
            }
        });
        selectedStudent.value = alumnos.value.find(a => a.id === studentId);
        studentHistory.value = response.data;
        showHistory.value = true;
    } catch (error) {
        console.error('Error cargando historial del estudiante:', error);
        alert('Error al cargar el historial del estudiante');
    } finally {
        loadingHistory.value = false;
    }
};

// Cerrar historial
const closeHistory = () => {
    showHistory.value = false;
    selectedStudent.value = null;
    studentHistory.value = [];
};

// Inicializar fecha actual y cargar datos
onMounted(() => {
    const hoy = new Date();
    fechaSeleccionada.value = hoy.toISOString().split('T')[0];
    
    // Cargar alumnos si hay parámetros
    if (scheduleId && carrera && grupo) {
        loadStudents();
    } else {
        loading.value = false;
    }
});
</script>

<template>
    <Head :title="`Lista de Alumnos - ${infoGrupo} - Portal Docente UTM`" />

    <AuthenticatedLayout>
        <!-- Contenido Principal -->
        <div class="min-h-screen">
            <div class="py-8">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    
                    <!-- Header -->
                    <div class="text-center mb-8">
                        <h1 :class="['font-heading text-4xl font-bold mb-4', darkMode ? 'text-white' : 'text-gray-900']">
                            Control de Asistencias
                        </h1>
                        <p :class="['font-body text-xl mb-2', darkMode ? 'text-blue-400' : 'text-blue-600']">
                            {{ infoGrupo }}
                        </p>
                        <p :class="['font-body text-lg', darkMode ? 'text-gray-400' : 'text-gray-600']">
                            Gestiona la asistencia de tus alumnos
                        </p>
                    </div>

                    <!-- Panel de Control -->
                    <div :class="['rounded-2xl shadow-xl border p-6 mb-8', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
                            
                            <!-- Selector de Fecha -->
                            <div>
                                <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                    Fecha de Clase
                                </label>
                                <div class="relative">
                                    <input
                                        type="date"
                                        v-model="fechaSeleccionada"
                                        :class="['font-body w-full px-4 py-3 pl-12 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-blue-400' : 'bg-white text-gray-900']"
                                    />
                                    <CalendarIcon class="w-5 h-5 absolute left-4 top-1/2 transform -translate-y-1/2" :class="darkMode ? 'text-gray-400' : 'text-gray-500'" />
                                </div>
                            </div>

                            <!-- Estadísticas -->
                            <div class="text-center">
                                <div class="grid grid-cols-2 gap-4">
                                    <div :class="['font-body p-3 rounded-lg', darkMode ? 'bg-green-900/30' : 'bg-green-100']">
                                        <div :class="['font-heading text-2xl font-bold', darkMode ? 'text-green-400' : 'text-green-600']">
                                            {{ totalPresentes }}
                                        </div>
                                        <div :class="['font-body text-sm', darkMode ? 'text-green-300' : 'text-green-700']">
                                            Presentes
                                        </div>
                                    </div>
                                    <div :class="['font-body p-3 rounded-lg', darkMode ? 'bg-red-900/30' : 'bg-red-100']">
                                        <div :class="['font-heading text-2xl font-bold', darkMode ? 'text-red-400' : 'text-red-600']">
                                            {{ totalAusentes }}
                                        </div>
                                        <div :class="['font-body text-sm', darkMode ? 'text-red-300' : 'text-red-700']">
                                            Ausentes
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Acciones Rápidas -->
                            <div class="space-y-3">
                                <button
                                    @click="marcarTodosPresentes"
                                    :class="['font-body w-full py-2 px-4 rounded-lg font-medium transition-colors flex items-center justify-center gap-2', darkMode ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-green-500 hover:bg-green-600 text-white']"
                                >
                                    <CheckIcon class="w-5 h-5" />
                                    Marcar Todos Presentes (Si vinieron todos)
                                </button>
                                <p :class="['font-body text-xs text-center mt-2', darkMode ? 'text-gray-400' : 'text-gray-500']">
                                    O marca uno por uno si faltó alguien
                                </p>
                            </div>

                        </div>
                    </div>

                    <!-- Lista de Alumnos -->
                    <div :class="['rounded-2xl shadow-xl border overflow-hidden', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                        
                        <!-- Header de la tabla -->
                        <div :class="['px-6 py-4 border-b', darkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-200']">
                            <div class="grid grid-cols-12 gap-4 items-center">
                                <div class="col-span-1 text-center">
                                    <span :class="['font-body text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-300' : 'text-gray-500']">#</span>
                                </div>
                                <div class="col-span-3">
                                    <span :class="['font-body text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-300' : 'text-gray-500']">Matrícula</span>
                                </div>
                                <div class="col-span-5">
                                    <span :class="['font-body text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-300' : 'text-gray-500']">Nombre Completo</span>
                                </div>
                                <div class="col-span-2 text-center">
                                    <span :class="['font-body text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-300' : 'text-gray-500']">% Asistencia</span>
                                </div>
                                <div class="col-span-1 text-center">
                                    <span :class="['font-body text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-300' : 'text-gray-500']">Presente</span>
                                </div>
                            </div>
                        </div>

                        <!-- Loading State -->
                        <div v-if="loading" class="p-12 text-center">
                            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-blue-400' : 'border-blue-600'"></div>
                            <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando alumnos...</p>
                        </div>

                        <!-- Empty State -->
                        <div v-else-if="alumnos.length === 0" class="p-12 text-center">
                            <UserGroupIcon class="w-24 h-24 mx-auto mb-4 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" />
                            <h3 :class="['font-heading text-xl font-medium mb-2', darkMode ? 'text-gray-400' : 'text-gray-500']">
                                No hay alumnos en este grupo
                            </h3>
                        </div>

                        <!-- Cuerpo de la tabla -->
                        <div v-else class="divide-y divide-gray-200 dark:divide-gray-600">
                            <div
                                v-for="(alumno, index) in alumnos"
                                :key="alumno.id"
                                :class="[
                                    'px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors',
                                    asistencias[alumno.id] ? (darkMode ? 'bg-green-900/20' : 'bg-green-50') : ''
                                ]"
                            >
                                <div class="grid grid-cols-12 gap-4 items-center">
                                    
                                    <!-- Número -->
                                    <div class="col-span-1 text-center">
                                        <span :class="['font-body text-sm font-medium', darkMode ? 'text-gray-400' : 'text-gray-500']">
                                            {{ index + 1 }}
                                        </span>
                                    </div>

                                    <!-- Matrícula -->
                                    <div class="col-span-3">
                                        <span :class="['font-body font-mono text-sm font-medium', darkMode ? 'text-blue-400' : 'text-blue-600']">
                                            {{ alumno.matricula }}
                                        </span>
                                    </div>

                                    <!-- Nombre -->
                                    <div class="col-span-5">
                                        <span :class="['font-body font-medium', darkMode ? 'text-white' : 'text-gray-900']">
                                            {{ alumno.nombre }}
                                        </span>
                                    </div>

                                    <!-- Porcentaje de Asistencia -->
                                    <div class="col-span-2 text-center">
                                        <span :class="['font-body px-3 py-1 rounded-full text-xs font-medium', getColorAsistencia(alumno.asistencia)]">
                                            {{ alumno.asistencia }}
                                        </span>
                                    </div>

                                    <!-- Checkbox de Asistencia -->
                                    <div class="col-span-1 text-center">
                                        <div 
                                            :class="[
                                                'w-6 h-6 rounded border-2 flex items-center justify-center transition-all cursor-pointer mx-auto',
                                                asistencias[alumno.id]
                                                    ? 'bg-green-500 border-green-500'
                                                    : (darkMode ? 'border-gray-500 hover:border-green-400' : 'border-gray-300 hover:border-green-400')
                                            ]"
                                            @click="asistencias[alumno.id] = !asistencias[alumno.id]"
                                        >
                                            <CheckIcon
                                                v-if="asistencias[alumno.id]"
                                                class="w-4 h-4 text-white"
                                            />
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Botones de Acción -->
                    <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
                        <button
                            @click="guardarAsistencias"
                            :disabled="saving || loading || alumnos.length === 0"
                            :class="['font-body px-8 py-4 rounded-lg font-semibold text-lg transition-all duration-300 flex items-center gap-2', saving || loading || alumnos.length === 0 ? (darkMode ? 'bg-gray-600 text-gray-400 cursor-not-allowed' : 'bg-gray-300 text-gray-500 cursor-not-allowed') : (darkMode ? 'bg-blue-500 hover:bg-blue-600 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white')]"
                        >
                            <ArrowDownTrayIcon v-if="!saving" class="w-6 h-6" />
                            <div v-else class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-white"></div>
                            {{ saving ? 'Guardando...' : 'Guardar Asistencias' }}
                        </button>

                        <button
                            @click="loadAllHistory(); showHistory = true"
                            :disabled="loading || alumnos.length === 0"
                            :class="['font-body px-8 py-4 rounded-lg font-semibold text-lg transition-all duration-300 flex items-center gap-2', loading || alumnos.length === 0 ? (darkMode ? 'bg-gray-600 text-gray-400 cursor-not-allowed' : 'bg-gray-300 text-gray-500 cursor-not-allowed') : (darkMode ? 'bg-purple-600 hover:bg-purple-700 text-white' : 'bg-purple-500 hover:bg-purple-600 text-white')]"
                        >
                            <DocumentTextIcon class="w-6 h-6" />
                            Ver Historial
                        </button>

                        <Link :href="route('maestros.seleccionar-grupo')" :class="['font-body px-8 py-4 rounded-lg font-semibold text-lg transition-all duration-300 flex items-center gap-2', darkMode ? 'bg-gray-600 hover:bg-gray-700 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']">
                            <ArrowLeftIcon class="w-6 h-6" />
                            Cambiar Grupo
                        </Link>
                    </div>

                    <!-- Modal de Historial de Asistencias -->
                    <div 
                        v-if="showHistory" 
                        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                        @click.self="closeHistory"
                    >
                        <div :class="['rounded-2xl shadow-2xl max-w-6xl w-full max-h-[90vh] overflow-hidden flex flex-col', darkMode ? 'bg-gray-800' : 'bg-white']">
                            <!-- Header del Modal -->
                            <div class="p-6 border-b flex items-center justify-between" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                                <h2 :class="['font-heading text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    {{ selectedStudent ? `Historial de ${selectedStudent.nombre}` : 'Historial de Asistencias' }}
                                </h2>
                                <button 
                                    @click="closeHistory"
                                    :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-gray-400' : 'hover:bg-gray-100 text-gray-600']"
                                >
                                    <XMarkIcon class="w-6 h-6" />
                                </button>
                            </div>

                            <!-- Contenido del Modal -->
                            <div class="flex-1 overflow-y-auto p-6">
                                <!-- Loading State -->
                                <div v-if="loadingHistory" class="text-center py-12">
                                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-purple-400' : 'border-purple-600'"></div>
                                    <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando historial...</p>
                                </div>

                                <!-- Historial de todos los estudiantes -->
                                <div v-else-if="!selectedStudent && studentHistory.length > 0" class="space-y-6">
                                    <div 
                                        v-for="student in studentHistory" 
                                        :key="student.student_id"
                                        :class="['rounded-lg border p-6', darkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-200']"
                                    >
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <h3 :class="['font-heading text-lg font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                                    {{ student.nombre }}
                                                </h3>
                                                <p :class="['font-body text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                                    Matrícula: {{ student.matricula }}
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <div :class="['font-heading text-2xl font-bold', getColorAsistencia(student.porcentaje + '%').includes('green') ? (darkMode ? 'text-green-400' : 'text-green-600') : getColorAsistencia(student.porcentaje + '%').includes('yellow') ? (darkMode ? 'text-yellow-400' : 'text-yellow-600') : (darkMode ? 'text-red-400' : 'text-red-600')]">
                                                    {{ student.porcentaje }}%
                                                </div>
                                                <div :class="['font-body text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                                    {{ student.presentes }}/{{ student.total_clases }} clases
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <button
                                            @click="loadStudentHistory(student.student_id)"
                                            :class="['font-body w-full mt-2 px-4 py-2 rounded-lg font-medium transition-colors', darkMode ? 'bg-purple-600 hover:bg-purple-700 text-white' : 'bg-purple-500 hover:bg-purple-600 text-white']"
                                        >
                                            Ver Detalle
                                        </button>
                                    </div>
                                </div>

                                <!-- Historial detallado de un estudiante -->
                                <div v-else-if="selectedStudent && studentHistory.length > 0" class="space-y-4">
                                    <div class="grid grid-cols-3 gap-4 mb-6">
                                        <div :class="['font-body p-4 rounded-lg text-center', darkMode ? 'bg-gray-700' : 'bg-gray-100']">
                                            <div :class="['font-heading text-2xl font-bold', darkMode ? 'text-green-400' : 'text-green-600']">
                                                {{ studentHistory.filter(a => a.presente).length }}
                                            </div>
                                            <div :class="['font-body text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">Presentes</div>
                                        </div>
                                        <div :class="['font-body p-4 rounded-lg text-center', darkMode ? 'bg-gray-700' : 'bg-gray-100']">
                                            <div :class="['font-heading text-2xl font-bold', darkMode ? 'text-red-400' : 'text-red-600']">
                                                {{ studentHistory.filter(a => !a.presente).length }}
                                            </div>
                                            <div :class="['font-body text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">Ausentes</div>
                                        </div>
                                        <div :class="['font-body p-4 rounded-lg text-center', darkMode ? 'bg-gray-700' : 'bg-gray-100']">
                                            <div :class="['font-heading text-2xl font-bold', darkMode ? 'text-blue-400' : 'text-blue-600']">
                                                {{ studentHistory.length }}
                                            </div>
                                            <div :class="['font-body text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">Total</div>
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <div 
                                            v-for="asistencia in studentHistory" 
                                            :key="asistencia.fecha"
                                            :class="['flex items-center justify-between p-4 rounded-lg border', asistencia.presente ? (darkMode ? 'bg-green-900/20 border-green-700' : 'bg-green-50 border-green-200') : (darkMode ? 'bg-red-900/20 border-red-700' : 'bg-red-50 border-red-200')]"
                                        >
                                            <div class="flex items-center">
                                                <CheckIcon 
                                                    v-if="asistencia.presente"
                                                    class="w-5 h-5 mr-3" 
                                                    :class="darkMode ? 'text-green-400' : 'text-green-600'"
                                                />
                                                <XMarkIcon 
                                                    v-else
                                                    class="w-5 h-5 mr-3" 
                                                    :class="darkMode ? 'text-red-400' : 'text-red-600'"
                                                />
                                                <span :class="['font-body font-medium', darkMode ? 'text-white' : 'text-gray-900']">
                                                    {{ asistencia.fecha_formateada }}
                                                </span>
                                            </div>
                                            <span :class="['font-body px-3 py-1 rounded-full text-xs font-medium', asistencia.presente ? (darkMode ? 'bg-green-600 text-green-200' : 'bg-green-100 text-green-800') : (darkMode ? 'bg-red-600 text-red-200' : 'bg-red-100 text-red-800')]">
                                                {{ asistencia.presente ? 'Presente' : 'Ausente' }}
                                            </span>
                                        </div>
                                    </div>

                                    <button
                                        @click="loadAllHistory(); selectedStudent = null"
                                        :class="['font-body w-full mt-4 px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                                    >
                                        <ArrowLeftIcon class="w-5 h-5" />
                                        Volver a Lista General
                                    </button>
                                </div>

                                <!-- Empty State -->
                                <div v-else class="text-center py-12">
                                    <DocumentTextIcon class="w-24 h-24 mx-auto mb-4 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" />
                                    <p :class="['font-body text-lg', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                        No hay historial de asistencias registrado aún
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>