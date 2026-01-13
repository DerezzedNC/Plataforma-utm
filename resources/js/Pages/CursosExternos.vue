<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref } from 'vue';

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Filtros y búsqueda
const searchQuery = ref('');
const selectedFilter = ref('Todas');

// Datos mock de cursos externos (después vendrán de la base de datos)
const cursosExternos = ref([
    {
        id: 1,
        titulo: 'Certificación AWS Cloud Practitioner',
        descripcion: 'Obtén la certificación fundamental de AWS para iniciar tu carrera en la nube. Aprende los conceptos básicos de los servicios de AWS, seguridad, arquitectura y precios.',
        proveedor: 'Amazon Web Services',
        modalidad: 'En línea',
        categoria: 'Tecnología',
        duracion: '40 horas',
        horario: 'Lunes a Viernes 18:00-20:00',
        costo: '$150 USD',
        instructor: 'Ing. Roberto Silva',
        telefono: '+52 951 123 4567',
        email: 'roberto.silva@aws.com',
        sitioWeb: 'aws.amazon.com/training',
        fechaInicio: '15 de Febrero 2024',
        nivel: 'Básico'
    },
    {
        id: 2,
        titulo: 'Diseño UX/UI con Figma',
        descripcion: 'Domina las herramientas y metodologías del diseño de experiencia de usuario. Aprende a crear interfaces intuitivas y atractivas usando Figma como herramienta principal.',
        proveedor: 'Platzi',
        modalidad: 'En línea',
        categoria: 'Diseño',
        duracion: '30 horas',
        horario: 'Flexible - A tu ritmo',
        costo: '$49 USD/mes',
        instructor: 'Dis. Laura Mendoza',
        telefono: '+52 55 8765 4321',
        email: 'laura.mendoza@platzi.com',
        sitioWeb: 'platzi.com/cursos/ux-ui',
        fechaInicio: 'Disponible ahora',
        nivel: 'Intermedio'
    },
    {
        id: 3,
        titulo: 'Google Analytics 4 Certified',
        descripcion: 'Conviértete en un experto en análisis web con la nueva versión de Google Analytics. Aprende a medir y optimizar el rendimiento de sitios web y aplicaciones.',
        proveedor: 'Google',
        modalidad: 'En línea',
        categoria: 'Marketing',
        duracion: '25 horas',
        horario: 'Autodirigido',
        costo: 'Gratuito',
        instructor: 'Google Team',
        telefono: 'N/A',
        email: 'support@google.com',
        sitioWeb: 'analytics.google.com/analytics/academy',
        fechaInicio: 'Inmediato',
        nivel: 'Intermedio'
    },
    {
        id: 4,
        titulo: 'Machine Learning con Python',
        descripcion: 'Aprende los fundamentos del aprendizaje automático utilizando Python y sus principales bibliotecas como scikit-learn, pandas y numpy.',
        proveedor: 'Coursera',
        modalidad: 'En línea',
        categoria: 'Tecnología',
        duracion: '60 horas',
        horario: 'Flexible',
        costo: '$79 USD/mes',
        instructor: 'Dr. Andrew Ng',
        telefono: '+1 650 123 4567',
        email: 'support@coursera.org',
        sitioWeb: 'coursera.org/machine-learning',
        fechaInicio: '1 de Marzo 2024',
        nivel: 'Avanzado'
    }
]);

// Filtros disponibles
const filtros = ['Todas', 'Tecnología', 'Diseño', 'Marketing', 'Negocios'];

// Función para filtrar cursos
const cursosFiltrados = () => {
    let cursos = cursosExternos.value;
    
    // Filtrar por categoría
    if (selectedFilter.value !== 'Todas') {
        cursos = cursos.filter(curso => curso.categoria === selectedFilter.value);
    }
    
    // Filtrar por búsqueda
    if (searchQuery.value) {
        cursos = cursos.filter(curso => 
            curso.titulo.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            curso.proveedor.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            curso.instructor.toLowerCase().includes(searchQuery.value.toLowerCase())
        );
    }
    
    return cursos;
};

