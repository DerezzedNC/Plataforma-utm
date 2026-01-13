<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
// Heroicons - Outline version
import { 
    MagnifyingGlassIcon,
    ClockIcon,
    UserIcon,
    MapPinIcon,
    BookOpenIcon,
    XMarkIcon,
    LinkIcon,
    AcademicCapIcon
} from '@heroicons/vue/24/outline';

const { darkMode } = useDarkMode();

// Estados
const cursos = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const filterType = ref('todos');
const selectedCourse = ref(null);
const showModal = ref(false);

// Cargar cursos
const loadCourses = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/student/courses');
        
        // Asegurar que siempre sea un array
        if (Array.isArray(response.data)) {
            cursos.value = response.data;
        } else {
            console.warn('La respuesta no es un array:', response.data);
            cursos.value = [];
        }
        
        console.log('Cursos cargados:', cursos.value.length);
    } catch (error) {
        console.error('Error al cargar cursos:', error);
        console.error('Error response:', error.response);
        
        // En caso de error, inicializar como array vacío
        cursos.value = [];
        
        // Solo mostrar alert si no es un error 404 o similar
        if (error.response?.status !== 404) {
            alert('Error al cargar los cursos: ' + (error.response?.data?.message || error.message));
        }
    } finally {
        loading.value = false;
    }
};

// Ver detalles del curso
const viewCourse = async (course) => {
    try {
        const response = await axios.get(`/student/courses/${course.id}`);
        selectedCourse.value = response.data;
        showModal.value = true;
    } catch (error) {
        console.error('Error al cargar detalles del curso:', error);
        alert('Error al cargar los detalles del curso');
    }
};

// Cerrar modal
const closeModal = () => {
    showModal.value = false;
    selectedCourse.value = null;
};

// Cursos filtrados
const cursosFiltrados = computed(() => {
    // Asegurar que siempre sea un array
    if (!Array.isArray(cursos.value)) {
        return [];
    }

    let filtered = [...cursos.value];

    // Filtrar por tipo
    if (filterType.value !== 'todos') {
        filtered = filtered.filter(c => c && c.tipo === filterType.value);
    }

    // Filtrar por búsqueda
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(c => 
            c && (
                (c.nombre && c.nombre.toLowerCase().includes(query)) ||
                (c.descripcion && c.descripcion.toLowerCase().includes(query)) ||
                (c.teacher && c.teacher.name && c.teacher.name.toLowerCase().includes(query))
            )
        );
    }

    return filtered;
});

// Obtener parámetros de la URL al montar
const initializeFromUrl = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const tipoParam = urlParams.get('tipo');
    if (tipoParam === 'interno' || tipoParam === 'externo') {
        filterType.value = tipoParam;
    }
};

onMounted(() => {
    initializeFromUrl();
    loadCourses();
});
</script>

