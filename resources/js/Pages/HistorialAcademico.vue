<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { 
    PresentationChartLineIcon,
    AcademicCapIcon
} from '@heroicons/vue/24/outline';

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Obtener datos del usuario autenticado
const page = usePage();
const user = computed(() => page.props.auth.user);

// Estados reactivos
const loading = ref(true);
const cuatrimestreActivo = ref('Todos');
const unidadActiva = ref('Todos'); // U1, U2, U3, U4, etc. o Todos
const unidadesDisponibles = ref(['Todos']); // Lista dinámica de unidades

// Información del estudiante (cargada desde la API)
const estudianteInfo = ref({
    nombre: user.value?.name || '',
    matricula: '',
    carrera: '',
    cuatrimestreActual: '',
    promedioGeneral: 0.00,
    avanceCarrera: 0
});

// Datos del historial académico (cargados desde la API)
const historialAcademico = ref({});
const currentCuatrimestre = ref({
    periodo: '',
    materias: []
});

// Función para obtener cuatrimestre actual
const getCurrentCuatrimestre = () => {
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth() + 1;
    let cuatrimestre = 1;
    if (month >= 5 && month <= 8) {
        cuatrimestre = 2;
    } else if (month >= 9) {
        cuatrimestre = 3;
    }
    return `${year}-${cuatrimestre}`;
};

// Función para formatear periodo de cuatrimestre
const formatCuatrimestrePeriodo = (cuatrimestre) => {
    const [year, cuat] = cuatrimestre.split('-');
    const cuatNum = parseInt(cuat);
    
    let mesInicio = '';
    let mesFin = '';
    
    if (cuatNum === 1) {
        mesInicio = 'Enero';
        mesFin = 'Abril';
    } else if (cuatNum === 2) {
        mesInicio = 'Mayo';
        mesFin = 'Agosto';
    } else if (cuatNum === 3) {
        mesInicio = 'Septiembre';
        mesFin = 'Diciembre';
    }
    
    return `${cuatNum}${cuatNum === 1 ? 'er' : cuatNum === 2 ? 'do' : cuatNum === 3 ? 'er' : 'to'} Cuatrimestre - ${mesInicio} ${year} - ${mesFin} ${year}`;
};