// Función para obtener el color de la categoría
const getCategoriaColor = (categoria) => {
    switch(categoria) {
        case 'Tecnología': return 'bg-blue-100 text-blue-800';
        case 'Diseño': return 'bg-purple-100 text-purple-800';
        case 'Marketing': return 'bg-orange-100 text-orange-800';
        case 'Negocios': return 'bg-green-100 text-green-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

// Función para obtener el color de la modalidad
const getModalidadColor = (modalidad) => {
    return modalidad === 'En línea' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
};
</script>

<template>
    <Head title="Cursos Externos - UTM" />

    <AuthenticatedLayout>
        <!-- Contenido Principal -->
        <div class="min-h-screen">
            <!-- Header -->
            <div class="py-8">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <!-- Título y descripción -->
                    <div class="text-center mb-8">
                        <h1 :class="['text-4xl font-bold mb-4', darkMode ? 'text-white' : 'text-gray-900']">
                            Cursos Externos
                        </h1>
                        <p :class="['text-xl', darkMode ? 'text-gray-400' : 'text-gray-600']">
                            Cursos especializados de proveedores externos y plataformas en línea
                        </p>
                    </div>

                    <!-- Barra de búsqueda y filtros -->
                    <div class="flex flex-col md:flex-row gap-6 mb-8">
                        <!-- Búsqueda -->
                        <div class="flex-1">
                            <div class="relative">
                                <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Buscar cursos, proveedores o instructores..."
                                    :class="['pl-10 pr-4 py-3 w-full rounded-lg border text-lg', darkMode ? 'bg-gray-800 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500']"
                                />
                            </div>
                        </div>

                        <!-- Filtros por categoría -->
                        <div class="flex gap-2 flex-wrap">
                            <button
                                v-for="filtro in filtros"
                                :key="filtro"
                                @click="selectedFilter = filtro"
                                :class="[
                                    'px-6 py-3 rounded-lg font-medium transition-all duration-300',
                                    selectedFilter === filtro
                                        ? (darkMode ? 'bg-green-600 text-white' : 'bg-green-500 text-white')
                                        : (darkMode ? 'bg-gray-700 text-gray-300 hover:bg-gray-600' : 'bg-gray-200 text-gray-700 hover:bg-gray-300')
                                ]"
                            >
                                {{ filtro }}
                            </button>
                        </div>
                    </div>

                    <!-- Grid de cursos -->
                    <div class="grid grid-cols-1 gap-8">
                        <div
                            v-for="curso in cursosFiltrados()"
                            :key="curso.id"
                            :class="['rounded-xl shadow-lg border p-8 hover:shadow-xl transition-all duration-300', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']"
                        >
                            <!-- Header del curso -->
                            <div class="flex flex-col lg:flex-row justify-between items-start mb-6">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-3">
                                        <h3 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                            {{ curso.titulo }}
                                        </h3>
                                        <span :class="['px-3 py-1 rounded-full text-sm font-medium', getModalidadColor(curso.modalidad)]">
                                            {{ curso.modalidad }}
                                        </span>
                                    </div>
                                    <p :class="['text-lg font-medium mb-2', darkMode ? 'text-green-400' : 'text-green-600']">
                                        {{ curso.proveedor }}
                                    </p>
                                    <span :class="['px-3 py-1 rounded-full text-sm font-medium', getCategoriaColor(curso.categoria)]">
                                        {{ curso.categoria }}
                                    </span>
                                </div>
                            </div>

                            <!-- Descripción -->
                            <p :class="['text-lg mb-6', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                {{ curso.descripcion }}
                            </p>

                            <!-- Detalles del curso y información de contacto -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                <!-- Detalles del curso -->
                                <div>
                                    <h4 :class="['text-lg font-semibold mb-4', darkMode ? 'text-white' : 'text-gray-900']">
                                        Detalles del curso
                                    </h4>
                                    <div class="space-y-3">
                                        <!-- Duración -->
                                        <div class="flex items-center">
                                            <svg :class="['w-5 h-5 mr-3', darkMode ? 'text-green-400' : 'text-green-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <span :class="['text-base', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                <strong>Duración:</strong> {{ curso.duracion }}
                                            </span>
                                        </div>

                                        <!-- Horario -->
                                        <div class="flex items-center">
                                            <svg :class="['w-5 h-5 mr-3', darkMode ? 'text-green-400' : 'text-green-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                            <span :class="['text-base', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                <strong>Horario:</strong> {{ curso.horario }}
                                            </span>
                                        </div>

                                        <!-- Costo -->
                                        <div class="flex items-center">
                                            <svg :class="['w-5 h-5 mr-3', darkMode ? 'text-green-400' : 'text-green-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                            </svg>
                                            <span :class="['text-base', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                <strong>Costo:</strong> {{ curso.costo }}
                                            </span>
                                        </div>

                                        <!-- Instructor -->
                                        <div class="flex items-center">
                                            <svg :class="['w-5 h-5 mr-3', darkMode ? 'text-green-400' : 'text-green-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                            <span :class="['text-base font-medium', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                <strong>Instructor:</strong> {{ curso.instructor }}
                                            </span>
                                        </div>

                                        <!-- Fecha de inicio -->
                                        <div class="flex items-center">
                                            <svg :class="['w-5 h-5 mr-3', darkMode ? 'text-green-400' : 'text-green-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                            <span :class="['text-base', darkMode ? 'text-blue-400' : 'text-blue-600']">
                                                <strong>Fecha de inicio:</strong> {{ curso.fechaInicio }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Información de contacto -->
                                <div>
                                    <h4 :class="['text-lg font-semibold mb-4', darkMode ? 'text-white' : 'text-gray-900']">
                                        Información de contacto
                                    </h4>
                                    <div class="space-y-3">
                                        <!-- Teléfono -->
                                        <div v-if="curso.telefono !== 'N/A'" class="flex items-center">
                                            <svg :class="['w-5 h-5 mr-3', darkMode ? 'text-green-400' : 'text-green-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                            </svg>
                                            <span :class="['text-base', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                {{ curso.telefono }}
                                            </span>
                                        </div>

                                        <!-- Email -->
                                        <div class="flex items-center">
                                            <svg :class="['w-5 h-5 mr-3', darkMode ? 'text-green-400' : 'text-green-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                            </svg>
                                            <span :class="['text-base', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                {{ curso.email }}
                                            </span>
                                        </div>

                                        <!-- Sitio web -->
                                        <div class="flex items-center">
                                            <svg :class="['w-5 h-5 mr-3', darkMode ? 'text-green-400' : 'text-green-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9v-9m0-9v9m0 9c4.97 0 9-4.03 9-9s-4.03-9-9-9"></path>
                                            </svg>
                                            <span :class="['text-base', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                {{ curso.sitioWeb }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Botón de acción -->
                            <div class="mt-8">
                                <button :class="['w-full lg:w-auto px-8 py-4 rounded-xl font-bold text-lg transition-all duration-300 transform hover:scale-105', darkMode ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-green-500 hover:bg-green-600 text-white']">
                                    Solicitar Curso
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Botón para regresar -->
                    <div class="mt-12 text-center">
                        <Link :href="route('cursos-extra')" :class="['inline-flex items-center px-6 py-3 rounded-lg font-medium transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-gray-300' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Regresar a Cursos Extra
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>