<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, onMounted } from 'vue';
import axios from 'axios';
// Heroicons - Outline version
import { 
    UserGroupIcon,
    AcademicCapIcon,
    DocumentTextIcon,
    UserPlusIcon,
    BriefcaseIcon,
    BookOpenIcon,
    BuildingOfficeIcon,
    CalendarDaysIcon,
    MegaphoneIcon,
    CalendarIcon
} from '@heroicons/vue/24/outline';

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Datos del administrador
const adminStats = ref({
    totalEstudiantes: 0,
    totalMaestros: 0,
    documentosPendientes: 0
});

const loading = ref(true);

// Cargar estadísticas reales
const loadStats = async () => {
    try {
        loading.value = true;
        
        // Cargar total de estudiantes
        const studentsResponse = await axios.get('/admin/students');
        adminStats.value.totalEstudiantes = studentsResponse.data.length || 0;
        
        // Cargar total de maestros
        const teachersResponse = await axios.get('/admin/teachers');
        adminStats.value.totalMaestros = teachersResponse.data.length || 0;
        
        // Cargar documentos pendientes (excluyendo finalizados y cancelados)
        const documentsResponse = await axios.get('/admin/documents');
        const allDocuments = documentsResponse.data || [];
        adminStats.value.documentosPendientes = allDocuments.filter(doc => 
            doc.estado !== 'finalizado' && doc.estado !== 'cancelado'
        ).length;
    } catch (error) {
        console.error('Error cargando estadísticas:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadStats();
});
</script>

<template>
    <Head title="Portal Administrativo - UTM" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                
                <!-- Header de Bienvenida -->
                <div :class="['mb-8 rounded-2xl p-6 border shadow-lg', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <div class="text-center">
                        <h1 :class="['font-heading text-3xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                            Portal Administrativo UTM
                        </h1>
                        <p :class="['font-body text-lg', darkMode ? 'text-gray-400' : 'text-gray-600']">
                            Gestión integral del sistema académico
                        </p>
                    </div>
                </div>

                <!-- Estadísticas Rápidas -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    <div :class="['rounded-xl border p-6 shadow-lg transition-all hover:shadow-xl', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                        <div class="flex items-center justify-between">
                            <div>
                                <p :class="['font-body text-sm font-medium mb-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                    Total Estudiantes
                                </p>
                                <p :class="['font-heading text-3xl font-bold', darkMode ? 'text-green-400' : 'text-green-600']">
                                    {{ loading ? '...' : adminStats.totalEstudiantes }}
                                </p>
                            </div>
                            <div :class="['w-16 h-16 rounded-full flex items-center justify-center', darkMode ? 'bg-green-500/20' : 'bg-green-100']">
                                <UserGroupIcon class="w-8 h-8" :class="darkMode ? 'text-green-400' : 'text-green-600'" />
                            </div>
                        </div>
                    </div>

                    <div :class="['rounded-xl border p-6 shadow-lg transition-all hover:shadow-xl', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                        <div class="flex items-center justify-between">
                            <div>
                                <p :class="['font-body text-sm font-medium mb-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                    Total Maestros
                                </p>
                                <p :class="['font-heading text-3xl font-bold', darkMode ? 'text-blue-400' : 'text-blue-600']">
                                    {{ loading ? '...' : adminStats.totalMaestros }}
                                </p>
                            </div>
                            <div :class="['w-16 h-16 rounded-full flex items-center justify-center', darkMode ? 'bg-blue-500/20' : 'bg-blue-100']">
                                <AcademicCapIcon class="w-8 h-8" :class="darkMode ? 'text-blue-400' : 'text-blue-600'" />
                            </div>
                        </div>
                    </div>

                    <div :class="['rounded-xl border p-6 shadow-lg transition-all hover:shadow-xl', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                        <div class="flex items-center justify-between">
                            <div>
                                <p :class="['font-body text-sm font-medium mb-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                    Documentos Pendientes
                                </p>
                                <p :class="['font-heading text-3xl font-bold', darkMode ? 'text-orange-400' : 'text-orange-600']">
                                    {{ loading ? '...' : adminStats.documentosPendientes }}
                                </p>
                            </div>
                            <div :class="['w-16 h-16 rounded-full flex items-center justify-center', darkMode ? 'bg-orange-500/20' : 'bg-orange-100']">
                                <DocumentTextIcon class="w-8 h-8" :class="darkMode ? 'text-orange-400' : 'text-orange-600'" />
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Panel de Gestión -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    
                    <!-- Gestión de Estudiantes -->
                    <div :class="['rounded-xl border hover:shadow-lg transition-all duration-300 group cursor-pointer', darkMode ? 'bg-gray-800 border-gray-600 hover:border-green-500' : 'bg-white border-gray-200 hover:border-green-500']">
                        <div class="p-8 text-center">
                            <div :class="['w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4', darkMode ? 'bg-green-500/20 group-hover:bg-green-500/30' : 'bg-green-100 group-hover:bg-green-200']">
                                <UserPlusIcon class="w-8 h-8" :class="darkMode ? 'text-green-400' : 'text-green-600'" />
                            </div>
                            <h3 :class="['font-heading text-xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Gestión de Estudiantes
                            </h3>
                            <p :class="['font-body text-sm mb-4', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                Dar de alta, editar y gestionar estudiantes
                            </p>
                            <div class="flex flex-col gap-2">
                                <Link :href="route('admin.estudiantes.index')" :class="['font-body px-4 py-2 rounded-lg font-medium transition-colors', darkMode ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white']">
                                    Ver Estudiantes
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Gestión de Maestros -->
                    <div :class="['rounded-xl border hover:shadow-lg transition-all duration-300 group cursor-pointer', darkMode ? 'bg-gray-800 border-gray-600 hover:border-green-500' : 'bg-white border-gray-200 hover:border-green-500']">
                        <div class="p-8 text-center">
                            <div :class="['w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4', darkMode ? 'bg-green-500/20 group-hover:bg-green-500/30' : 'bg-green-100 group-hover:bg-green-200']">
                                <BriefcaseIcon class="w-8 h-8" :class="darkMode ? 'text-green-400' : 'text-green-600'" />
                            </div>
                            <h3 :class="['font-heading text-xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Gestión de Maestros
                            </h3>
                            <p :class="['font-body text-sm mb-4', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                Dar de alta, editar y gestionar maestros
                            </p>
                            <div class="flex flex-col gap-2">
                                <Link :href="route('admin.maestros.index')" :class="['font-body px-4 py-2 rounded-lg font-medium transition-colors', darkMode ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white']">
                                    Ver Maestros
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Gestión de Grupos -->
                    <div :class="['rounded-xl border hover:shadow-lg transition-all duration-300 group cursor-pointer', darkMode ? 'bg-gray-800 border-gray-600 hover:border-green-500' : 'bg-white border-gray-200 hover:border-green-500']">
                        <div class="p-8 text-center">
                            <div :class="['w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4', darkMode ? 'bg-green-500/20 group-hover:bg-green-500/30' : 'bg-green-100 group-hover:bg-green-200']">
                                <UserGroupIcon class="w-8 h-8" :class="darkMode ? 'text-green-400' : 'text-green-600'" />
                            </div>
                            <h3 :class="['font-heading text-xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Gestión de Grupos
                            </h3>
                            <p :class="['font-body text-sm mb-4', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                Crear y administrar grupos académicos
                            </p>
                            <div class="flex flex-col gap-2">
                                <Link :href="route('admin.grupos.index')" :class="['font-body px-4 py-2 rounded-lg font-medium transition-colors', darkMode ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white']">
                                    Ver Grupos
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Gestión de Áreas y Carreras -->
                    <div :class="['rounded-xl border hover:shadow-lg transition-all duration-300 group cursor-pointer', darkMode ? 'bg-gray-800 border-gray-600 hover:border-green-500' : 'bg-white border-gray-200 hover:border-green-500']">
                        <div class="p-8 text-center">
                            <div :class="['w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4', darkMode ? 'bg-green-500/20 group-hover:bg-green-500/30' : 'bg-green-100 group-hover:bg-green-200']">
                                <BookOpenIcon class="w-8 h-8" :class="darkMode ? 'text-green-400' : 'text-green-600'" />
                            </div>
                            <h3 :class="['font-heading text-xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Gestión de Áreas y Carreras
                            </h3>
                            <p :class="['font-body text-sm mb-4', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                Organizar carreras por áreas de estudio
                            </p>
                            <div class="flex flex-col gap-2">
                                <Link :href="route('admin.carreras.index')" :class="['font-body px-4 py-2 rounded-lg font-medium transition-colors', darkMode ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white']">
                                    Ver Áreas y Carreras
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Gestión de Materias -->
                    <div :class="['rounded-xl border hover:shadow-lg transition-all duration-300 group cursor-pointer', darkMode ? 'bg-gray-800 border-gray-600 hover:border-green-500' : 'bg-white border-gray-200 hover:border-green-500']">
                        <div class="p-8 text-center">
                            <div :class="['w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4', darkMode ? 'bg-green-500/20 group-hover:bg-green-500/30' : 'bg-green-100 group-hover:bg-green-200']">
                                <BookOpenIcon class="w-8 h-8" :class="darkMode ? 'text-green-400' : 'text-green-600'" />
                            </div>
                            <h3 :class="['font-heading text-xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Gestión de Materias
                            </h3>
                            <p :class="['font-body text-sm mb-4', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                Crear y administrar materias por grado
                            </p>
                            <div class="flex flex-col gap-2">
                                <Link :href="route('admin.materias.index')" :class="['font-body px-4 py-2 rounded-lg font-medium transition-colors', darkMode ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white']">
                                    Ver Materias
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Gestión de Edificios -->
                    <div :class="['rounded-xl border hover:shadow-lg transition-all duration-300 group cursor-pointer', darkMode ? 'bg-gray-800 border-gray-600 hover:border-green-500' : 'bg-white border-gray-200 hover:border-green-500']">
                        <div class="p-8 text-center">
                            <div :class="['w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4', darkMode ? 'bg-green-500/20 group-hover:bg-green-500/30' : 'bg-green-100 group-hover:bg-green-200']">
                                <BuildingOfficeIcon class="w-8 h-8" :class="darkMode ? 'text-green-400' : 'text-green-600'" />
                            </div>
                            <h3 :class="['font-heading text-xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Gestión de Edificios
                            </h3>
                            <p :class="['font-body text-sm mb-4', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                Crear y administrar edificios y sus aulas
                            </p>
                            <div class="flex flex-col gap-2">
                                <Link :href="route('admin.edificios.index')" :class="['font-body px-4 py-2 rounded-lg font-medium transition-colors', darkMode ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white']">
                                    Ver Edificios
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Gestión de Horarios -->
                    <div :class="['rounded-xl border hover:shadow-lg transition-all duration-300 group cursor-pointer', darkMode ? 'bg-gray-800 border-gray-600 hover:border-green-500' : 'bg-white border-gray-200 hover:border-green-500']">
                        <div class="p-8 text-center">
                            <div :class="['w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4', darkMode ? 'bg-green-500/20 group-hover:bg-green-500/30' : 'bg-green-100 group-hover:bg-green-200']">
                                <CalendarDaysIcon class="w-8 h-8" :class="darkMode ? 'text-green-400' : 'text-green-600'" />
                            </div>
                            <h3 :class="['font-heading text-xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Gestión de Horarios
                            </h3>
                            <p :class="['font-body text-sm mb-4', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                Crear y administrar horarios de clases visualmente
                            </p>
                            <div class="flex flex-col gap-2">
                                <Link :href="route('admin.horarios.index')" :class="['font-body px-4 py-2 rounded-lg font-medium transition-colors', darkMode ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white']">
                                    Ver Horarios
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Gestión de Documentos -->
                    <div :class="['rounded-xl border hover:shadow-lg transition-all duration-300 group cursor-pointer', darkMode ? 'bg-gray-800 border-gray-600 hover:border-green-500' : 'bg-white border-gray-200 hover:border-green-500']">
                        <div class="p-8 text-center">
                            <div :class="['w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4', darkMode ? 'bg-green-500/20 group-hover:bg-green-500/30' : 'bg-green-100 group-hover:bg-green-200']">
                                <DocumentTextIcon class="w-8 h-8" :class="darkMode ? 'text-green-400' : 'text-green-600'" />
                            </div>
                            <h3 :class="['font-heading text-xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Gestión de Documentos
                            </h3>
                            <p :class="['font-body text-sm mb-4', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                Verificar y aprobar solicitudes
                            </p>
                            <div class="flex flex-col gap-2">
                                <Link :href="route('admin.documentos.index')" :class="['font-body px-4 py-2 rounded-lg font-medium transition-colors', darkMode ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white']">
                                    Ver Documentos
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Gestión de Avisos y Tutorías -->
                    <div :class="['rounded-xl border hover:shadow-lg transition-all duration-300 group cursor-pointer', darkMode ? 'bg-gray-800 border-gray-600 hover:border-green-500' : 'bg-white border-gray-200 hover:border-green-500']">
                        <div class="p-8 text-center">
                            <div :class="['w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4', darkMode ? 'bg-green-500/20 group-hover:bg-green-500/30' : 'bg-green-100 group-hover:bg-green-200']">
                                <MegaphoneIcon class="w-8 h-8" :class="darkMode ? 'text-green-400' : 'text-green-600'" />
                            </div>
                            <h3 :class="['font-heading text-xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Avisos y Tutorías
                            </h3>
                            <p :class="['font-body text-sm mb-4', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                Crear avisos para tutores y maestros
                            </p>
                            <div class="flex flex-col gap-2">
                                <Link :href="route('admin.avisos.create')" :class="['font-body px-4 py-2 rounded-lg font-medium transition-colors', darkMode ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white']">
                                    Crear Aviso
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Gestión de Periodos Académicos -->
                    <div :class="['rounded-xl border hover:shadow-lg transition-all duration-300 group cursor-pointer', darkMode ? 'bg-gray-800 border-gray-600 hover:border-green-500' : 'bg-white border-gray-200 hover:border-green-500']">
                        <div class="p-8 text-center">
                            <div :class="['w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4', darkMode ? 'bg-green-500/20 group-hover:bg-green-500/30' : 'bg-green-100 group-hover:bg-green-200']">
                                <CalendarIcon class="w-8 h-8" :class="darkMode ? 'text-green-400' : 'text-green-600'" />
                            </div>
                            <h3 :class="['font-heading text-xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Periodos Académicos
                            </h3>
                            <p :class="['font-body text-sm mb-4', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                Crear y gestionar periodos académicos
                            </p>
                            <div class="flex flex-col gap-2">
                                <Link :href="route('admin.periodos.index')" :class="['font-body px-4 py-2 rounded-lg font-medium transition-colors', darkMode ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white']">
                                    Ver Periodos
                                </Link>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Footer -->
                <div class="mt-12 text-center">
                    <p :class="['font-body text-sm', darkMode ? 'text-gray-500' : 'text-gray-400']">
                        © 2024 UTM - Portal Administrativo - Desarrollado por Angel Noh y Mauricio Chale del 4-E
                    </p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