// Cargar datos del historial académico
const loadAcademicHistory = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/student/profile/academic-history');
        const data = response.data;
        
        // Actualizar información del estudiante
        estudianteInfo.value = {
            nombre: data.student_info.nombre,
            matricula: data.student_info.matricula,
            carrera: data.student_info.carrera,
            cuatrimestreActual: data.student_info.cuatrimestreActual,
            promedioGeneral: parseFloat(data.student_info.promedioGeneral || 0).toFixed(2),
            avanceCarrera: data.student_info.avanceCarrera || 0
        };
        
        // Procesar cuatrimestre actual
        if (data.current_cuatrimestre && data.current_cuatrimestre.materias.length > 0) {
            const periodo = data.current_cuatrimestre.periodo;
            const periodoFormateado = formatCuatrimestrePeriodo(periodo);
            
            // Obtener todas las unidades únicas de todas las materias
            const unidadesSet = new Set(['Todos']);
            data.current_cuatrimestre.materias.forEach(m => {
                if (m.unidades) {
                    Object.keys(m.unidades).forEach(key => {
                        // Agregar tanto U1, U2, U3 como nombres de unidades
                        if (key.startsWith('U') && /^U\d+$/.test(key)) {
                            unidadesSet.add(key);
                        }
                    });
                }
            });
            unidadesDisponibles.value = Array.from(unidadesSet).sort((a, b) => {
                if (a === 'Todos') return -1;
                if (b === 'Todos') return 1;
                const numA = parseInt(a.replace('U', ''));
                const numB = parseInt(b.replace('U', ''));
                return numA - numB;
            });
            
            // Calcular promedio del cuatrimestre actual
            const materiasConCalificacion = data.current_cuatrimestre.materias.filter(m => m.calificacion !== null);
            let promedio = 0;
            if (materiasConCalificacion.length > 0) {
                const suma = materiasConCalificacion.reduce((acc, m) => acc + parseFloat(m.calificacion || 0), 0);
                promedio = suma / materiasConCalificacion.length;
            }
            
            historialAcademico.value[periodoFormateado] = {
                periodo: periodo,
                promedio: promedio > 0 ? promedio.toFixed(2) : null,
                materias: data.current_cuatrimestre.materias.map(m => ({
                    materia: m.materia,
                    codigo: m.codigo,
                    teacher_name: m.teacher_name,
                    calificacion: m.calificacion ? parseFloat(m.calificacion).toFixed(2) : null,
                    asistencia: m.asistencia !== undefined && m.asistencia !== null ? parseFloat(m.asistencia).toFixed(1) : null,
                    unidades: m.unidades || {} // Calificaciones por unidad (U1, U2, U3, U4, etc.)
                }))
            };
            
            currentCuatrimestre.value = {
                periodo: periodo,
                materias: data.current_cuatrimestre.materias
            };
        }
        
        // Procesar historial histórico (cuando exista)
        if (data.historial && data.historial.length > 0) {
            data.historial.forEach(item => {
                const periodoFormateado = formatCuatrimestrePeriodo(item.periodo);
                historialAcademico.value[periodoFormateado] = {
                    periodo: item.periodo,
                    promedio: item.promedio ? parseFloat(item.promedio).toFixed(2) : null,
                    materias: item.materias.map(m => ({
                        materia: m.materia,
                        codigo: m.codigo,
                        teacher_name: m.teacher_name,
                        calificacion: m.calificacion ? parseFloat(m.calificacion).toFixed(2) : null,
                        asistencia: m.asistencia !== undefined && m.asistencia !== null ? parseFloat(m.asistencia).toFixed(1) : null,
                        unidades: m.unidades || {} // Calificaciones por unidad
                    }))
                };
            });
        }
        
    } catch (error) {
        console.error('Error cargando historial académico:', error);
        // Mantener datos básicos del usuario
        estudianteInfo.value.nombre = user.value?.name || '';
    } finally {
        loading.value = false;
    }
};

// Función para obtener color de calificación
const getCalificacionColor = (calificacion) => {
    if (!calificacion) return darkMode.value ? 'text-gray-400' : 'text-gray-500';
    const cal = parseFloat(calificacion);
    if (cal >= 9.0) return darkMode.value ? 'text-green-400' : 'text-green-600';
    if (cal >= 8.0) return darkMode.value ? 'text-blue-400' : 'text-blue-600';
    if (cal >= 7.0) return darkMode.value ? 'text-yellow-400' : 'text-yellow-600';
    return darkMode.value ? 'text-red-400' : 'text-red-600';
};

// Función para descargar historial (simulada)
const descargarHistorial = () => {
    alert('Funcionalidad de descarga será implementada próximamente');
};

