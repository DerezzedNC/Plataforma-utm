<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { 
    ExclamationTriangleIcon,
    CheckCircleIcon,
    XCircleIcon,
    DocumentArrowUpIcon
} from '@heroicons/vue/24/outline';

const { darkMode } = useDarkMode();

// Estados
const loading = ref(true);
const unidad = ref(1);
const calificaciones = ref([]);
const asistencias = ref([]);
const showJustificationModal = ref(false);
const selectedAttendance = ref(null);
const justificationForm = ref({
    motivo: '',
    evidencia: null
});

// Cargar calificaciones
const loadGrades = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/student/grades');
        calificaciones.value = response.data.calificaciones || [];
        asistencias.value = response.data.asistencias || [];
    } catch (error) {
        console.error('Error cargando calificaciones:', error);
        alert('Error al cargar las calificaciones');
    } finally {
        loading.value = false;
    }
};

// Obtener calificaciones por unidad
const getGradesByUnidad = (unidadNum) => {
    return calificaciones.value.filter(c => c.unidad === unidadNum);
};

// Obtener asistencias por unidad
const getAttendancesByUnidad = (unidadNum) => {
    return asistencias.value.filter(a => a.unidad === unidadNum);
};

// Calcular porcentaje de asistencia por unidad
const calcularPorcentajeAsistencia = (unidadNum) => {
    const unidadAsistencias = getAttendancesByUnidad(unidadNum);
    if (unidadAsistencias.length === 0) return 100;

    const presentes = unidadAsistencias.filter(a => 
        ['presente', 'retardo', 'justificado'].includes(a.estado)
    ).length;

    return Math.round((presentes / unidadAsistencias.length) * 100);
};

// Obtener color de asistencia
const getAttendanceColor = (porcentaje) => {
    if (porcentaje >= 80) return 'bg-green-100 text-green-800';
    if (porcentaje >= 60) return 'bg-yellow-100 text-yellow-800';
    return 'bg-red-100 text-red-800';
};

// Obtener color de calificación
const getGradeColor = (promedio) => {
    if (!promedio) return darkMode.value ? 'text-gray-400' : 'text-gray-600';
    if (promedio >= 9.0) return 'text-green-600';
    if (promedio >= 8.0) return 'text-blue-600';
    if (promedio >= 7.0) return 'text-yellow-600';
    return 'text-red-600';
};

// Abrir modal de justificación
const openJustificationModal = (attendance) => {
    selectedAttendance.value = attendance;
    justificationForm.value = {
        motivo: '',
        evidencia: null
    };
    showJustificationModal.value = true;
};

// Cerrar modal
const closeJustificationModal = () => {
    showJustificationModal.value = false;
    selectedAttendance.value = null;
};

// Enviar justificación
const submitJustification = async () => {
    if (!justificationForm.value.motivo.trim()) {
        alert('Por favor, ingresa un motivo');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('attendance_id', selectedAttendance.value.id);
        formData.append('motivo', justificationForm.value.motivo);
        if (justificationForm.value.evidencia) {
            formData.append('evidencia', justificationForm.value.evidencia);
        }

        await axios.post('/student/justifications', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });

        alert('Justificación enviada exitosamente');
        closeJustificationModal();
        loadGrades(); // Recargar para actualizar estados
    } catch (error) {
        console.error('Error enviando justificación:', error);
        alert('Error al enviar la justificación');
    }
};

// Obtener estado de justificación
const getJustificationStatus = (attendance) => {
    if (!attendance.justificacion) return null;
    return attendance.justificacion.estado;
};

// Formatear fecha
const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

onMounted(() => {
    loadGrades();
});
</script>