<template>
    <Head title="Cursos Disponibles" />

    <AuthenticatedLayout>
        <div class="min-h-screen py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="text-center mb-8">
                    <h1 :class="['font-heading text-4xl font-bold mb-4', darkMode ? 'text-white' : 'text-gray-900']">
                        Cursos Disponibles
                    </h1>
                    <p :class="['font-body text-xl', darkMode ? 'text-gray-400' : 'text-gray-600']">
                        Cursos internos y externos disponibles para tu carrera
                    </p>
                </div>

                <!-- Barra de búsqueda y filtros -->
                <div class="flex flex-col md:flex-row gap-6 mb-8">
                    <!-- Búsqueda -->
                    <div class="flex-1">
                        <div class="relative">
                            <MagnifyingGlassIcon class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar cursos o instructores..."
                                :class="['font-body pl-12 pr-4 py-3 w-full rounded-lg border border-gray-300 text-lg transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-800 border-gray-600 text-white placeholder-gray-400 focus:ring-blue-400' : 'bg-white text-gray-900 placeholder-gray-500']"
                            />
                        </div>
                    </div>

                    <!-- Filtros por tipo -->
                    <div class="flex gap-2 flex-wrap">
                        <button
                            @click="filterType = 'todos'"
                            :class="[
                                'font-body px-6 py-3 rounded-lg font-medium transition-all duration-300',
                                filterType === 'todos'
                                    ? (darkMode ? 'bg-blue-500 text-white' : 'bg-blue-500 text-white')
                                    : (darkMode ? 'bg-gray-700 text-gray-300 hover:bg-gray-600' : 'bg-gray-200 text-gray-700 hover:bg-gray-300')
                            ]"
                        >
                            Todos
                        </button>
                        <button
                            @click="filterType = 'interno'"
                            :class="[
                                'font-body px-6 py-3 rounded-lg font-medium transition-all duration-300',
                                filterType === 'interno'
                                    ? (darkMode ? 'bg-blue-500 text-white' : 'bg-blue-500 text-white')
                                    : (darkMode ? 'bg-gray-700 text-gray-300 hover:bg-gray-600' : 'bg-gray-200 text-gray-700 hover:bg-gray-300')
                            ]"
                        >
                            Internos
                        </button>
                        <button
                            @click="filterType = 'externo'"
                            :class="[
                                'font-body px-6 py-3 rounded-lg font-medium transition-all duration-300',
                                filterType === 'externo'
                                    ? (darkMode ? 'bg-blue-500 text-white' : 'bg-blue-500 text-white')
                                    : (darkMode ? 'bg-gray-700 text-gray-300 hover:bg-gray-600' : 'bg-gray-200 text-gray-700 hover:bg-gray-300')
                            ]"
                        >
                            Externos
                        </button>
                    </div>
                </div>

                <!-- Loading -->
                <div v-if="loading && cursos.length === 0" class="text-center py-12">
                    <p :class="[darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando cursos...</p>
                </div>

                <!-- Sin cursos -->
                <div v-else-if="cursosFiltrados.length === 0" class="text-center py-12">
                    <BookOpenIcon class="w-24 h-24 mx-auto mb-4 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" />
                    <p :class="['font-body', darkMode ? 'text-gray-400' : 'text-gray-600']">
                        {{ searchQuery || filterType !== 'todos' ? 'No se encontraron cursos con los filtros seleccionados' : 'No hay cursos disponibles en este momento' }}
                    </p>
                </div>

                <!-- Grid de cursos -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <div
                        v-for="curso in cursosFiltrados"
                        :key="curso.id"
                        :class="['rounded-xl shadow-lg border p-8 hover:shadow-xl transition-all duration-300 cursor-pointer', darkMode ? 'bg-gray-800 border-gray-600 hover:border-gray-500' : 'bg-white border-gray-200 hover:border-gray-300']"
                        @click="viewCourse(curso)"
                    >
                        <!-- Header del curso -->
                        <div class="flex justify-between items-start mb-6">
                            <h3 :class="['font-heading text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                {{ curso.nombre }}
                            </h3>
                            <span
                                :class="[
                                    'font-body px-3 py-1 rounded-full text-sm font-medium',
                                    curso.tipo === 'interno'
                                        ? (darkMode ? 'bg-blue-900 text-blue-200' : 'bg-blue-100 text-blue-800')
                                        : (darkMode ? 'bg-purple-900 text-purple-200' : 'bg-purple-100 text-purple-800')
                                ]"
                            >
                                {{ curso.tipo === 'interno' ? 'Interno' : 'Externo' }}
                            </span>
                        </div>

                        <!-- Descripción -->
                        <p :class="['font-body text-base mb-6 line-clamp-3', darkMode ? 'text-gray-300' : 'text-gray-600']">
                            {{ curso.descripcion }}
                        </p>

                        <!-- Detalles del curso -->
                        <div class="space-y-3 mb-6">
                            <!-- Duración -->
                            <div class="flex items-center">
                                <ClockIcon :class="['w-5 h-5 mr-3', darkMode ? 'text-blue-400' : 'text-blue-600']" />
                                <span :class="['font-body text-sm', darkMode ? 'text-gray-300' : 'text-gray-700']">{{ curso.tiempo_duracion }}</span>
                            </div>

                            <!-- Instructor -->
                            <div v-if="curso.teacher" class="flex items-center">
                                <UserIcon :class="['w-5 h-5 mr-3', darkMode ? 'text-blue-400' : 'text-blue-600']" />
                                <span :class="['font-body text-sm font-medium', darkMode ? 'text-gray-300' : 'text-gray-700']">{{ curso.teacher.name }}</span>
                            </div>

                            <!-- Aula -->
                            <div v-if="curso.aula" class="flex items-center">
                                <MapPinIcon :class="['w-5 h-5 mr-3', darkMode ? 'text-blue-400' : 'text-blue-600']" />
                                <span :class="['font-body text-sm', darkMode ? 'text-gray-300' : 'text-gray-700']">{{ curso.aula }}</span>
                            </div>

                            <!-- Costo -->
                            <div v-if="curso.costo" class="flex items-center">
                                <AcademicCapIcon :class="['w-5 h-5 mr-3', darkMode ? 'text-blue-400' : 'text-blue-600']" />
                                <span :class="['font-body text-sm font-semibold', darkMode ? 'text-green-400' : 'text-green-600']">${{ parseFloat(curso.costo).toFixed(2) }}</span>
                            </div>

                            <!-- Carreras -->
                            <div v-if="curso.careers && curso.careers.length > 0" class="flex flex-wrap gap-1">
                                <span
                                    v-for="career in curso.careers"
                                    :key="career.id"
                                    :class="['font-body px-2 py-1 rounded text-xs', darkMode ? 'bg-gray-700 text-gray-300' : 'bg-gray-100 text-gray-700']"
                                >
                                    {{ career.nombre }}
                                </span>
                            </div>
                        </div>

                        <!-- Botón de acción -->
                        <button
                            :class="['font-body w-full py-3 px-6 rounded-lg font-semibold text-base transition-all duration-300', curso.tipo === 'interno' ? (darkMode ? 'bg-blue-500 hover:bg-blue-600 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white') : (darkMode ? 'bg-purple-600 hover:bg-purple-700 text-white' : 'bg-purple-500 hover:bg-purple-600 text-white')]"
                            @click.stop="viewCourse(curso)"
                        >
                            Ver más detalles
                        </button>
                    </div>
                </div>

                <!-- Modal de detalles del curso -->
                <div
                    v-if="showModal && selectedCourse"
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="closeModal"
                >
                    <div
                        :class="['w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-xl shadow-xl', darkMode ? 'bg-gray-800' : 'bg-white']"
                        @click.stop
                    >
                        <div class="p-8">
                            <div class="flex justify-between items-start mb-6">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <span
                                            :class="[
                                                'font-body px-3 py-1 rounded-full text-sm font-medium',
                                                selectedCourse.tipo === 'interno'
                                                    ? (darkMode ? 'bg-blue-900 text-blue-200' : 'bg-blue-100 text-blue-800')
                                                    : (darkMode ? 'bg-purple-900 text-purple-200' : 'bg-purple-100 text-purple-800')
                                            ]"
                                        >
                                            {{ selectedCourse.tipo === 'interno' ? 'Interno' : 'Externo' }}
                                        </span>
                                    </div>
                                    <h2 :class="['font-heading text-3xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                        {{ selectedCourse.nombre }}
                                    </h2>
                                </div>
                                <button
                                    @click="closeModal"
                                    :class="['p-2 rounded-lg transition-colors', darkMode ? 'text-gray-400 hover:text-white hover:bg-gray-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100']"
                                >
                                    <XMarkIcon class="w-6 h-6" />
                                </button>
                            </div>

                            <!-- Descripción completa -->
                            <div class="mb-6">
                                <h3 :class="['font-heading text-lg font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">Descripción</h3>
                                <p :class="['font-body text-base', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                    {{ selectedCourse.descripcion }}
                                </p>
                            </div>

                            <!-- Información detallada -->
                            <div class="space-y-4 mb-6">
                                <div class="flex items-center">
                                    <ClockIcon :class="['w-5 h-5 mr-3', darkMode ? 'text-blue-400' : 'text-blue-600']" />
                                    <span :class="['font-body text-base', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                        <strong>Duración:</strong> {{ selectedCourse.tiempo_duracion }}
                                    </span>
                                </div>

                                <div v-if="selectedCourse.teacher" class="flex items-center">
                                    <UserIcon :class="['w-5 h-5 mr-3', darkMode ? 'text-blue-400' : 'text-blue-600']" />
                                    <span :class="['font-body text-base', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                        <strong>Instructor:</strong> {{ selectedCourse.teacher.name }}
                                    </span>
                                </div>

                                <div v-if="selectedCourse.aula" class="flex items-center">
                                    <MapPinIcon :class="['w-5 h-5 mr-3', darkMode ? 'text-blue-400' : 'text-blue-600']" />
                                    <span :class="['font-body text-base', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                        <strong>Aula:</strong> {{ selectedCourse.aula }}
                                    </span>
                                </div>

                                <div v-if="selectedCourse.costo" class="flex items-center">
                                    <AcademicCapIcon :class="['w-5 h-5 mr-3', darkMode ? 'text-green-400' : 'text-green-600']" />
                                    <span :class="['font-body text-base font-semibold', darkMode ? 'text-green-400' : 'text-green-600']">
                                        <strong>Costo:</strong> ${{ parseFloat(selectedCourse.costo).toFixed(2) }}
                                    </span>
                                </div>

                                <div v-if="selectedCourse.link" class="flex items-center">
                                    <LinkIcon :class="['w-5 h-5 mr-3', darkMode ? 'text-blue-400' : 'text-blue-600']" />
                                    <a
                                        :href="selectedCourse.link"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        :class="['font-body text-base text-blue-600 hover:text-blue-800 underline', darkMode ? 'text-blue-400 hover:text-blue-300' : '']"
                                    >
                                        Ver más información
                                    </a>
                                </div>

                                <div v-if="selectedCourse.careers && selectedCourse.careers.length > 0">
                                    <h4 :class="['font-heading text-base font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">Carreras relacionadas:</h4>
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            v-for="career in selectedCourse.careers"
                                            :key="career.id"
                                            :class="['font-body px-3 py-1 rounded-full text-sm', darkMode ? 'bg-gray-700 text-gray-300' : 'bg-gray-100 text-gray-700']"
                                        >
                                            {{ career.nombre }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Botón de cerrar -->
                            <div class="flex justify-end">
                                <button
                                    @click="closeModal"
                                    :class="['font-body px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-600 hover:bg-gray-700 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-900']"
                                >
                                    Cerrar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

