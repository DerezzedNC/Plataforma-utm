<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, onMounted, watch, computed } from 'vue';
import axios from 'axios';
import { 
    XMarkIcon,
    PaperAirplaneIcon,
    ExclamationTriangleIcon
} from '@heroicons/vue/24/outline';

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Estados
const loading = ref(false);
const teachers = ref([]);
const loadingTeachers = ref(false);
const searchTeacherQuery = ref('');

// Formulario
const form = ref({
    title: '',
    content: '',
    priority: 'media',
    audience: 'all_tutors', // 'all_tutors' o 'select_teachers'
    selected_users: []
});

// Errores de validación
const errors = ref({});

// Computed para filtrar maestros
const filteredTeachers = computed(() => {
    if (!searchTeacherQuery.value) {
        return teachers.value;
    }
    const query = searchTeacherQuery.value.toLowerCase();
    return teachers.value.filter(teacher => 
        teacher.name.toLowerCase().includes(query) ||
        teacher.email.toLowerCase().includes(query)
    );
});

// Cargar maestros
const loadTeachers = async () => {
    try {
        loadingTeachers.value = true;
        const response = await axios.get('/admin/announcements/teachers/list');
        teachers.value = response.data;
    } catch (error) {
        console.error('Error cargando maestros:', error);
    } finally {
        loadingTeachers.value = false;
    }
};

// Watch para limpiar selección cuando cambia el audience
watch(() => form.value.audience, (newAudience) => {
    if (newAudience === 'all_tutors') {
        form.value.selected_users = [];
    }
});

// Función para alternar selección de maestro
const toggleTeacher = (teacherId) => {
    const index = form.value.selected_users.indexOf(teacherId);
    if (index > -1) {
        form.value.selected_users.splice(index, 1);
    } else {
        form.value.selected_users.push(teacherId);
    }
};

// Verificar si un maestro está seleccionado
const isTeacherSelected = (teacherId) => {
    return form.value.selected_users.includes(teacherId);
};

// Crear aviso
const createAnnouncement = async () => {
    // Validación básica
    errors.value = {};
    
    if (!form.value.title.trim()) {
        errors.value.title = 'El título es requerido';
        return;
    }
    
    if (!form.value.content.trim()) {
        errors.value.content = 'El contenido es requerido';
        return;
    }
    
    if (form.value.audience === 'select_teachers' && form.value.selected_users.length === 0) {
        errors.value.selected_users = 'Debes seleccionar al menos un maestro';
        return;
    }

    try {
        loading.value = true;
        
        const payload = {
            title: form.value.title,
            content: form.value.content,
            priority: form.value.priority,
            audience: form.value.audience,
        };

        // Solo incluir selected_users si el audience es select_teachers
        if (form.value.audience === 'select_teachers') {
            payload.selected_users = form.value.selected_users;
        }

        const response = await axios.post('/admin/announcements', payload);

        // Mostrar mensaje de éxito
        alert(`Aviso creado exitosamente. Enviado a ${response.data.recipients_count} destinatario(s).`);

        // Resetear formulario
        form.value = {
            title: '',
            content: '',
            priority: 'media',
            audience: 'all_tutors',
            selected_users: []
        };
        errors.value = {};

        // Opcional: redirigir o emitir evento
        // emit('created', response.data.announcement);
    } catch (error) {
        console.error('Error creando aviso:', error);
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors || {};
            alert('Error de validación: ' + (error.response.data.message || 'Por favor, revisa los campos'));
        } else {
            alert('Error al crear el aviso: ' + (error.response?.data?.message || error.message));
        }
    } finally {
        loading.value = false;
    }
};

// Cargar maestros al montar
onMounted(() => {
    loadTeachers();
});
</script>

