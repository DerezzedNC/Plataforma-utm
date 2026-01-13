<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();
</script>

<template>
    <Head title="Cursos Extra - UTM" />

    <AuthenticatedLayout>
        <!-- Contenido Principal -->
        <div class="min-h-screen">
            <!-- Header con título y descripción -->
            <div class="py-8">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="text-center mb-8">
                        <h1 :class="['text-3xl font-bold mb-4', darkMode ? 'text-white' : 'text-gray-900']">
                            Cursos Extra
                        </h1>
                        <p :class="['text-lg', darkMode ? 'text-gray-400' : 'text-gray-600']">
                            Amplía tus conocimientos con cursos adicionales
                        </p>
                    </div>

                    <!-- Contenedor Principal con Sidebar y Contenido -->
                    <div class="flex gap-8">
                        <!-- Sidebar con navegación -->
                        <div class="w-80 flex-shrink-0">
                            <div :class="['rounded-lg shadow-md border', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                                <nav class="p-6 space-y-3">
                                    <!-- Dashboard -->
                                    <Link :href="route('dashboard')" :class="['flex items-center px-4 py-3 text-base font-medium rounded-md transition-colors', darkMode ? 'text-gray-300 hover:bg-gray-700 hover:text-white' : 'text-gray-700 hover:bg-gray-100']">
                                        <svg class="w-6 h-6 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v6H8V5z"></path>
                                        </svg>
                                        Dashboard
                                    </Link>

                                    <!-- Justificaciones -->
                                    <a href="#" :class="['flex items-center px-4 py-3 text-base font-medium rounded-md transition-colors', darkMode ? 'text-gray-300 hover:bg-gray-700 hover:text-white' : 'text-gray-700 hover:bg-gray-100']">
                                        <svg class="w-6 h-6 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        Justificaciones
                                    </a>

                                    <!-- Cursos Extra - Activo -->
                                    <Link :href="route('cursos-extra')" :class="['flex items-center px-4 py-3 text-base font-medium rounded-md border-l-4', route().current('cursos-extra') ? (darkMode ? 'bg-green-900 text-green-400 border-green-400' : 'bg-green-100 text-green-800 border-green-500') : (darkMode ? 'text-gray-300 hover:bg-gray-700 hover:text-white' : 'text-gray-700 hover:bg-gray-100')]">
                                        <svg class="w-6 h-6 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                        </svg>
                                        Cursos Extra
                                    </Link>

                                    <!-- Horario -->
                                    <Link :href="route('consultar-horario')" :class="['flex items-center px-4 py-3 text-base font-medium rounded-md transition-colors', route().current('consultar-horario') ? (darkMode ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700') : (darkMode ? 'text-gray-300 hover:bg-gray-700 hover:text-white' : 'text-gray-700 hover:bg-gray-100')]">
                                        <svg class="w-6 h-6 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Horario
                                    </Link>

                                    <!-- Reinscripción -->
                                    <a href="#" :class="['flex items-center px-4 py-3 text-base font-medium rounded-md transition-colors', darkMode ? 'text-gray-300 hover:bg-gray-700 hover:text-white' : 'text-gray-700 hover:bg-gray-100']">
                                        <svg class="w-6 h-6 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                        Reinscripción
                                    </a>

                                    <!-- Historial Académico -->
                                    <Link :href="route('historial-academico')" :class="['flex items-center px-4 py-3 text-base font-medium rounded-md transition-colors', route().current('historial-academico') ? (darkMode ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700') : (darkMode ? 'text-gray-300 hover:bg-gray-700 hover:text-white' : 'text-gray-700 hover:bg-gray-100')]">
                                        <svg class="w-6 h-6 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                        Historial Académico
                                    </Link>

                                    <!-- Procesos Administrativos -->
                                    <Link :href="route('procesos-administrativos')" :class="['flex items-center px-4 py-3 text-base font-medium rounded-md transition-colors', route().current('procesos-administrativos') ? (darkMode ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700') : (darkMode ? 'text-gray-300 hover:bg-gray-700 hover:text-white' : 'text-gray-700 hover:bg-gray-100')]">
                                        <svg class="w-6 h-6 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        Procesos Administrativos
                                    </Link>
                                </nav>

                                <!-- Footer del sidebar -->
                                <div class="border-t border-gray-200 dark:border-gray-600 p-6">
                                    <p :class="['text-sm text-center', darkMode ? 'text-gray-400' : 'text-gray-500']">
                                        © 2024 UTM<br>
                                        Todos los derechos reservados
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Contenido Principal -->
                        <div class="flex-1">
                            <!-- Tarjetas de tipos de cursos -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                                
                                <!-- Cursos Internos -->
                                <Link :href="route('cursos.index') + '?tipo=interno'" :class="['rounded-2xl shadow-xl border hover:shadow-2xl transition-all duration-300 cursor-pointer group p-12', darkMode ? 'bg-gray-800 border-gray-600 hover:border-green-500' : 'bg-white border-gray-200 hover:border-green-400']">
                                    <div class="text-center">
                                        <!-- Icono de cursos internos -->
                                        <div :class="['w-32 h-32 rounded-full flex items-center justify-center mx-auto mb-8 group-hover:scale-110 transition-transform duration-300', darkMode ? 'bg-green-900' : 'bg-green-100']">
                                            <svg :class="['w-16 h-16', darkMode ? 'text-green-400' : 'text-green-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H3m2 0h-2m2 0V9a2 2 0 012-2h2a2 2 0 012 2v12M7 3h10M9 21v-4a2 2 0 012-2h2a2 2 0 012 2v4M9 9h6M9 13h6"></path>
                                            </svg>
                                        </div>
                                        
                                        <h3 :class="['text-4xl font-bold mb-6 transition-colors duration-300', darkMode ? 'text-white' : 'text-gray-800']">
                                            Cursos Internos
                                        </h3>
                                        <p :class="['text-xl mb-8 transition-colors duration-300', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                            Cursos impartidos por la UTM
                                        </p>
                                        
                                        <!-- Lista de características -->
                                        <div class="space-y-6">
                                            <div :class="['flex items-center justify-start text-lg', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                <svg class="w-8 h-8 text-green-500 mr-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                </svg>
                                                Certificación UTM
                                            </div>
                                            <div :class="['flex items-center justify-start text-lg', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                <svg class="w-8 h-8 text-green-500 mr-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                </svg>
                                                Profesores especializados
                                            </div>
                                            <div :class="['flex items-center justify-start text-lg', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                <svg class="w-8 h-8 text-green-500 mr-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                </svg>
                                                Instalaciones de la universidad
                                            </div>
                                        </div>

                                        <!-- Botón de acción -->
                                        <div class="mt-10">
                                            <div :class="['w-full py-4 px-8 rounded-xl font-bold text-lg transition-all duration-300 transform group-hover:scale-105', darkMode ? 'bg-green-600 group-hover:bg-green-700 text-white' : 'bg-green-500 group-hover:bg-green-600 text-white']">
                                                Ver Cursos Internos
                                            </div>
                                        </div>
                                    </div>
                                </Link>

                                <!-- Cursos Externos -->
                                <Link :href="route('cursos.index') + '?tipo=externo'" :class="['rounded-2xl shadow-xl border hover:shadow-2xl transition-all duration-300 cursor-pointer group p-12', darkMode ? 'bg-gray-800 border-gray-600 hover:border-green-500' : 'bg-white border-gray-200 hover:border-green-400']">
                                    <div class="text-center">
                                        <!-- Icono de cursos externos -->
                                        <div :class="['w-32 h-32 rounded-full flex items-center justify-center mx-auto mb-8 group-hover:scale-110 transition-transform duration-300', darkMode ? 'bg-green-900' : 'bg-green-100']">
                                            <svg :class="['w-16 h-16', darkMode ? 'text-green-400' : 'text-green-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9v-9m0-9v9m0 9c4.97 0 9-4.03 9-9s-4.03-9-9-9"></path>
                                            </svg>
                                        </div>
                                        
                                        <h3 :class="['text-4xl font-bold mb-6 transition-colors duration-300', darkMode ? 'text-white' : 'text-gray-800']">
                                            Cursos Externos
                                        </h3>
                                        <p :class="['text-xl mb-8 transition-colors duration-300', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                            Cursos fuera de UTM o en línea
                                        </p>
                                        
                                        <!-- Lista de características -->
                                        <div class="space-y-6">
                                            <div :class="['flex items-center justify-start text-lg', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                <svg class="w-8 h-8 text-green-500 mr-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                </svg>
                                                Plataformas reconocidas
                                            </div>
                                            <div :class="['flex items-center justify-start text-lg', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                <svg class="w-8 h-8 text-green-500 mr-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                </svg>
                                                Horarios flexibles
                                            </div>
                                            <div :class="['flex items-center justify-start text-lg', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                <svg class="w-8 h-8 text-green-500 mr-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                </svg>
                                                Certificaciones internacionales
                                            </div>
                                        </div>

                                        <!-- Botón de acción -->
                                        <div class="mt-10">
                                            <div :class="['w-full py-4 px-8 rounded-xl font-bold text-lg transition-all duration-300 transform group-hover:scale-105', darkMode ? 'bg-green-600 group-hover:bg-green-700 text-white' : 'bg-green-500 group-hover:bg-green-600 text-white']">
                                                Ver Cursos Externos
                                            </div>
                                        </div>
                                    </div>
                                </Link>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>