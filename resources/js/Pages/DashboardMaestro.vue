<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, onMounted } from 'vue';
import axios from 'axios';
// Heroicons - Outline version
import { 
    BookOpenIcon,
    ChartBarIcon,
    UserGroupIcon,
    ChartBarSquareIcon,
    BellIcon,
    PaperAirplaneIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon
} from '@heroicons/vue/24/outline';

// Obtenemos el usuario autenticado
const props = defineProps({
    auth: Object,
});

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Estados para datos del tutor
const loading = ref(true);
const tutorData = ref(null);
const group = ref(null);
const announcements = ref([]);
const forwarding = ref({}); // Para rastrear qué avisos se están reenviando

// Cargar datos del dashboard del tutor
const loadTutorDashboard = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/teacher/tutor/dashboard');
        tutorData.value = response.data;
        group.value = response.data.group;
        announcements.value = response.data.unread_announcements || [];
    } catch (error) {
        console.error('Error cargando dashboard del tutor:', error);
        // Si no es tutor, simplemente no mostrar la sección
        group.value = null;
        announcements.value = [];
    } finally {
        loading.value = false;
    }
};

// Marcar aviso como leído
const markAsRead = async (announcementId) => {
    try {
        await axios.post(`/teacher/tutor/announcements/${announcementId}/read`);
        // Actualizar el estado local
        const announcement = announcements.value.find(a => a.id === announcementId);
        if (announcement) {
            announcement.is_read = true;
            announcement.read_at = new Date().toISOString();
        }
    } catch (error) {
        console.error('Error marcando aviso como leído:', error);
        alert('Error al marcar el aviso como leído');
    }
};

// Reenviar aviso al grupo
const forwardAnnouncement = async (announcementId) => {
    if (forwarding.value[announcementId]) {
        return; // Ya se está procesando
    }

    try {
        forwarding.value[announcementId] = true;
        const response = await axios.post(`/teacher/tutor/announcements/${announcementId}/forward`);
        
        // Actualizar el estado local
        const announcement = announcements.value.find(a => a.id === announcementId);
        if (announcement) {
            announcement.is_forwarded = true;
            announcement.forwarded_at = response.data.forwarded_at;
        }

        alert('Aviso reenviado exitosamente a tu grupo');
    } catch (error) {
        console.error('Error reenviando aviso:', error);
        const message = error.response?.data?.message || 'Error al reenviar el aviso';
        alert(message);
    } finally {
        forwarding.value[announcementId] = false;
    }
};

// Obtener clase de prioridad
const getPriorityClass = (priority) => {
    const classes = {
        alta: darkMode ? 'bg-red-900 bg-opacity-30 border-red-600 text-red-300' : 'bg-red-50 border-red-200 text-red-800',
        media: darkMode ? 'bg-yellow-900 bg-opacity-30 border-yellow-600 text-yellow-300' : 'bg-yellow-50 border-yellow-200 text-yellow-800',
        baja: darkMode ? 'bg-blue-900 bg-opacity-30 border-blue-600 text-blue-300' : 'bg-blue-50 border-blue-200 text-blue-800',
    };
    return classes[priority] || classes.media;
};

// Obtener icono de prioridad
const getPriorityIcon = (priority) => {
    if (priority === 'alta') {
        return ExclamationTriangleIcon;
    } else if (priority === 'media') {
        return InformationCircleIcon;
    }
    return InformationCircleIcon;
};

// Cargar datos al montar
onMounted(() => {
    loadTutorDashboard();
});
</script>

