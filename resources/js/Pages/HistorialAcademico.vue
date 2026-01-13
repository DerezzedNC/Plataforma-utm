<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Obtener datos del usuario autenticado
const page = usePage();
const user = computed(() => page.props.auth.user);

// Estados reactivos
const cuatrimestreActivo = ref('Todos');

// Información del estudiante (datos del usuario autenticado)
const estudianteInfo = ref({
    nombre: user.value.name,
    matricula: '2021030123', // Este vendría de la base de datos del usuario
    carrera: 'Ingeniería en Computación',
    cuatrimestreActual: '6to Cuatrimestre',
    promedioGeneral: 8.7,
    avanceCarrera: 75
});

// Datos del historial académico por cuatrimestres
const historialAcademico = ref({
    '1er Cuatrimestre - Agosto 2021 - Noviembre 2021': {
        promedio: 8.8,
        materias: [
            { materia: 'Cálculo Diferencial', calificacion: 9.2, estado: 'Aprobada' },
            { materia: 'Álgebra Lineal', calificacion: 8.8, estado: 'Aprobada' },
            { materia: 'Programación I', calificacion: 9.5, estado: 'Aprobada' },
            { materia: 'Inglés I', calificacion: 9.0, estado: 'Aprobada' },
            { materia: 'Metodología de la Investigación', calificacion: 8.5, estado: 'Aprobada' }
        ]
    },
    '2do Cuatrimestre - Diciembre 2021 - Marzo 2022': {
        promedio: 8.6,
        materias: [
            { materia: 'Cálculo Integral', calificacion: 8.9, estado: 'Aprobada' },
            { materia: 'Física I', calificacion: 8.2, estado: 'Aprobada' },
            { materia: 'Programación II', calificacion: 9.1, estado: 'Aprobada' },
            { materia: 'Inglés II', calificacion: 8.7, estado: 'Aprobada' },
            { materia: 'Contabilidad', calificacion: 8.3, estado: 'Aprobada' }
        ]
    },
    '3er Cuatrimestre - Abril 2022 - Julio 2022': {
        promedio: 8.9,
        materias: [
            { materia: 'Cálculo Vectorial', calificacion: 9.0, estado: 'Aprobada' },
            { materia: 'Física II', calificacion: 8.5, estado: 'Aprobada' },
            { materia: 'Estructuras de Datos', calificacion: 9.3, estado: 'Aprobada' },
            { materia: 'Inglés III', calificacion: 9.2, estado: 'Aprobada' },
            { materia: 'Probabilidad y Estadística', calificacion: 8.7, estado: 'Aprobada' }
        ]
    },
    '4to Cuatrimestre - Agosto 2022 - Noviembre 2022': {
        promedio: 8.4,
        materias: [
            { materia: 'Ecuaciones Diferenciales', calificacion: 8.1, estado: 'Aprobada' },
            { materia: 'Circuitos Eléctricos', calificacion: 8.0, estado: 'Aprobada' },
            { materia: 'Programación Orientada a Objetos', calificacion: 9.0, estado: 'Aprobada' },
            { materia: 'Inglés IV', calificacion: 8.8, estado: 'Aprobada' },
            { materia: 'Base de Datos', calificacion: 8.3, estado: 'Aprobada' }
        ]
    },
    '5to Cuatrimestre - Diciembre 2022 - Marzo 2023': {
        promedio: 8.8,
        materias: [
            { materia: 'Análisis Numérico', calificacion: 8.7, estado: 'Aprobada' },
            { materia: 'Sistemas Operativos', calificacion: 9.0, estado: 'Aprobada' },
            { materia: 'Redes de Computadoras', calificacion: 8.5, estado: 'Aprobada' },
            { materia: 'Ingeniería de Software', calificacion: 9.2, estado: 'Aprobada' },
            { materia: 'Desarrollo del Pensamiento', calificacion: 8.8, estado: 'Aprobada' }
        ]
    },
    '6to Cuatrimestre - Abril 2023 - Julio 2023': {
        promedio: 8.6,
        materias: [
            { materia: 'Tópicos de Calidad para el Diseño de Software', calificacion: 8.8, estado: 'Aprobada' },
            { materia: 'Programación Web', calificacion: 9.1, estado: 'Aprobada' },
            { materia: 'Proyecto Integrador I', calificacion: 8.9, estado: 'Aprobada' },
            { materia: 'Administración de Proyectos', calificacion: 8.2, estado: 'Aprobada' },
            { materia: 'Seguridad Informática', calificacion: 8.4, estado: 'Aprobada' }
        ]
    }
});