<template>
    <Head title="Mis Calificaciones - UTM" />

    <AuthenticatedLayout>
        <div class="min-h-screen py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div :class="['mb-6 rounded-2xl p-6 border shadow-lg', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <h1 :class="['text-2xl md:text-3xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                        Mis Calificaciones
                    </h1>
                    <p :class="['text-lg', darkMode ? 'text-gray-300' : 'text-gray-600']">
                        Consulta tus calificaciones y asistencias por unidad
                    </p>
                </div>

                <!-- Loading -->
                <div v-if="loading" class="text-center py-12">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-green-400' : 'border-green-600'"></div>
                    <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando calificaciones...</p>
                </div>

                <!-- Contenido -->
                <div v-else class="space-y-6">
                    <!-- Unidades -->
                    <div v-for="unidadNum in [1, 2, 3]" :key="unidadNum" :class="['rounded-2xl border p-6', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                        <div class="flex items-center justify-between mb-4">
                            <h2 :class="['text-xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                {{ unidadNum }}{{ unidadNum === 1 ? 'ra' : unidadNum === 2 ? 'da' : 'ra' }} Unidad
                            </h2>
                            <span :class="['px-3 py-1 rounded-full text-sm font-medium', getAttendanceColor(calcularPorcentajeAsistencia(unidadNum))]">
                                Asistencia: {{ calcularPorcentajeAsistencia(unidadNum) }}%
                            </span>
                        </div>

                        <!-- Calificaciones de la unidad -->
                        <div v-if="getGradesByUnidad(unidadNum).length > 0" class="mb-6">
                            <h3 :class="['text-lg font-semibold mb-3', darkMode ? 'text-white' : 'text-gray-900']">Calificaciones</h3>
                            <div class="overflow-x-auto">
                                <table :class="['w-full rounded-lg border', darkMode ? 'bg-gray-700 border-gray-500' : 'bg-gray-50 border-gray-300']">
                                    <thead :class="[darkMode ? 'bg-gray-600' : 'bg-gray-100']">
                                        <tr>
                                            <th :class="['px-4 py-2 text-left text-sm font-bold', darkMode ? 'text-white' : 'text-gray-900']">Materia</th>
                                            <th :class="['px-4 py-2 text-center text-sm font-bold', darkMode ? 'text-white' : 'text-gray-900']">Tareas</th>
                                            <th :class="['px-4 py-2 text-center text-sm font-bold', darkMode ? 'text-white' : 'text-gray-900']">Examen</th>
                                            <th :class="['px-4 py-2 text-center text-sm font-bold', darkMode ? 'text-white' : 'text-gray-900']">Conducta</th>
                                            <th :class="['px-4 py-2 text-center text-sm font-bold', darkMode ? 'text-white' : 'text-gray-900']">Promedio</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr 
                                            v-for="(cal, index) in getGradesByUnidad(unidadNum)" 
                                            :key="cal.id"
                                            :class="['border-t', darkMode ? 'border-gray-600' : 'border-gray-200', index % 2 === 0 ? (darkMode ? 'bg-gray-800' : 'bg-white') : '']"
                                        >
                                            <td :class="['px-4 py-2', darkMode ? 'text-gray-200' : 'text-gray-900']">{{ cal.materia }}</td>
                                            <td :class="['px-4 py-2 text-center', darkMode ? 'text-gray-200' : 'text-gray-900']">
                                                {{ cal.score_tareas !== null && cal.score_tareas !== undefined ? parseFloat(cal.score_tareas).toFixed(1) : '--' }}
                                            </td>
                                            <td :class="['px-4 py-2 text-center', darkMode ? 'text-gray-200' : 'text-gray-900']">
                                                <span v-if="cal.derecho_examen">
                                                    {{ cal.score_examen !== null && cal.score_examen !== undefined ? parseFloat(cal.score_examen).toFixed(1) : '--' }}
                                                </span>
                                                <span v-else :class="['text-red-500 text-xs']">Sin derecho</span>
                                            </td>
                                            <td :class="['px-4 py-2 text-center', darkMode ? 'text-gray-200' : 'text-gray-900']">
                                                {{ cal.score_conducta !== null && cal.score_conducta !== undefined ? parseFloat(cal.score_conducta).toFixed(1) : '--' }}
                                            </td>
                                            <td :class="['px-4 py-2 text-center font-bold', getGradeColor(cal.promedio_unidad)]">
                                                {{ cal.promedio_unidad !== null && cal.promedio_unidad !== undefined ? parseFloat(cal.promedio_unidad).toFixed(2) : '--' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Asistencias de la unidad -->
                        <div>
                            <h3 :class="['text-lg font-semibold mb-3', darkMode ? 'text-white' : 'text-gray-900']">Asistencias</h3>
                            <div class="space-y-2">
                                <div 
                                    v-for="attendance in getAttendancesByUnidad(unidadNum)" 
                                    :key="attendance.id"
                                    :class="['flex items-center justify-between p-3 rounded-lg border', darkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-200']"
                                >
                                    <div class="flex items-center space-x-3">
                                        <span :class="[
                                            'px-2 py-1 rounded text-xs font-medium',
                                            attendance.estado === 'presente' ? 'bg-green-100 text-green-800' :
                                            attendance.estado === 'falta' ? 'bg-red-100 text-red-800' :
                                            attendance.estado === 'retardo' ? 'bg-yellow-100 text-yellow-800' :
                                            'bg-blue-100 text-blue-800'
                                        ]">
                                            {{ attendance.estado === 'presente' ? 'Presente' :
                                               attendance.estado === 'falta' ? 'Falta' :
                                               attendance.estado === 'retardo' ? 'Retardo' :
                                               'Justificado' }}
                                        </span>
                                        <span :class="['text-sm', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                            {{ formatDate(attendance.fecha) }} - {{ attendance.materia }}
                                        </span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span v-if="getJustificationStatus(attendance) === 'aprobada'" class="text-green-500">
                                            <CheckCircleIcon class="w-5 h-5" />
                                        </span>
                                        <span v-else-if="getJustificationStatus(attendance) === 'rechazada'" class="text-red-500">
                                            <XCircleIcon class="w-5 h-5" />
                                        </span>
                                        <span v-else-if="getJustificationStatus(attendance) === 'pendiente'" class="text-yellow-500">
                                            <ExclamationTriangleIcon class="w-5 h-5" />
                                        </span>
                                        <button
                                            v-if="attendance.estado === 'falta' && !getJustificationStatus(attendance)"
                                            @click="openJustificationModal(attendance)"
                                            :class="['px-3 py-1 rounded-lg text-xs font-medium flex items-center space-x-1', darkMode ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                                        >
                                            <DocumentArrowUpIcon class="w-4 h-4" />
                                            <span>Justificar</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal de Justificación -->
                <div 
                    v-if="showJustificationModal"
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="closeJustificationModal"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-2xl w-full', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    Justificar Falta
                                </h2>
                                <button 
                                    @click="closeJustificationModal"
                                    :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-gray-400' : 'hover:bg-gray-100 text-gray-600']"
                                >
                                    <XCircleIcon class="w-6 h-6" />
                                </button>
                            </div>
                        </div>

                        <div class="p-6 space-y-4">
                            <div>
                                <label :class="['block text-sm font-medium mb-2', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                    Fecha
                                </label>
                                <p :class="['text-base', darkMode ? 'text-gray-200' : 'text-gray-900']">
                                    {{ selectedAttendance ? formatDate(selectedAttendance.fecha) : '' }}
                                </p>
                            </div>

                            <div>
                                <label :class="['block text-sm font-medium mb-2', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                    Motivo <span class="text-red-500">*</span>
                                </label>
                                <textarea
                                    v-model="justificationForm.motivo"
                                    rows="4"
                                    :class="['w-full px-4 py-2 rounded-lg border', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    placeholder="Describe el motivo de tu falta..."
                                ></textarea>
                            </div>

                            <div>
                                <label :class="['block text-sm font-medium mb-2', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                    Evidencia (Opcional)
                                </label>
                                <input
                                    type="file"
                                    @change="justificationForm.evidencia = $event.target.files[0]"
                                    accept="image/*,.pdf"
                                    :class="['w-full px-4 py-2 rounded-lg border', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                />
                            </div>
                        </div>

                        <div class="p-6 border-t flex justify-end space-x-3" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <button
                                @click="closeJustificationModal"
                                :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                            >
                                Cancelar
                            </button>
                            <button
                                @click="submitJustification"
                                :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                            >
                                Enviar Justificación
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