// Filtrar materias por unidad activa
const materiasFiltradas = computed(() => {
    // Si no hay historial académico, retornar objeto vacío
    if (!historialAcademico.value || Object.keys(historialAcademico.value).length === 0) {
        return {};
    }
    
    if (unidadActiva.value === 'Todos') {
        // Mostrar promedio final
        return Object.keys(historialAcademico.value).reduce((acc, periodo) => {
            acc[periodo] = {
                ...historialAcademico.value[periodo],
                materias: (historialAcademico.value[periodo].materias || []).map(m => ({
                    ...m,
                    calificacion_mostrar: m.calificacion, // Promedio final
                    asistencia_mostrar: m.asistencia // Promedio de asistencia
                }))
            };
            return acc;
        }, {});
    } else {
        // Mostrar calificación de la unidad seleccionada (U1, U2, U3, U4, etc.)
        const unidadKey = unidadActiva.value; // Ej: "U1", "U2", "U3", "U4"
        return Object.keys(historialAcademico.value).reduce((acc, periodo) => {
            acc[periodo] = {
                ...historialAcademico.value[periodo],
                materias: (historialAcademico.value[periodo].materias || []).map(m => {
                    // Buscar la unidad por clave (U1, U2, U3, etc.)
                    const unidad = m.unidades && m.unidades[unidadKey];
                    return {
                        ...m,
                        calificacion_mostrar: unidad && unidad.calificacion !== null && unidad.calificacion !== undefined 
                            ? parseFloat(unidad.calificacion).toFixed(2) 
                            : null,
                        asistencia_mostrar: unidad && unidad.asistencia !== undefined && unidad.asistencia !== null
                            ? parseFloat(unidad.asistencia).toFixed(1) 
                            : null
                    };
                })
            };
            return acc;
        }, {});
    }
});

// Obtener lista de cuatrimestres disponibles
const cuatrimestres = computed(() => {
    const lista = ['Todos'];
    Object.keys(historialAcademico.value).forEach(key => {
        const match = key.match(/(\d+)(er|do|to)\s+Cuatrimestre/);
        if (match) {
            const num = match[1];
            const texto = `${num}${match[2]} Cuatrimestre`;
            if (!lista.includes(texto)) {
                lista.push(texto);
            }
        }
    });
    return lista;
});

// Función para filtrar historial
const historialFiltrado = computed(() => {
    // Si no hay historial académico, retornar objeto vacío
    if (!historialAcademico.value || Object.keys(historialAcademico.value).length === 0) {
        return {};
    }
    
    // Si materiasFiltradas no está disponible, retornar objeto vacío
    if (!materiasFiltradas.value || typeof materiasFiltradas.value !== 'object') {
        return {};
    }
    
    let filtered = {};
    
    if (cuatrimestreActivo.value === 'Todos') {
        filtered = materiasFiltradas.value || {};
    } else {
        // Filtrar por cuatrimestre y aplicar filtro de unidad
        Object.keys(materiasFiltradas.value).forEach(key => {
            if (key.includes(cuatrimestreActivo.value)) {
                filtered[key] = materiasFiltradas.value[key];
            }
        });
    }
    
    return filtered;
});

// Cargar datos al montar
onMounted(() => {
    loadAcademicHistory();
});
</script>

