<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});

const showAbout = ref(false);
</script>

<template>
    <Head title="Plataforma UTM" />
    
    <!-- Navbar -->
    <nav class="bg-white shadow-sm border-b border-green-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <!-- Logo -->
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-600 rounded-lg flex items-center justify-center text-white font-bold text-xl">
                        UTM
                    </div>
                    <span class="ml-4 text-2xl font-bold text-green-800">Universidad Técnica Metropolitana</span>
                </div>
                
                <!-- Navigation Links -->
                <div class="hidden md:flex items-center space-x-8">
                    <button 
                        @click="showAbout = !showAbout"
                        class="text-gray-700 hover:text-green-600 transition-colors duration-200 text-lg font-medium"
                    >
                        Nosotros
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- About Modal/Dropdown -->
    <div v-if="showAbout" class="bg-green-50 border-b border-green-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="bg-white rounded-lg shadow-sm p-8 border border-green-100">
                <h3 class="text-2xl font-display font-bold text-green-800 mb-4">Acerca de nosotros</h3>
                <p class="text-gray-700 text-lg leading-relaxed">
                    Este es un sitio web escolar para la Universidad Técnica Metropolitana, 
                    desarrollada por los alumnos <strong>Angel Noh</strong> y <strong>Mauricio Chale</strong> del 4-E.
                </p>
            </div>
        </div>
    </div>

    <!-- Hero Section -->
    <div class="min-h-screen bg-gradient-to-br from-green-50 to-green-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center min-h-screen py-24">
                
                <!-- Left Side - Content -->
                <div class="space-y-12">
                    <div class="space-y-6">
                        <h1 class="text-6xl lg:text-7xl font-display font-bold text-gray-900 leading-tight">
                            Plataforma
                            <span class="text-green-600 block">UTM</span>
                        </h1>
                        
                        <p class="text-2xl text-gray-600 max-w-2xl leading-relaxed">
                            Mantén informados a tus alumnos sobre sus calificaciones, actividades 
                            académicas, asistencias y más desde su portal institucional.
                        </p>
                    </div>
                    
                    <!-- CTA Button -->
                    <div class="flex flex-col sm:flex-row gap-6">
                        <Link
                            v-if="canLogin && !$page.props.auth.user"
                            :href="route('login')"
                            class="bg-green-600 hover:bg-green-700 text-white px-12 py-5 rounded-xl text-xl font-semibold transition-all duration-200 text-center shadow-lg hover:shadow-xl transform hover:-translate-y-1"
                        >
                            Ingresa a UTM
                        </Link>
                        <Link
                            v-else-if="$page.props.auth.user"
                            :href="route('dashboard')"
                            class="bg-green-700 hover:bg-green-800 text-white px-12 py-5 rounded-xl text-xl font-semibold transition-all duration-200 text-center shadow-lg hover:shadow-xl transform hover:-translate-y-1"
                        >
                            Ir al Dashboard
                        </Link>
                    </div>
                    
                    <!-- App Download Buttons -->
                    <div class="space-y-4">
                        <p class="text-lg text-gray-500 font-medium">Próximamente disponible en:</p>
                        <div class="flex flex-col sm:flex-row gap-4">
                            <!-- App Store Button -->
                            <div class="flex items-center bg-black text-white px-6 py-3 rounded-xl hover:bg-gray-800 transition-colors duration-200 cursor-pointer shadow-md">
                                <svg class="w-8 h-8 mr-3" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
                                </svg>
                                <div>
                                    <div class="text-sm">Disponible en</div>
                                    <div class="text-lg font-semibold">App Store</div>
                                </div>
                            </div>
                            
                            <!-- Google Play Button -->
                            <div class="flex items-center bg-black text-white px-6 py-3 rounded-xl hover:bg-gray-800 transition-colors duration-200 cursor-pointer shadow-md">
                                <svg class="w-8 h-8 mr-3" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.6 3,21.09 3,20.5M16.81,15.12L6.05,21.34L14.54,12.85L16.81,15.12M20.16,10.81C20.5,11.08 20.75,11.5 20.75,12C20.75,12.5 20.53,12.9 20.18,13.18L17.89,14.5L15.39,12L17.89,9.5L20.16,10.81M6.05,2.66L16.81,8.88L14.54,11.15L6.05,2.66Z"/>
                                </svg>
                                <div>
                                    <div class="text-sm">Disponible en</div>
                                    <div class="text-lg font-semibold">Google Play</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Side - Dashboard Preview -->
                <div class="relative">
                    <!-- Desktop Mockup -->
                    <div class="relative bg-gray-900 rounded-2xl p-6 shadow-2xl transform rotate-2 hover:rotate-0 transition-transform duration-300">
                        <div class="bg-gray-800 rounded-t-xl p-3 flex items-center space-x-3">
                            <div class="w-4 h-4 bg-red-500 rounded-full"></div>
                            <div class="w-4 h-4 bg-yellow-500 rounded-full"></div>
                            <div class="w-4 h-4 bg-green-500 rounded-full"></div>
                        </div>
                        <div class="bg-white rounded-b-xl overflow-hidden">
                            <!-- Simulated Dashboard Content -->
                            <div class="p-8 space-y-6">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-2xl font-bold text-green-800">Dashboard UTM</h3>
                                    <div class="w-12 h-12 bg-green-600 rounded-full flex items-center justify-center">
                                        <span class="text-white font-bold text-lg">A</span>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-6">
                                    <div class="bg-green-50 p-6 rounded-xl border border-green-200">
                                        <div class="text-3xl font-bold text-green-600">85%</div>
                                        <div class="text-lg text-gray-600 font-medium">Asistencia</div>
                                    </div>
                                    <div class="bg-green-50 p-6 rounded-xl border border-green-200">
                                        <div class="text-3xl font-bold text-green-600">8.5</div>
                                        <div class="text-lg text-gray-600 font-medium">Promedio</div>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div class="h-3 bg-green-200 rounded-full"></div>
                                    <div class="h-3 bg-green-200 rounded-full w-3/4"></div>
                                    <div class="h-3 bg-green-200 rounded-full w-1/2"></div>
                                </div>
                                <div class="bg-green-600 text-white p-4 rounded-xl text-center font-semibold">
                                    Acceso completo a calificaciones
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mobile Mockup -->
                    <div class="absolute -bottom-8 -right-8 w-40 h-80 bg-gray-900 rounded-3xl p-2 shadow-xl transform -rotate-6 hover:rotate-0 transition-transform duration-300">
                        <div class="bg-white rounded-3xl h-full overflow-hidden">
                            <div class="p-4 space-y-3">
                                <div class="h-3 bg-green-600 rounded-full w-3/4 mx-auto"></div>
                                <div class="space-y-2">
                                    <div class="h-2 bg-gray-200 rounded-full"></div>
                                    <div class="h-2 bg-gray-200 rounded-full w-2/3"></div>
                                    <div class="h-2 bg-gray-200 rounded-full w-1/2"></div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                        <div class="w-6 h-6 bg-green-600 rounded"></div>
                                    </div>
                                    <div class="h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                        <div class="w-6 h-6 bg-green-600 rounded"></div>
                                    </div>
                                </div>
                                <div class="bg-green-600 h-8 rounded-lg"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>