<template>
    <Head title="Crear Aviso - Administrador UTM" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h1 :class="['font-heading text-3xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                            Crear Aviso
                        </h1>
                        <p :class="['font-body mt-2 text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                            Envía un aviso a tutores o maestros específicos
                        </p>
                    </div>
                    <Link 
                        :href="route('dashboard-admin')"
                        :class="['font-body inline-flex items-center px-4 py-2 rounded-lg font-medium transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                    >
                        <XMarkIcon class="w-5 h-5 mr-2" />
                        Cancelar
                    </Link>
                </div>

                <!-- Formulario -->
                <div :class="['rounded-2xl border shadow-lg overflow-hidden', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <div class="p-6">
                        <form @submit.prevent="createAnnouncement" class="space-y-6">
                            <!-- Título -->
                            <div>
                                <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                    Título del Aviso *
                                </label>
                                <input
                                    v-model="form.title"
                                    type="text"
                                    :class="['font-body w-full px-4 py-3 rounded-lg border transition-all focus:ring-2 focus:ring-green-500 focus:border-green-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-green-400' : 'bg-white border-gray-300 text-gray-900', errors.title && 'border-red-500']"
                                    placeholder="Ej: Reunión de tutores - Enero 2025"
                                    maxlength="255"
                                >
                                <p v-if="errors.title" class="mt-1 text-sm text-red-500">
                                    {{ errors.title }}
                                </p>
                            </div>

                            <!-- Contenido -->
                            <div>
                                <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                    Contenido del Aviso *
                                </label>
                                <textarea
                                    v-model="form.content"
                                    rows="6"
                                    :class="['font-body w-full px-4 py-3 rounded-lg border transition-all focus:ring-2 focus:ring-green-500 focus:border-green-500 resize-none', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-green-400' : 'bg-white border-gray-300 text-gray-900', errors.content && 'border-red-500']"
                                    placeholder="Escribe el contenido del aviso aquí..."
                                ></textarea>
                                <p v-if="errors.content" class="mt-1 text-sm text-red-500">
                                    {{ errors.content }}
                                </p>
                            </div>

                            <!-- Prioridad -->
                            <div>
                                <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                    Prioridad *
                                </label>
                                <select
                                    v-model="form.priority"
                                    :class="['font-body w-full px-4 py-3 rounded-lg border transition-all focus:ring-2 focus:ring-green-500 focus:border-green-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-green-400' : 'bg-white border-gray-300 text-gray-900']"
                                >
                                    <option value="baja">Baja</option>
                                    <option value="media">Media</option>
                                    <option value="alta">Alta</option>
                                </select>
                            </div>

                            <!-- Dirigido a -->
                            <div>
                                <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                    Dirigido a *
                                </label>
                                <select
                                    v-model="form.audience"
                                    :class="['font-body w-full px-4 py-3 rounded-lg border transition-all focus:ring-2 focus:ring-green-500 focus:border-green-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-green-400' : 'bg-white border-gray-300 text-gray-900']"
                                >
                                    <option value="all_tutors">Todos los Tutores</option>
                                    <option value="select_teachers">Seleccionar Maestros</option>
                                </select>
                            </div>

                            <!-- Selector de maestros (solo si selecciona "Seleccionar Maestros") -->
                            <div v-if="form.audience === 'select_teachers'">
                                <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                    Seleccionar Maestros *
                                    <span class="text-xs font-normal text-gray-500 ml-2">
                                        ({{ form.selected_users.length }} seleccionado{{ form.selected_users.length !== 1 ? 's' : '' }})
                                    </span>
                                </label>
                                
                                <!-- Buscador de maestros -->
                                <div class="mb-3">
                                    <input
                                        type="text"
                                        v-model="searchTeacherQuery"
                                        :class="['font-body w-full px-4 py-2 rounded-lg border transition-all focus:ring-2 focus:ring-green-500 focus:border-green-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-green-400' : 'bg-white border-gray-300 text-gray-900']"
                                        placeholder="Buscar maestro por nombre o email..."
                                    >
                                </div>

                                <!-- Lista de maestros -->
                                <div 
                                    :class="['border rounded-lg max-h-64 overflow-y-auto', darkMode ? 'border-gray-600 bg-gray-700' : 'border-gray-300 bg-gray-50']"
                                >
                                    <div v-if="loadingTeachers" class="p-4 text-center">
                                        <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2" :class="darkMode ? 'border-green-400' : 'border-green-600'"></div>
                                        <p :class="['mt-2 text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando maestros...</p>
                                    </div>
                                    
                                    <div v-else-if="filteredTeachers.length === 0" class="p-4 text-center">
                                        <p :class="['text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                            No se encontraron maestros
                                        </p>
                                    </div>
                                    
                                    <div v-else class="divide-y" :class="darkMode ? 'divide-gray-600' : 'divide-gray-200'">
                                        <label
                                            v-for="teacher in filteredTeachers"
                                            :key="teacher.id"
                                            :class="['flex items-center p-3 cursor-pointer transition-colors hover:bg-opacity-50', darkMode ? 'hover:bg-gray-600' : 'hover:bg-gray-100', isTeacherSelected(teacher.id) && (darkMode ? 'bg-green-900 bg-opacity-30' : 'bg-green-50')]"
                                        >
                                            <input
                                                type="checkbox"
                                                :checked="isTeacherSelected(teacher.id)"
                                                @change="toggleTeacher(teacher.id)"
                                                class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500"
                                                :class="darkMode ? 'bg-gray-600 border-gray-500' : ''"
                                            >
                                            <div class="ml-3 flex-1">
                                                <p :class="['text-sm font-medium', darkMode ? 'text-white' : 'text-gray-900']">
                                                    {{ teacher.name }}
                                                </p>
                                                <p :class="['text-xs', darkMode ? 'text-gray-400' : 'text-gray-500']">
                                                    {{ teacher.email }}
                                                </p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                
                                <p v-if="errors.selected_users" class="mt-1 text-sm text-red-500">
                                    {{ errors.selected_users }}
                                </p>
                            </div>

                            <!-- Información de destinatarios -->
                            <div 
                                v-if="form.audience === 'all_tutors'"
                                :class="['p-4 rounded-lg border', darkMode ? 'bg-blue-900 bg-opacity-30 border-blue-600' : 'bg-blue-50 border-blue-200']"
                            >
                                <div class="flex items-start">
                                    <ExclamationTriangleIcon class="w-5 h-5 mr-2 flex-shrink-0" :class="darkMode ? 'text-blue-400' : 'text-blue-600'" />
                                    <div>
                                        <p :class="['text-sm font-medium', darkMode ? 'text-blue-300' : 'text-blue-900']">
                                            Este aviso se enviará a todos los tutores asignados a grupos.
                                        </p>
                                        <p :class="['text-xs mt-1', darkMode ? 'text-blue-400' : 'text-blue-700']">
                                            Los tutores son maestros que tienen un grupo asignado como tutor.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Botones -->
                            <div class="flex justify-end space-x-4 pt-4 border-t" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                                <Link 
                                    :href="route('dashboard-admin')"
                                    :class="['font-body px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                                >
                                    Cancelar
                                </Link>
                                <button
                                    type="submit"
                                    :disabled="loading"
                                    :class="['font-body px-6 py-3 rounded-lg font-semibold transition-colors flex items-center gap-2', loading ? 'bg-gray-400 cursor-not-allowed text-white' : (darkMode ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-green-500 hover:bg-green-600 text-white')]"
                                >
                                    <PaperAirplaneIcon class="w-5 h-5" />
                                    {{ loading ? 'Publicando...' : 'Publicar Aviso' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