<template>
    <Head title="Historial Académico - UTM" />

    <AuthenticatedLayout>
        <!-- Contenido Principal -->
        <div class="min-h-screen">
            <div class="py-8">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <!-- Header -->
                    <div class="text-center mb-8">
                        <h1 :class="['text-4xl font-bold mb-4', darkMode ? 'text-white' : 'text-gray-900']">
                            Historial Académico
                        </h1>
                        <p :class="['text-xl', darkMode ? 'text-gray-400' : 'text-gray-600']">
                            Consulta tu expediente académico y calificaciones
                        </p>
                    </div>

                    <!-- Loading State -->
                    <div v-if="loading" class="flex justify-center items-center py-12">
                        <div class="text-center">
                            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-green-400' : 'border-green-600'"></div>
                            <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando historial académico...</p>
                        </div>
                    </div>

                    <!-- Contenido cuando no está cargando -->
                    <div v-else>
                        <!-- Información del Estudiante -->
                        <div :class="['rounded-2xl shadow-xl border p-6 md:p-8 mb-8', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                            <!-- Título -->
                            <div class="flex items-center mb-6">
                                <svg :class="['w-6 h-6 mr-3', darkMode ? 'text-green-400' : 'text-green-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    Información del Estudiante
                                </h2>
                            </div>

                            <!-- Datos del estudiante -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
                                <div>
                                    <label :class="['block text-sm font-medium mb-1', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                        Nombre
                                    </label>
                                    <p :class="['text-base md:text-lg font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                        {{ estudianteInfo.nombre || 'No disponible' }}
                                    </p>
                                </div>
                                <div>
                                    <label :class="['block text-sm font-medium mb-1', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                        Matrícula
                                    </label>
                                    <p :class="['text-base md:text-lg font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                        {{ estudianteInfo.matricula || 'No disponible' }}
                                    </p>
                                </div>
                                <div>
                                    <label :class="['block text-sm font-medium mb-1', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                        Carrera
                                    </label>
                                    <p :class="['text-base md:text-lg font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                        {{ estudianteInfo.carrera || 'No asignada' }}
                                    </p>
                                </div>
                                <div>
                                    <label :class="['block text-sm font-medium mb-1', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                        Cuatrimestre Actual
                                    </label>
                                    <p :class="['text-base md:text-lg font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                        {{ estudianteInfo.cuatrimestreActual || 'No asignado' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Estadísticas académicas -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 md:gap-6">
                                <!-- Promedio General -->
                                <div :class="['text-center p-4 md:p-6 rounded-xl', darkMode ? 'bg-gray-700' : 'bg-gray-50']">
                                    <div class="flex justify-center mb-2">
                                        <PresentationChartLineIcon :class="['w-8 h-8 md:w-10 md:h-10', darkMode ? 'text-green-400' : 'text-green-600']" />
                                    </div>
                                    <div :class="['text-2xl md:text-3xl font-bold mb-1', darkMode ? 'text-green-400' : 'text-green-600']">
                                        {{ estudianteInfo.promedioGeneral }}
                                    </div>
                                    <p :class="['text-sm', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                        Promedio General
                                    </p>
                                </div>

                                <!-- Avance de Carrera -->
                                <div :class="['text-center p-4 md:p-6 rounded-xl', darkMode ? 'bg-gray-700' : 'bg-gray-50']">
                                    <div class="flex justify-center mb-2">
                                        <AcademicCapIcon :class="['w-8 h-8 md:w-10 md:h-10', darkMode ? 'text-purple-400' : 'text-purple-600']" />
                                    </div>
                                    <div :class="['text-2xl md:text-3xl font-bold mb-1', darkMode ? 'text-purple-400' : 'text-purple-600']">
                                        {{ estudianteInfo.avanceCarrera }}%
                                    </div>
                                    <p :class="['text-sm', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                        Avance de Carrera
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Historial por Cuatrimestres -->
                        <div :class="['rounded-2xl shadow-xl border p-6 md:p-8', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                            <!-- Header del historial -->
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 md:mb-8">
                                <div class="mb-4 md:mb-0">
                                    <h3 :class="['text-xl md:text-2xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Historial por Cuatrimestres
                                    </h3>
                                    <p :class="['text-base md:text-lg', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                        Calificaciones detalladas por periodo académico
                                    </p>
                                </div>
                                <button 
                                    @click="descargarHistorial"
                                    :class="['mt-4 md:mt-0 px-4 md:px-6 py-2 md:py-3 rounded-lg font-medium transition-colors flex items-center text-sm md:text-base', darkMode ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                                >
                                    <svg class="w-4 h-4 md:w-5 md:h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    Descargar Historial
                                </button>
                            </div>

                            <!-- Mensaje cuando no hay materias -->
                            <div v-if="Object.keys(historialAcademico).length === 0" :class="['text-center py-12', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                <p class="text-lg mb-2">No hay materias registradas aún</p>
                                <p class="text-sm">Las materias aparecerán aquí cuando estén asignadas a tu grupo</p>
                            </div>

                            <!-- Filtros de cuatrimestre y stepper vertical de unidades -->
                            <div v-if="Object.keys(historialAcademico).length > 0" class="flex flex-col md:flex-row gap-6 mb-6 md:mb-8">
                                <!-- Filtros de cuatrimestre -->
                                <div class="flex gap-2 flex-wrap">
                                    <button
                                        v-for="cuatrimestre in cuatrimestres"
                                        :key="cuatrimestre"
                                        @click="cuatrimestreActivo = cuatrimestre"
                                        :class="[
                                            'px-3 md:px-4 py-2 rounded-lg font-medium transition-all duration-300 text-sm md:text-base',
                                            cuatrimestreActivo === cuatrimestre
                                                ? (darkMode ? 'bg-green-600 text-white' : 'bg-green-500 text-white')
                                                : (darkMode ? 'bg-gray-700 text-gray-300 hover:bg-gray-600' : 'bg-gray-200 text-gray-700 hover:bg-gray-300')
                                        ]"
                                    >
                                        {{ cuatrimestre }}
                                    </button>
                                </div>

                                <!-- Stepper vertical de unidades (dinámico) -->
                                <div :class="['flex flex-row md:flex-col gap-2 border-l-2 md:border-l-0 md:border-t-2 pl-4 md:pl-0 md:pt-4', darkMode ? 'border-gray-600' : 'border-gray-300']">
                                    <button
                                        v-for="unidad in unidadesDisponibles"
                                        :key="unidad"
                                        @click="unidadActiva = unidad"
                                        :class="[
                                            'px-4 py-2 rounded-lg font-semibold transition-all duration-300 text-sm md:text-base relative flex items-center',
                                            unidadActiva === unidad
                                                ? (darkMode ? 'bg-blue-600 text-white' : 'bg-blue-500 text-white')
                                                : (darkMode ? 'bg-gray-700 text-gray-300 hover:bg-gray-600' : 'bg-gray-200 text-gray-700 hover:bg-gray-300')
                                        ]"
                                    >
                                        <span v-if="unidad !== 'Todos'" class="w-6 h-6 rounded-full flex items-center justify-center mr-2 font-bold" :class="unidadActiva === unidad ? (darkMode ? 'bg-white text-blue-600' : 'bg-white text-blue-500') : (darkMode ? 'bg-gray-500 text-white' : 'bg-gray-400 text-white')">
                                            {{ unidad.replace('U', '') }}
                                        </span>
                                        {{ unidad }}
                                    </button>
                                </div>
                            </div>

                            <!-- Cuatrimestres y materias -->
                            <div v-if="Object.keys(historialAcademico).length > 0" class="space-y-6 md:space-y-8">
                                <div v-for="(cuatrimestre, periodo) in historialFiltrado" :key="periodo" :class="['border rounded-xl p-4 md:p-6', darkMode ? 'border-gray-600 bg-gray-700' : 'border-gray-200 bg-gray-50']">
                                    <!-- Header del cuatrimestre -->
                                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 md:mb-6 gap-3">
                                        <h4 :class="['text-lg md:text-xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                            {{ periodo }}
                                        </h4>
                                        <span v-if="cuatrimestre.promedio" :class="['px-3 md:px-4 py-2 rounded-lg font-bold text-sm md:text-base', 
                                            parseFloat(cuatrimestre.promedio) >= 9.0 ? 'bg-green-100 text-green-800' :
                                            parseFloat(cuatrimestre.promedio) >= 8.0 ? 'bg-blue-100 text-blue-800' :
                                            parseFloat(cuatrimestre.promedio) >= 7.0 ? 'bg-yellow-100 text-yellow-800' :
                                            'bg-red-100 text-red-800'
                                        ]">
                                            Promedio: {{ cuatrimestre.promedio }}
                                        </span>
                                        <span v-else :class="['px-3 md:px-4 py-2 rounded-lg font-bold text-sm md:text-base bg-gray-100 text-gray-600']">
                                            En curso
                                        </span>
                                    </div>

                                    <!-- Tabla de materias -->
                                    <div class="overflow-x-auto">
                                        <table :class="['w-full rounded-lg overflow-hidden border-2', darkMode ? 'bg-gray-800 border-gray-500' : 'bg-white border-gray-300']">
                                            <thead :class="[darkMode ? 'bg-gray-600' : 'bg-gray-100']">
                                                <tr>
                                                    <th :class="['px-3 md:px-6 py-3 text-left font-bold border-r-2 text-xs md:text-sm', darkMode ? 'text-white border-gray-500' : 'text-gray-900 border-gray-300']">Materia</th>
                                                    <th :class="['px-3 md:px-6 py-3 text-center font-bold border-r-2 text-xs md:text-sm', darkMode ? 'text-white border-gray-500' : 'text-gray-900 border-gray-300']">Maestro</th>
                                                    <th :class="['px-3 md:px-6 py-3 text-center font-bold border-r-2 text-xs md:text-sm', darkMode ? 'text-white border-gray-500' : 'text-gray-900 border-gray-300']">Calificación</th>
                                                    <th :class="['px-3 md:px-6 py-3 text-center font-bold text-xs md:text-sm', darkMode ? 'text-white' : 'text-gray-900']">Asistencias</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(materia, index) in cuatrimestre.materias" :key="index" :class="['border-b-2', darkMode ? 'border-gray-600' : 'border-gray-200', index % 2 === 0 ? (darkMode ? 'bg-gray-800' : 'bg-white') : (darkMode ? 'bg-gray-700' : 'bg-gray-50')]">
                                                    <td :class="['px-3 md:px-6 py-3 md:py-4 border-r-2 text-xs md:text-sm', darkMode ? 'text-gray-200 border-gray-500' : 'text-gray-900 border-gray-300']">
                                                        <div class="font-medium">{{ materia.materia }}</div>
                                                        <div v-if="materia.codigo" :class="['text-xs mt-1', darkMode ? 'text-gray-400' : 'text-gray-500']">
                                                            {{ materia.codigo }}
                                                        </div>
                                                    </td>
                                                    <td :class="['px-3 md:px-6 py-3 md:py-4 text-center border-r-2 text-xs md:text-sm', darkMode ? 'text-gray-300 border-gray-500' : 'text-gray-700 border-gray-300']">
                                                        {{ materia.teacher_name || 'No asignado' }}
                                                    </td>
                                                    <td :class="['px-3 md:px-6 py-3 md:py-4 text-center font-bold text-sm md:text-lg border-r-2', getCalificacionColor(materia.calificacion_mostrar), darkMode ? 'border-gray-500' : 'border-gray-300']">
                                                        {{ materia.calificacion_mostrar || '--' }}
                                                    </td>
                                                    <td :class="['px-3 md:px-6 py-3 md:py-4 text-center']">
                                                        <span v-if="materia.asistencia_mostrar !== null && materia.asistencia_mostrar !== undefined" :class="['px-2 md:px-3 py-1 rounded-full text-xs md:text-sm font-medium', 
                                                            parseFloat(materia.asistencia_mostrar) >= 80 ? 'bg-green-100 text-green-800' :
                                                            parseFloat(materia.asistencia_mostrar) >= 60 ? 'bg-yellow-100 text-yellow-800' :
                                                            'bg-red-100 text-red-800'
                                                        ]">
                                                            {{ materia.asistencia_mostrar }}%
                                                        </span>
                                                        <span v-else :class="['px-2 md:px-3 py-1 rounded-full text-xs md:text-sm font-medium bg-gray-100 text-gray-600']">
                                                            --
                                                        </span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botón para regresar -->
                    <div class="mt-8 md:mt-12 text-center">
                        <Link :href="route('dashboard')" :class="['inline-flex items-center px-4 md:px-6 py-2 md:py-3 rounded-lg font-medium transition-colors text-sm md:text-base', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-gray-300' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']">
                            <svg class="w-4 h-4 md:w-5 md:h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Regresar al Dashboard
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