<template>
    <Head title="Portal Docente - UTM" />

    <AuthenticatedLayout>
        <!-- Contenido Principal -->
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                
                <!-- Mensaje de Bienvenida para Maestros -->
                <div class="mb-8 bg-gradient-to-r from-blue-50 to-blue-100 dark:from-gray-800 dark:to-gray-700 rounded-2xl p-8 border border-blue-200 dark:border-gray-600 transition-colors duration-300">
                    <div class="text-center">
                        <h1 class="font-heading text-4xl font-bold text-blue-800 dark:text-blue-400 mb-2 transition-colors duration-300">
                            Portal Docente UTM
                        </h1>
                        <p class="font-body text-2xl text-blue-600 dark:text-blue-300 transition-colors duration-300">
                            Profesor {{ auth.user.name }}
                        </p>
                        <p class="font-body text-lg text-gray-600 dark:text-gray-400 mt-2 transition-colors duration-300">
                            Sistema de gestión académica para docentes
                        </p>
                    </div>
                </div>

                <!-- Sección del Tutor (solo si es tutor) -->
                <div v-if="group" class="mb-8">
                    <div :class="['rounded-2xl border shadow-lg overflow-hidden', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                        <div class="p-6">
                            <h2 :class="['font-heading text-2xl font-bold mb-4 flex items-center', darkMode ? 'text-white' : 'text-gray-900']">
                                <UserGroupIcon class="w-6 h-6 mr-2" :class="darkMode ? 'text-blue-400' : 'text-blue-600'" />
                                Tu Grupo Tutorado
                            </h2>
                            
                            <div class="flex items-center justify-between">
                                <div>
                                    <p :class="['font-body text-3xl font-bold mb-1', darkMode ? 'text-white' : 'text-gray-900']">
                                        {{ group.group_name }}
                                    </p>
                                    <p :class="['font-body text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                        {{ group.carrera }} - {{ group.students_count }} estudiante{{ group.students_count !== 1 ? 's' : '' }}
                                    </p>
                                </div>
                                <Link 
                                    :href="route('maestros.seleccionar-grupo')"
                                    :class="['font-body px-6 py-3 rounded-lg font-semibold transition-colors flex items-center gap-2', darkMode ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                                >
                                    <UserGroupIcon class="w-5 h-5" />
                                    Ver Alumnos
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bandeja de Avisos Institucionales (solo si es tutor) -->
                <div v-if="group" class="mb-8">
                    <div :class="['rounded-2xl border shadow-lg overflow-hidden', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <h2 :class="['font-heading text-2xl font-bold flex items-center', darkMode ? 'text-white' : 'text-gray-900']">
                                <BellIcon class="w-6 h-6 mr-2" :class="darkMode ? 'text-blue-400' : 'text-blue-600'" />
                                Bandeja de Avisos Institucionales
                                <span v-if="announcements.length > 0" :class="['ml-3 px-3 py-1 rounded-full text-sm font-semibold', darkMode ? 'bg-blue-600 text-white' : 'bg-blue-100 text-blue-800']">
                                    {{ announcements.length }} nuevo{{ announcements.length !== 1 ? 's' : '' }}
                                </span>
                            </h2>
                        </div>

                        <div v-if="loading" class="p-12 text-center">
                            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-blue-400' : 'border-blue-600'"></div>
                            <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando avisos...</p>
                        </div>

                        <div v-else-if="announcements.length === 0" class="p-12 text-center">
                            <BellIcon class="w-24 h-24 mx-auto mb-4 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" />
                            <p :class="['font-body text-lg', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                No tienes avisos nuevos
                            </p>
                        </div>

                        <div v-else class="divide-y" :class="darkMode ? 'divide-gray-700' : 'divide-gray-200'">
                            <div
                                v-for="announcement in announcements"
                                :key="announcement.id"
                                :class="['p-6 hover:bg-opacity-50 transition-colors', darkMode ? 'hover:bg-gray-700' : 'hover:bg-gray-50']"
                            >
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <component 
                                                :is="getPriorityIcon(announcement.priority)" 
                                                class="w-5 h-5 flex-shrink-0"
                                                :class="announcement.priority === 'alta' ? (darkMode ? 'text-red-400' : 'text-red-600') : announcement.priority === 'media' ? (darkMode ? 'text-yellow-400' : 'text-yellow-600') : (darkMode ? 'text-blue-400' : 'text-blue-600')"
                                            />
                                            <h3 :class="['font-heading text-xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                                {{ announcement.title }}
                                            </h3>
                                            <span :class="['px-2 py-1 rounded text-xs font-semibold border', getPriorityClass(announcement.priority)]">
                                                {{ announcement.priority === 'alta' ? 'Alta' : announcement.priority === 'media' ? 'Media' : 'Baja' }}
                                            </span>
                                        </div>
                                        <p :class="['font-body text-sm mb-2', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                            De: {{ announcement.sender?.name || 'Administración' }}
                                        </p>
                                        <p :class="['font-body text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                            {{ new Date(announcement.created_at).toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' }) }}
                                        </p>
                                    </div>
                                </div>

                                <div :class="['p-4 rounded-lg mb-4', darkMode ? 'bg-gray-700' : 'bg-gray-50']">
                                    <p :class="['font-body whitespace-pre-wrap', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                        {{ announcement.content }}
                                    </p>
                                </div>

                                <div class="flex items-center justify-between">
                                    <button
                                        v-if="!announcement.is_read"
                                        @click="markAsRead(announcement.id)"
                                        :class="['font-body px-4 py-2 rounded-lg text-sm font-medium transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                                    >
                                        Marcar como leído
                                    </button>
                                    <span v-else :class="['font-body text-sm flex items-center gap-2', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                        <CheckCircleIcon class="w-5 h-5 text-green-500" />
                                        Leído
                                    </span>

                                    <button
                                        @click="forwardAnnouncement(announcement.id)"
                                        :disabled="announcement.is_forwarded || forwarding[announcement.id]"
                                        :class="[
                                            'font-body px-6 py-2 rounded-lg text-sm font-semibold transition-colors flex items-center gap-2',
                                            announcement.is_forwarded 
                                                ? (darkMode ? 'bg-green-800 text-green-300 cursor-not-allowed' : 'bg-green-100 text-green-800 cursor-not-allowed')
                                                : forwarding[announcement.id]
                                                ? (darkMode ? 'bg-gray-600 text-gray-400 cursor-not-allowed' : 'bg-gray-300 text-gray-600 cursor-not-allowed')
                                                : (darkMode ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white')
                                        ]"
                                    >
                                        <PaperAirplaneIcon v-if="!announcement.is_forwarded" class="w-5 h-5" />
                                        <CheckCircleIcon v-else class="w-5 h-5" />
                                        {{ announcement.is_forwarded ? 'Reenviado ✅' : (forwarding[announcement.id] ? 'Reenviando...' : 'Reenviar a mi Grupo') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grid de Servicios para Maestros -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-6">
                    
                    <!-- Gestión de Cursos (Internos y Externos unificados) -->
                    <Link :href="route('maestros.cursos.index')" :class="['rounded-xl shadow-lg border hover:shadow-xl transition-all duration-300 cursor-pointer group', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-blue-100']">
                        <div class="p-8 text-center">
                            <div :class="['w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:bg-blue-200 transition-colors', darkMode ? 'bg-blue-900 group-hover:bg-blue-800' : 'bg-blue-100']">
                                <BookOpenIcon :class="['w-8 h-8', darkMode ? 'text-blue-400' : 'text-blue-600']" />
                            </div>
                            <h3 :class="['font-heading text-xl font-bold mb-2 transition-colors duration-300', darkMode ? 'text-white' : 'text-gray-800']">Gestión de Cursos</h3>
                            <p :class="['font-body transition-colors duration-300', darkMode ? 'text-gray-400' : 'text-gray-600']">Crear y administrar cursos internos y externos</p>
                        </div>
                    </Link>

                    <!-- Gestión de Calificaciones -->
                    <div :class="['rounded-xl shadow-lg border hover:shadow-xl transition-all duration-300 cursor-pointer group', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-blue-100']">
                        <div class="p-8 text-center">
                            <div :class="['w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:bg-blue-200 transition-colors', darkMode ? 'bg-blue-900 group-hover:bg-blue-800' : 'bg-blue-100']">
                                <ChartBarIcon :class="['w-8 h-8', darkMode ? 'text-blue-400' : 'text-blue-600']" />
                            </div>
                            <h3 :class="['font-heading text-xl font-bold mb-2 transition-colors duration-300', darkMode ? 'text-white' : 'text-gray-800']">Gestión de Calificaciones</h3>
                            <p :class="['font-body transition-colors duration-300', darkMode ? 'text-gray-400' : 'text-gray-600']">Capturar y administrar calificaciones</p>
                        </div>
                    </div>

                    <!-- Lista de Alumnos -->
                    <Link :href="route('maestros.seleccionar-grupo')" :class="['rounded-xl shadow-lg border hover:shadow-xl transition-all duration-300 cursor-pointer group', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-blue-100']">
                        <div class="p-8 text-center">
                            <div :class="['w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:bg-blue-200 transition-colors', darkMode ? 'bg-blue-900 group-hover:bg-blue-800' : 'bg-blue-100']">
                                <UserGroupIcon :class="['w-8 h-8', darkMode ? 'text-blue-400' : 'text-blue-600']" />
                            </div>
                            <h3 :class="['font-heading text-xl font-bold mb-2 transition-colors duration-300', darkMode ? 'text-white' : 'text-gray-800']">Lista de Alumnos</h3>
                            <p :class="['font-body transition-colors duration-300', darkMode ? 'text-gray-400' : 'text-gray-600']">Ver y gestionar información de estudiantes</p>
                        </div>
                    </Link>

                </div>

                <!-- Información adicional para maestros -->
                <div class="mt-12 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Panel de estadísticas rápidas -->
                    <div :class="['rounded-xl shadow-lg border p-6', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-blue-100']">
                        <h3 :class="['font-heading text-xl font-bold mb-4 flex items-center', darkMode ? 'text-white' : 'text-gray-800']">
                            <ChartBarSquareIcon :class="['w-6 h-6 mr-2', darkMode ? 'text-blue-400' : 'text-blue-600']" />
                            Estadísticas Rápidas
                        </h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span :class="['font-body', darkMode ? 'text-gray-300' : 'text-gray-600']">Cursos Activos:</span>
                                <span :class="['font-body font-bold', darkMode ? 'text-blue-400' : 'text-blue-600']">8</span>
                            </div>
                            <div class="flex justify-between">
                                <span :class="['font-body', darkMode ? 'text-gray-300' : 'text-gray-600']">Estudiantes Total:</span>
                                <span :class="['font-body font-bold', darkMode ? 'text-blue-400' : 'text-blue-600']">245</span>
                            </div>
                            <div class="flex justify-between">
                                <span :class="['font-body', darkMode ? 'text-gray-300' : 'text-gray-600']">Calificaciones Pendientes:</span>
                                <span :class="['font-body font-bold', darkMode ? 'text-orange-400' : 'text-orange-600']">12</span>
                            </div>
                        </div>
                    </div>

                    <!-- Panel de notificaciones -->
                    <div :class="['rounded-xl shadow-lg border p-6', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-blue-100']">
                        <h3 :class="['font-heading text-xl font-bold mb-4 flex items-center', darkMode ? 'text-white' : 'text-gray-800']">
                            <BellIcon :class="['w-6 h-6 mr-2', darkMode ? 'text-blue-400' : 'text-blue-600']" />
                            Notificaciones Recientes
                        </h3>
                        <div class="space-y-3">
                            <div :class="['p-3 rounded-lg', darkMode ? 'bg-gray-700' : 'bg-blue-50']">
                                <p :class="['font-body text-sm', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                    Recordatorio: Fecha límite para captura de calificaciones - 15 Oct
                                </p>
                            </div>
                            <div :class="['p-3 rounded-lg', darkMode ? 'bg-gray-700' : 'bg-green-50']">
                                <p :class="['font-body text-sm', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                    Nuevo curso externo aprobado para publicación
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer del Dashboard -->
                <div class="mt-12 text-center">
                    <p class="font-body text-gray-500 dark:text-gray-400 transition-colors duration-300">
                        © 2024 UTM - Portal Docente - Desarrollado por Angel Noh y Mauricio Chale del 4-E
                    </p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
