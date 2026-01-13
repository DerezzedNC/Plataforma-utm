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

// Datos mock de cursos internos (después vendrán de la base de datos)
const cursosInternos = ref([
    {
        id: 1,
        titulo: 'Desarrollo Web con React',
        descripcion: 'Aprende a desarrollar aplicaciones web modernas con React y sus herramientas.',
        categoria: 'Tecnología',
        idioma: 'Español',
        horario: 'Lunes y Miércoles 16:00-18:00',
        lugar: 'Lab. Cómputo 1',
        cupos: '18/25',
        instructor: 'Dr. Juan Martínez',
        nivel: 'Intermedio',
        duracion: '8 semanas'
    },
    {
        id: 2,
        titulo: 'Inglés Técnico',
        descripcion: 'Mejora tu inglés técnico para el ámbito profesional y académico.',
        categoria: 'Idiomas',
        idioma: 'Español/Inglés',
        horario: 'Martes y Jueves 14:00-16:00',
        lugar: 'Aula 205',
        cupos: '22/30',
        instructor: 'Mtra. Ana López',
        nivel: 'Intermedio',
        duracion: '12 semanas'
    },
    {
        id: 3,
        titulo: 'Emprendimiento Digital',
        descripcion: 'Desarrolla habilidades empresariales para el mundo digital.',
        categoria: 'Negocios',
        idioma: 'Español',
        horario: 'Viernes 10:00-14:00',
        lugar: 'Aula Magna',
        cupos: '35/40',
        instructor: 'Mtro. Carlos Ruiz',
        nivel: 'Básico',
        duracion: '6 semanas'
    },
    {
        id: 4,
        titulo: 'Fotografía Digital',
        descripcion: 'Aprende técnicas de fotografía digital y edición de imágenes.',
        categoria: 'Arte',
        idioma: 'Español',
        horario: 'Sábados 9:00-12:00',
        lugar: 'Taller de Arte',
        cupos: '12/20',
        instructor: 'Lic. María González',
        nivel: 'Básico',
        duracion: '10 semanas'
    }
]);

// Filtros disponibles
const filtros = ['Todas', 'Tecnología', 'Idiomas', 'Negocios', 'Arte'];

// Función para filtrar cursos
const cursosFiltrados = () => {
    let cursos = cursosInternos.value;
    
    // Filtrar por categoría
    if (selectedFilter.value !== 'Todas') {
        cursos = cursos.filter(curso => curso.categoria === selectedFilter.value);
    }
    
    // Filtrar por búsqueda
    if (searchQuery.value) {
        cursos = cursos.filter(curso => 
            curso.titulo.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            curso.instructor.toLowerCase().includes(searchQuery.value.toLowerCase())
        );
    }
    
    return cursos;
};
</script>

<template>
    <Head title="Cursos Internos UTM" />

    <AuthenticatedLayout>
        <!-- Contenido Principal -->
        <div class="min-h-screen">
            <!-- Header -->
            <div class="py-8">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <!-- Título y descripción -->
                    <div class="text-center mb-8">
                        <h1 :class="['text-4xl font-bold mb-4', darkMode ? 'text-white' : 'text-gray-900']">
                            Cursos Internos UTM
                        </h1>
                        <p :class="['text-xl', darkMode ? 'text-gray-400' : 'text-gray-600']">
                            Cursos impartidos por profesores de la universidad
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
                                    placeholder="Buscar cursos o instructores..."
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
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div
                            v-for="curso in cursosFiltrados()"
                            :key="curso.id"
                            :class="['rounded-xl shadow-lg border p-8 hover:shadow-xl transition-all duration-300', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']"
                        >
                            <!-- Header del curso -->
                            <div class="flex justify-between items-start mb-6">
                                <h3 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    {{ curso.titulo }}
                                </h3>
                                <span 
                                    :class="[
                                        'px-3 py-1 rounded-full text-sm font-medium',
                                        curso.categoria === 'Tecnología' ? 'bg-blue-100 text-blue-800' :
                                        curso.categoria === 'Idiomas' ? 'bg-green-100 text-green-800' :
                                        curso.categoria === 'Negocios' ? 'bg-purple-100 text-purple-800' :
                                        'bg-pink-100 text-pink-800'
                                    ]"
                                >
                                    {{ curso.categoria }}
                                </span>
                            </div>

                            <!-- Descripción -->
                            <p :class="['text-lg mb-6', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                {{ curso.descripcion }}
                            </p>

                            <!-- Detalles del curso -->
                            <div class="space-y-3 mb-6">
                                <!-- Horario -->
                                <div class="flex items-center">
                                    <svg :class="['w-5 h-5 mr-3', darkMode ? 'text-green-400' : 'text-green-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span :class="['text-base', darkMode ? 'text-gray-300' : 'text-gray-700']">{{ curso.horario }}</span>
                                </div>

                                <!-- Lugar -->
                                <div class="flex items-center">
                                    <svg :class="['w-5 h-5 mr-3', darkMode ? 'text-green-400' : 'text-green-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    <span :class="['text-base', darkMode ? 'text-gray-300' : 'text-gray-700']">{{ curso.lugar }}</span>
                                </div>

                                <!-- Estudiantes -->
                                <div class="flex items-center">
                                    <svg :class="['w-5 h-5 mr-3', darkMode ? 'text-green-400' : 'text-green-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-1.5a3.001 3.001 0 00-5.5-1.5a3 3 0 005.5 1.5z"></path>
                                    </svg>
                                    <span :class="['text-base', darkMode ? 'text-gray-300' : 'text-gray-700']">{{ curso.cupos }} estudiantes</span>
                                </div>

                                <!-- Instructor -->
                                <div class="flex items-center">
                                    <svg :class="['w-5 h-5 mr-3', darkMode ? 'text-green-400' : 'text-green-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    <span :class="['text-base font-medium', darkMode ? 'text-gray-300' : 'text-gray-700']">Instructor: {{ curso.instructor }}</span>
                                </div>
                            </div>

                            <!-- Botón de acción -->
                            <button :class="['w-full py-4 px-6 rounded-xl font-bold text-lg transition-all duration-300 transform hover:scale-105', darkMode ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-green-500 hover:bg-green-600 text-white']">
                                Ver más detalles
                            </button>
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