// Función para obtener color de calificación
const getCalificacionColor = (calificacion) => {
    if (calificacion >= 9.0) return darkMode.value ? 'text-green-400' : 'text-green-600';
    if (calificacion >= 8.0) return darkMode.value ? 'text-blue-400' : 'text-blue-600';
    if (calificacion >= 7.0) return darkMode.value ? 'text-yellow-400' : 'text-yellow-600';
    return darkMode.value ? 'text-red-400' : 'text-red-600';
};

// Función para descargar historial (simulada)
const descargarHistorial = () => {
    alert('Funcionalidad de descarga será implementada próximamente');
};

// Filtrar cuatrimestres
const cuatrimestres = ['Todos', '1er Cuatrimestre', '2do Cuatrimestre', '3er Cuatrimestre', '4to Cuatrimestre', '5to Cuatrimestre', '6to Cuatrimestre'];

// Función para filtrar historial
const historialFiltrado = () => {
    if (cuatrimestreActivo.value === 'Todos') {
        return historialAcademico.value;
    }
    
    const filtered = {};
    Object.keys(historialAcademico.value).forEach(key => {
        if (key.includes(cuatrimestreActivo.value)) {
            filtered[key] = historialAcademico.value[key];
        }
    });
    return filtered;
};
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

                    <!-- Información del Estudiante -->
                    <div :class="['rounded-2xl shadow-xl border p-8 mb-8', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
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
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                            <div>
                                <label :class="['block text-sm font-medium mb-1', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                    Nombre
                                </label>
                                <p :class="['text-lg font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                    {{ estudianteInfo.nombre }}
                                </p>
                            </div>
                            <div>
                                <label :class="['block text-sm font-medium mb-1', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                    Matrícula
                                </label>
                                <p :class="['text-lg font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                    {{ estudianteInfo.matricula }}
                                </p>
                            </div>
                            <div>
                                <label :class="['block text-sm font-medium mb-1', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                    Carrera
                                </label>
                                <p :class="['text-lg font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                    {{ estudianteInfo.carrera }}
                                </p>
                            </div>
                            <div>
                                <label :class="['block text-sm font-medium mb-1', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                    Cuatrimestre Actual
                                </label>
                                <p :class="['text-lg font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                    {{ estudianteInfo.cuatrimestreActual }}
                                </p>
                            </div>
                        </div>

                        <!-- Estadísticas académicas -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Promedio General -->
                            <div :class="['text-center p-6 rounded-xl', darkMode ? 'bg-gray-700' : 'bg-gray-50']">
                                <div :class="['text-4xl font-bold mb-2', darkMode ? 'text-green-400' : 'text-green-600']">
                                    📈
                                </div>
                                <div :class="['text-3xl font-bold mb-1', darkMode ? 'text-green-400' : 'text-green-600']">
                                    {{ estudianteInfo.promedioGeneral }}
                                </div>
                                <p :class="['text-sm', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                    Promedio General
                                </p>
                            </div>

                            <!-- Avance de Carrera -->
                            <div :class="['text-center p-6 rounded-xl', darkMode ? 'bg-gray-700' : 'bg-gray-50']">
                                <div :class="['text-4xl font-bold mb-2', darkMode ? 'text-purple-400' : 'text-purple-600']">
                                    🎯
                                </div>
                                <div :class="['text-3xl font-bold mb-1', darkMode ? 'text-purple-400' : 'text-purple-600']">
                                    {{ estudianteInfo.avanceCarrera }}%
                                </div>
                                <p :class="['text-sm', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                    Avance de Carrera
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Historial por Cuatrimestres -->
                    <div :class="['rounded-2xl shadow-xl border p-8', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                        <!-- Header del historial -->
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
                            <div>
                                <h3 :class="['text-2xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                    Historial por Cuatrimestres
                                </h3>
                                <p :class="['text-lg', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                    Calificaciones detalladas por periodo académico
                                </p>
                            </div>
                            <button 
                                @click="descargarHistorial"
                                :class="['mt-4 md:mt-0 px-6 py-3 rounded-lg font-medium transition-colors flex items-center', darkMode ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                            >
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Descargar Historial
                            </button>
                        </div>

                        <!-- Filtros de cuatrimestre -->
                        <div class="flex gap-2 flex-wrap mb-8">
                            <button
                                v-for="cuatrimestre in cuatrimestres"
                                :key="cuatrimestre"
                                @click="cuatrimestreActivo = cuatrimestre"
                                :class="[
                                    'px-4 py-2 rounded-lg font-medium transition-all duration-300',
                                    cuatrimestreActivo === cuatrimestre
                                        ? (darkMode ? 'bg-green-600 text-white' : 'bg-green-500 text-white')
                                        : (darkMode ? 'bg-gray-700 text-gray-300 hover:bg-gray-600' : 'bg-gray-200 text-gray-700 hover:bg-gray-300')
                                ]"
                            >
                                {{ cuatrimestre }}
                            </button>
                        </div>

                        <!-- Cuatrimestres y materias -->
                        <div class="space-y-8">
                            <div v-for="(cuatrimestre, periodo) in historialFiltrado()" :key="periodo" :class="['border rounded-xl p-6', darkMode ? 'border-gray-600 bg-gray-700' : 'border-gray-200 bg-gray-50']">
                                <!-- Header del cuatrimestre -->
                                <div class="flex justify-between items-center mb-6">
                                    <h4 :class="['text-xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                        {{ periodo }}
                                    </h4>
                                    <span :class="['px-4 py-2 rounded-lg font-bold', 
                                        cuatrimestre.promedio >= 9.0 ? 'bg-green-100 text-green-800' :
                                        cuatrimestre.promedio >= 8.0 ? 'bg-blue-100 text-blue-800' :
                                        cuatrimestre.promedio >= 7.0 ? 'bg-yellow-100 text-yellow-800' :
                                        'bg-red-100 text-red-800'
                                    ]">
                                        Promedio: {{ cuatrimestre.promedio }}
                                    </span>
                                </div>

                                <!-- Tabla de materias -->
                                <div class="overflow-x-auto">
                                    <table :class="['w-full rounded-lg overflow-hidden border-2', darkMode ? 'bg-gray-800 border-gray-500' : 'bg-white border-gray-300']">
                                        <thead :class="[darkMode ? 'bg-gray-600' : 'bg-gray-100']">
                                            <tr>
                                                <th :class="['px-6 py-4 text-left font-bold border-r-2', darkMode ? 'text-white border-gray-500' : 'text-gray-900 border-gray-300']">Materia</th>
                                                <th :class="['px-6 py-4 text-center font-bold border-r-2', darkMode ? 'text-white border-gray-500' : 'text-gray-900 border-gray-300']">Calificación</th>
                                                <th :class="['px-6 py-4 text-center font-bold', darkMode ? 'text-white' : 'text-gray-900']">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(materia, index) in cuatrimestre.materias" :key="index" :class="['border-b-2', darkMode ? 'border-gray-600' : 'border-gray-200', index % 2 === 0 ? (darkMode ? 'bg-gray-800' : 'bg-white') : (darkMode ? 'bg-gray-700' : 'bg-gray-50')]">
                                                <td :class="['px-6 py-4 border-r-2', darkMode ? 'text-gray-200 border-gray-500' : 'text-gray-900 border-gray-300']">
                                                    {{ materia.materia }}
                                                </td>
                                                <td :class="['px-6 py-4 text-center font-bold text-lg border-r-2', getCalificacionColor(materia.calificacion), darkMode ? 'border-gray-500' : 'border-gray-300']">
                                                    {{ materia.calificacion }}
                                                </td>
                                                <td :class="['px-6 py-4 text-center']">
                                                    <span :class="['px-3 py-1 rounded-full text-sm font-medium', 
                                                        materia.estado === 'Aprobada' ? 'bg-green-100 text-green-800' :
                                                        materia.estado === 'Reprobada' ? 'bg-red-100 text-red-800' :
                                                        'bg-yellow-100 text-yellow-800'
                                                    ]">
                                                        {{ materia.estado }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botón para regresar -->
                    <div class="mt-12 text-center">
                        <Link :href="route('dashboard')" :class="['inline-flex items-center px-6 py-3 rounded-lg font-medium transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-gray-300' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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