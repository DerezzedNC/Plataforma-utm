<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import MultiSelect from '@/Components/Form/MultiSelect.vue';
// Heroicons - Outline version
import { 
    PlusIcon, 
    MagnifyingGlassIcon, 
    UserGroupIcon, 
    PencilIcon, 
    TrashIcon, 
    XMarkIcon,
    AcademicCapIcon
} from '@heroicons/vue/24/outline';

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Estados
const teachers = ref([]);
const loading = ref(true);
const showAddModal = ref(false);
const showEditModal = ref(false);
const selectedTeacher = ref(null);
const searchQuery = ref('');

// Datos de áreas y carreras
const areas = ref([]);
const careers = ref([]);
const careersByArea = ref({});
const availableGroups = ref([]);
const loadingGroups = ref(false);

// Computed para carreras filtradas por sectores seleccionados (crear)
const filteredCareersForNew = computed(() => {
    if (!newTeacher.value.area_ids || newTeacher.value.area_ids.length === 0) {
        return []; // Si no hay sectores seleccionados, no mostrar carreras
    }
    return careers.value.filter(career => 
        newTeacher.value.area_ids.includes(career.area_id)
    );
});

// Computed para carreras filtradas por sectores seleccionados (editar)
const filteredCareersForEdit = computed(() => {
    if (!editTeacher.value.area_ids || editTeacher.value.area_ids.length === 0) {
        return []; // Si no hay sectores seleccionados, no mostrar carreras
    }
    return careers.value.filter(career => 
        editTeacher.value.area_ids.includes(career.area_id)
    );
});

// Formulario para agregar maestro
const newTeacher = ref({
    name: '',
    apellido_paterno: '',
    apellido_materno: '',
    email: '',
    password: '',
    password_confirmation: '',
    area_ids: [],
    career_ids: [],
    group_ids: [],
});

// Formulario para editar maestro
const editTeacher = ref({
    id: null,
    name: '',
    apellido_paterno: '',
    apellido_materno: '',
    email: '',
    area_ids: [],
    career_ids: [],
    group_ids: [],
});

// Función para normalizar texto para correo (eliminar acentos, espacios, etc.)
const normalizeForEmail = (text) => {
    if (!text) return '';
    return text
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // Eliminar acentos
        .replace(/ñ/g, 'n')
        .replace(/ü/g, 'u')
        .replace(/[^a-z0-9]/g, ''); // Eliminar caracteres especiales
};

// Watch para generar correo automáticamente cuando se ingresen apellido paterno y nombre
watch([() => newTeacher.value.apellido_paterno, () => newTeacher.value.name], ([apellidoPaterno, nombre]) => {
    if (apellidoPaterno && apellidoPaterno.trim() !== '' && nombre && nombre.trim() !== '') {
        // Generar correo automáticamente: {apellido_paterno}.{nombre}@utmetropolitana.edu.mx
        // Usar solo el primer nombre si hay múltiples nombres
        const primerNombre = nombre.trim().split(' ')[0];
        const apellidoNormalizado = normalizeForEmail(apellidoPaterno);
        const nombreNormalizado = normalizeForEmail(primerNombre);
        newTeacher.value.email = `${apellidoNormalizado}.${nombreNormalizado}@utmetropolitana.edu.mx`;
    } else {
        newTeacher.value.email = '';
    }
});

// Cargar áreas y carreras
const loadAreas = async () => {
    try {
        const response = await axios.get('/admin/teachers/areas/list');
        areas.value = response.data;
    } catch (error) {
        console.error('Error cargando áreas:', error);
    }
};

const loadCareers = async () => {
    try {
        const response = await axios.get('/admin/teachers/careers/list');
        careers.value = response.data;
        // Organizar carreras por área
        careersByArea.value = {};
        response.data.forEach(career => {
            if (!careersByArea.value[career.area_id]) {
                careersByArea.value[career.area_id] = [];
            }
            careersByArea.value[career.area_id].push(career);
        });
    } catch (error) {
        console.error('Error cargando carreras:', error);
    }
};

// Cargar maestros
const loadTeachers = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/admin/teachers');
        teachers.value = response.data;
    } catch (error) {
        console.error('Error cargando maestros:', error);
        if (error.response) {
            console.error('Respuesta del servidor:', error.response.status, error.response.data);
        }
    } finally {
        loading.value = false;
    }
};

// Agregar nuevo maestro
const addTeacher = async () => {
    try {
        const fullName = `${newTeacher.value.apellido_paterno} ${newTeacher.value.apellido_materno} ${newTeacher.value.name}`.trim();
        
        // El email se genera automáticamente en el backend usando apellido_paterno y nombre
        const response = await axios.post('/admin/teachers', {
            name: fullName,
            apellido_paterno: newTeacher.value.apellido_paterno,
            apellido_materno: newTeacher.value.apellido_materno || '',
            password: newTeacher.value.password,
            password_confirmation: newTeacher.value.password_confirmation,
            area_ids: newTeacher.value.area_ids || [],
            career_ids: newTeacher.value.career_ids || [],
            group_ids: newTeacher.value.group_ids || [],
            // El email se genera automáticamente en el backend
        });

        teachers.value.push(response.data);
        showAddModal.value = false;
        resetForm();
        await loadTeachers(); // Recargar la lista
    } catch (error) {
        console.error('Error agregando maestro:', error);
        if (error.response) {
            console.error('Respuesta del servidor:', error.response.status, error.response.data);
            const errorMessage = error.response.data?.message || 
                                (error.response.data?.errors ? JSON.stringify(error.response.data.errors) : 'Error desconocido');
            alert('Error al agregar maestro: ' + errorMessage);
        } else {
            alert('Error al agregar maestro: ' + (error.message || 'Error desconocido'));
        }
    }
};

// Editar maestro
const openEditModal = async (teacher) => {
    selectedTeacher.value = teacher;
    // Separar nombre completo en partes
    const nameParts = teacher.name.split(' ');
    editTeacher.value = {
        id: teacher.id,
        apellido_paterno: nameParts[0] || '',
        apellido_materno: nameParts[1] || '',
        name: nameParts.slice(2).join(' ') || '',
        email: teacher.email,
        area_ids: teacher.areas ? teacher.areas.map(a => a.id) : [],
        career_ids: teacher.careers ? teacher.careers.map(c => c.id) : [],
        group_ids: teacher.tutor_groups ? teacher.tutor_groups.map(g => g.id) : [],
    };
    showEditModal.value = true;
    // Cargar grupos disponibles después de un pequeño delay para que se carguen las carreras
    await new Promise(resolve => setTimeout(resolve, 100));
    await loadAvailableGroups('edit');
};

const updateTeacher = async () => {
    try {
        const fullName = `${editTeacher.value.apellido_paterno} ${editTeacher.value.apellido_materno} ${editTeacher.value.name}`.trim();
        
        const response = await axios.put(`/admin/teachers/${editTeacher.value.id}`, {
            name: fullName,
            email: editTeacher.value.email,
            area_ids: editTeacher.value.area_ids || [],
            career_ids: editTeacher.value.career_ids || [],
            group_ids: editTeacher.value.group_ids || [],
        });

        const index = teachers.value.findIndex(t => t.id === editTeacher.value.id);
        if (index !== -1) {
            teachers.value[index] = response.data;
        }
        showEditModal.value = false;
        await loadTeachers(); // Recargar la lista
    } catch (error) {
        console.error('Error actualizando maestro:', error);
        if (error.response) {
            console.error('Respuesta del servidor:', error.response.status, error.response.data);
        }
        const errorMessage = error.response?.data?.message || error.response?.data?.errors?.email?.[0] || 'Error desconocido';
        alert('Error al actualizar maestro: ' + errorMessage);
    }
};

// Eliminar maestro
const deleteTeacher = async (teacher) => {
    if (!confirm(`¿Estás seguro de eliminar a ${teacher.name}?`)) {
        return;
    }

    try {
        await axios.delete(`/admin/teachers/${teacher.id}`);
        teachers.value = teachers.value.filter(t => t.id !== teacher.id);
        await loadTeachers(); // Recargar la lista
    } catch (error) {
        console.error('Error eliminando maestro:', error);
        alert('Error al eliminar maestro');
    }
};

// Resetear formulario
const resetForm = () => {
    newTeacher.value = {
        name: '',
        apellido_paterno: '',
        apellido_materno: '',
        email: '',
        password: '',
        password_confirmation: '',
        area_ids: [],
        career_ids: [], // Limpiar carreras al resetear
        group_ids: [], // Limpiar grupos al resetear
    };
    availableGroups.value = [];
};

// Watch para limpiar carreras cuando cambian los sectores (crear)
watch(() => newTeacher.value.area_ids, (newAreas, oldAreas) => {
    // Si se deseleccionaron sectores, remover carreras que no pertenecen a los sectores actuales
    if (newTeacher.value.career_ids && newTeacher.value.career_ids.length > 0) {
        const validCareerIds = filteredCareersForNew.value.map(c => c.id);
        newTeacher.value.career_ids = newTeacher.value.career_ids.filter(id => validCareerIds.includes(id));
    }
});

// Watch para limpiar carreras cuando cambian los sectores (editar)
watch(() => editTeacher.value.area_ids, (newAreas, oldAreas) => {
    // Si se deseleccionaron sectores, remover carreras que no pertenecen a los sectores actuales
    if (editTeacher.value.career_ids && editTeacher.value.career_ids.length > 0) {
        const validCareerIds = filteredCareersForEdit.value.map(c => c.id);
        editTeacher.value.career_ids = editTeacher.value.career_ids.filter(id => validCareerIds.includes(id));
    }
    // Recargar grupos cuando cambian los sectores
    if (editTeacher.value.id) {
        loadAvailableGroups('edit');
    }
});

// Cargar grupos disponibles basados en las carreras seleccionadas
const loadAvailableGroups = async (mode = 'new') => {
    try {
        loadingGroups.value = true;
        const careerIds = mode === 'new' ? newTeacher.value.career_ids : editTeacher.value.career_ids;
        const teacherId = mode === 'edit' ? editTeacher.value.id : null;
        
        if (!careerIds || careerIds.length === 0) {
            availableGroups.value = [];
            return;
        }
        
        const params = { career_ids: careerIds };
        if (teacherId) {
            params.teacher_id = teacherId;
        }
        
        const response = await axios.get('/admin/teachers/groups/available', { params });
        availableGroups.value = response.data;
    } catch (error) {
        console.error('Error cargando grupos:', error);
        availableGroups.value = [];
    } finally {
        loadingGroups.value = false;
    }
};

// Watch para cargar grupos cuando cambian las carreras (crear)
watch(() => newTeacher.value.career_ids, () => {
    loadAvailableGroups('new');
    // Limpiar grupos seleccionados si ya no están disponibles
    if (newTeacher.value.group_ids && newTeacher.value.group_ids.length > 0) {
        const validGroupIds = availableGroups.value.map(g => g.id);
        newTeacher.value.group_ids = newTeacher.value.group_ids.filter(id => validGroupIds.includes(id));
    }
});

// Watch para cargar grupos cuando cambian las carreras (editar)
watch(() => editTeacher.value.career_ids, () => {
    if (editTeacher.value.id) {
        loadAvailableGroups('edit');
        // Limpiar grupos seleccionados si ya no están disponibles
        if (editTeacher.value.group_ids && editTeacher.value.group_ids.length > 0) {
            const validGroupIds = availableGroups.value.map(g => g.id);
            editTeacher.value.group_ids = editTeacher.value.group_ids.filter(id => validGroupIds.includes(id));
        }
    }
});

// Filtrar maestros
const filteredTeachers = () => {
    if (!searchQuery.value) return teachers.value;
    const query = searchQuery.value.toLowerCase();
    return teachers.value.filter(teacher => 
        teacher.name.toLowerCase().includes(query) ||
        teacher.email.toLowerCase().includes(query)
    );
};

onMounted(() => {
    loadAreas();
    loadCareers();
    loadTeachers();
});
</script>

<template>
    <Head title="Gestión de Maestros - Administrador UTM" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                
                <!-- Header -->
                <div :class="['mb-8 rounded-2xl p-6 border shadow-lg', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 :class="['font-heading text-3xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Gestión de Maestros
                            </h1>
                            <p :class="['font-body text-lg', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                Administrar información de todos los maestros
                            </p>
                        </div>
                        <button 
                            @click="showAddModal = true"
                            :class="['font-body mt-4 md:mt-0 px-6 py-3 rounded-lg font-semibold transition-all hover:shadow-lg flex items-center gap-2', darkMode ? 'bg-blue-500 hover:bg-blue-600 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                        >
                            <PlusIcon class="w-5 h-5" />
                            Nuevo Maestro
                        </button>
                    </div>
                </div>

                <!-- Búsqueda y filtros -->
                <div :class="['mb-6 rounded-xl border p-6', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <div class="flex items-center space-x-4">
                        <div class="flex-1 relative">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar por nombre o correo..."
                                :class="['font-body w-full px-4 py-3 pl-12 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400 focus:ring-blue-400' : 'bg-white text-gray-900 placeholder-gray-500']"
                            >
                            <MagnifyingGlassIcon class="w-5 h-5 absolute left-4 top-1/2 transform -translate-y-1/2" :class="darkMode ? 'text-gray-400' : 'text-gray-500'" />
                        </div>
                        <div :class="['font-body px-4 py-3 rounded-lg font-medium', darkMode ? 'bg-gray-700 text-white' : 'bg-gray-100 text-gray-900']">
                            Total: {{ filteredTeachers().length }}
                        </div>
                    </div>
                </div>

                <!-- Tabla de maestros -->
                <div :class="['rounded-2xl border shadow-lg overflow-hidden', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <!-- Loading state -->
                    <div v-if="loading" class="p-12 text-center">
                        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-blue-400' : 'border-blue-600'"></div>
                        <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando maestros...</p>
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="filteredTeachers().length === 0" class="p-12 text-center">
                        <UserGroupIcon class="w-24 h-24 mx-auto mb-4 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" />
                        <p :class="['font-heading text-lg font-semibold', darkMode ? 'text-gray-400' : 'text-gray-600']">
                            {{ searchQuery ? 'No se encontraron maestros' : 'No hay maestros registrados' }}
                        </p>
                        <button 
                            v-if="!searchQuery"
                            @click="showAddModal = true"
                            :class="['font-body mt-4 px-6 py-3 rounded-lg font-semibold transition-all flex items-center gap-2 mx-auto', darkMode ? 'bg-blue-500 hover:bg-blue-600 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                        >
                            <PlusIcon class="w-5 h-5" />
                            Agregar Primer Maestro
                        </button>
                    </div>

                    <!-- Teachers table -->
                    <div v-else class="overflow-x-auto">
                        <table class="w-full">
                            <thead :class="darkMode ? 'bg-gray-700' : 'bg-gray-50'">
                                <tr>
                                    <th :class="['font-body px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-300' : 'text-gray-500']">
                                        Nombre Completo
                                    </th>
                                    <th :class="['font-body px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-300' : 'text-gray-500']">
                                        Correo Institucional
                                    </th>
                                    <th :class="['font-body px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-300' : 'text-gray-500']">
                                        Sectores/Carreras
                                    </th>
                                    <th :class="['font-body px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-300' : 'text-gray-500']">
                                        Tutor
                                    </th>
                                    <th :class="['font-body px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-300' : 'text-gray-500']">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y" :class="darkMode ? 'divide-gray-700 bg-gray-800' : 'divide-gray-200 bg-white'">
                                <tr 
                                    v-for="teacher in filteredTeachers()" 
                                    :key="teacher.id"
                                    :class="['font-body transition-colors', darkMode ? 'hover:bg-gray-700/50' : 'hover:bg-gray-50']"
                                >
                                    <td :class="['px-6 py-4 whitespace-nowrap text-sm font-medium', darkMode ? 'text-white' : 'text-gray-900']">
                                        {{ teacher.name }}
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap text-sm', darkMode ? 'text-blue-400' : 'text-blue-600']">
                                        {{ teacher.email }}
                                    </td>
                                    <td :class="['px-6 py-4 text-sm', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                        <div v-if="teacher.areas && teacher.areas.length > 0" class="mb-2">
                                            <span class="font-semibold">Sectores:</span>
                                            <span class="ml-2">{{ teacher.areas.map(a => a.nombre).join(', ') }}</span>
                                        </div>
                                        <div v-if="teacher.careers && teacher.careers.length > 0">
                                            <span class="font-semibold">Carreras:</span>
                                            <span class="ml-2">{{ teacher.careers.map(c => c.nombre).join(', ') }}</span>
                                        </div>
                                        <span v-if="(!teacher.areas || teacher.areas.length === 0) && (!teacher.careers || teacher.careers.length === 0)" class="text-gray-500 italic">
                                            Sin sectores/carreras asignados
                                        </span>
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap text-center text-sm']">
                                        <span
                                            v-if="teacher.is_tutor || (teacher.tutor_groups && teacher.tutor_groups.length > 0)"
                                            :class="[
                                                'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold',
                                                darkMode ? 'bg-green-600 text-white' : 'bg-green-100 text-green-800'
                                            ]"
                                        >
                                            Sí
                                        </span>
                                        <span
                                            v-else
                                            :class="[
                                                'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold',
                                                darkMode ? 'bg-gray-700 text-gray-400' : 'bg-gray-100 text-gray-600'
                                            ]"
                                        >
                                            No
                                        </span>
                                        <div
                                            v-if="teacher.tutor_groups && teacher.tutor_groups.length > 0"
                                            class="mt-1 text-xs"
                                            :class="darkMode ? 'text-gray-400' : 'text-gray-600'"
                                        >
                                            <div v-for="group in teacher.tutor_groups" :key="group.id">
                                                {{ group.grado }}° {{ group.grupo }} - {{ group.carrera }}
                                            </div>
                                        </div>
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap text-center text-sm font-medium']">
                                        <div class="flex items-center justify-center space-x-2">
                                            <button
                                                @click="openEditModal(teacher)"
                                                :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-blue-400' : 'hover:bg-blue-50 text-blue-600']"
                                                title="Editar"
                                            >
                                                <PencilIcon class="w-5 h-5" />
                                            </button>
                                            <button
                                                @click="deleteTeacher(teacher)"
                                                :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-red-400' : 'hover:bg-red-50 text-red-600']"
                                                title="Eliminar"
                                            >
                                                <TrashIcon class="w-5 h-5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Modal Agregar Maestro -->
                <div 
                    v-if="showAddModal" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showAddModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-3xl w-full', darkMode ? 'bg-gray-800' : 'bg-white']" style="max-height: calc(100vh - 1rem); min-height: auto; overflow-y: auto;">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <h2 :class="['font-heading text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    Nuevo Maestro
                                </h2>
                                <button 
                                    @click="showAddModal = false; resetForm()"
                                    :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-gray-400' : 'hover:bg-gray-100 text-gray-600']"
                                >
                                    <XMarkIcon class="w-6 h-6" />
                                </button>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Apellido Paterno -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Apellido Paterno *
                                    </label>
                                    <input
                                        v-model="newTeacher.apellido_paterno"
                                        type="text"
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-blue-400' : 'bg-white text-gray-900']"
                                        placeholder="Pérez"
                                    >
                                </div>

                                <!-- Apellido Materno -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Apellido Materno *
                                    </label>
                                    <input
                                        v-model="newTeacher.apellido_materno"
                                        type="text"
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-blue-400' : 'bg-white text-gray-900']"
                                        placeholder="González"
                                    >
                                </div>

                                <!-- Nombres -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Nombres *
                                    </label>
                                    <input
                                        v-model="newTeacher.name"
                                        type="text"
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-blue-400' : 'bg-white text-gray-900']"
                                        placeholder="Juan Carlos"
                                    >
                                </div>

                                <!-- Correo Institucional (Generado automáticamente) -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Correo Institucional *
                                        <span class="font-normal text-xs text-gray-500 ml-2">(Generado automáticamente)</span>
                                    </label>
                                    <input
                                        v-model="newTeacher.email"
                                        type="email"
                                        readonly
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 cursor-not-allowed', darkMode ? 'bg-gray-800 border-gray-600 text-gray-400' : 'bg-gray-100 text-gray-600']"
                                        placeholder="Se generará automáticamente al ingresar apellido paterno y nombre"
                                    >
                                    <p class="font-body mt-1 text-xs" :class="darkMode ? 'text-gray-400' : 'text-gray-500'">
                                        Formato: {apellido_paterno}.{nombre}@utmetropolitana.edu.mx
                                    </p>
                                </div>

                                <!-- Contraseña -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Contraseña *
                                    </label>
                                    <input
                                        v-model="newTeacher.password"
                                        type="password"
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-blue-400' : 'bg-white text-gray-900']"
                                        placeholder="••••••••"
                                    >
                                </div>

                                <!-- Confirmar Contraseña -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Confirmar Contraseña *
                                    </label>
                                    <input
                                        v-model="newTeacher.password_confirmation"
                                        type="password"
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-blue-400' : 'bg-white text-gray-900']"
                                        placeholder="••••••••"
                                    >
                                </div>

                                <!-- Sectores/Áreas -->
                                <div class="md:col-span-2">
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Sectores/Áreas
                                        <span class="font-normal text-xs text-gray-500 ml-2">(Puede seleccionar múltiples)</span>
                                    </label>
                                    <MultiSelect
                                        v-model="newTeacher.area_ids"
                                        :options="areas && areas.length > 0 ? areas.map(a => ({ id: a.id, name: a.nombre })) : []"
                                        placeholder="Seleccionar sectores/áreas..."
                                        :disabled="!areas || areas.length === 0"
                                    />
                                </div>

                                <!-- Carreras -->
                                <div class="md:col-span-2">
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Carreras
                                        <span class="font-normal text-xs text-gray-500 ml-2">(Puede seleccionar múltiples)</span>
                                    </label>
                                    <MultiSelect
                                        v-model="newTeacher.career_ids"
                                        :options="filteredCareersForNew.map(c => ({ id: c.id, name: c.nombre }))"
                                        :placeholder="newTeacher.area_ids.length === 0 ? 'Primero selecciona un sector/área' : 'Seleccionar carreras...'"
                                        :disabled="!newTeacher.area_ids || newTeacher.area_ids.length === 0 || filteredCareersForNew.length === 0"
                                    />
                                    <p v-if="newTeacher.area_ids.length === 0" class="font-body mt-1 text-xs" :class="darkMode ? 'text-gray-400' : 'text-gray-500'">
                                        Selecciona primero un sector/área para ver las carreras disponibles
                                    </p>
                                    <p v-else-if="filteredCareersForNew.length === 0" class="font-body mt-1 text-xs" :class="darkMode ? 'text-gray-400' : 'text-gray-500'">
                                        No hay carreras disponibles para los sectores seleccionados
                                    </p>
                                </div>

                                <!-- Grupos como Tutor -->
                                <div class="md:col-span-2">
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Grupos como Tutor
                                        <span class="font-normal text-xs text-gray-500 ml-2">(Puede seleccionar múltiples)</span>
                                    </label>
                                    <MultiSelect
                                        v-model="newTeacher.group_ids"
                                        :options="availableGroups"
                                        :placeholder="newTeacher.career_ids.length === 0 ? 'Primero selecciona carreras' : (loadingGroups ? 'Cargando grupos...' : 'Seleccionar grupos...')"
                                        :disabled="!newTeacher.career_ids || newTeacher.career_ids.length === 0 || loadingGroups || availableGroups.length === 0"
                                    />
                                    <p v-if="newTeacher.career_ids.length === 0" class="font-body mt-1 text-xs" :class="darkMode ? 'text-gray-400' : 'text-gray-500'">
                                        Selecciona primero las carreras que imparte para ver los grupos disponibles
                                    </p>
                                    <p v-else-if="loadingGroups" class="font-body mt-1 text-xs" :class="darkMode ? 'text-gray-400' : 'text-gray-500'">
                                        Cargando grupos...
                                    </p>
                                    <p v-else-if="availableGroups.length === 0" class="font-body mt-1 text-xs" :class="darkMode ? 'text-gray-400' : 'text-gray-500'">
                                        No hay grupos disponibles para las carreras seleccionadas
                                    </p>
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end space-x-4">
                                <button
                                    @click="showAddModal = false; resetForm()"
                                    :class="['font-body px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                                >
                                    Cancelar
                                </button>
                                <button
                                    @click="addTeacher"
                                    :class="['font-body px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-blue-500 hover:bg-blue-600 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                                >
                                    Agregar Maestro
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Editar Maestro -->
                <div 
                    v-if="showEditModal" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showEditModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-3xl w-full', darkMode ? 'bg-gray-800' : 'bg-white']" style="max-height: calc(100vh - 1rem); min-height: auto; overflow-y: auto;">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <h2 :class="['font-heading text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    Editar Maestro
                                </h2>
                                <button 
                                    @click="showEditModal = false"
                                    :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-gray-400' : 'hover:bg-gray-100 text-gray-600']"
                                >
                                    <XMarkIcon class="w-6 h-6" />
                                </button>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Apellido Paterno -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Apellido Paterno *
                                    </label>
                                    <input
                                        v-model="editTeacher.apellido_paterno"
                                        type="text"
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-blue-400' : 'bg-white text-gray-900']"
                                    >
                                </div>

                                <!-- Apellido Materno -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Apellido Materno *
                                    </label>
                                    <input
                                        v-model="editTeacher.apellido_materno"
                                        type="text"
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-blue-400' : 'bg-white text-gray-900']"
                                    >
                                </div>

                                <!-- Nombres -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Nombres *
                                    </label>
                                    <input
                                        v-model="editTeacher.name"
                                        type="text"
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-blue-400' : 'bg-white text-gray-900']"
                                    >
                                </div>

                                <!-- Correo Institucional -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Correo Institucional *
                                    </label>
                                    <input
                                        v-model="editTeacher.email"
                                        type="email"
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-blue-400' : 'bg-white text-gray-900']"
                                    >
                                    <p class="font-body mt-1 text-xs" :class="darkMode ? 'text-gray-400' : 'text-gray-500'">
                                        Debe terminar en @utmetropolitana.edu.mx
                                    </p>
                                </div>

                                <!-- Sectores/Áreas -->
                                <div class="md:col-span-2">
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Sectores/Áreas
                                        <span class="font-normal text-xs text-gray-500 ml-2">(Puede seleccionar múltiples)</span>
                                    </label>
                                    <MultiSelect
                                        v-model="editTeacher.area_ids"
                                        :options="areas && areas.length > 0 ? areas.map(a => ({ id: a.id, name: a.nombre })) : []"
                                        placeholder="Seleccionar sectores/áreas..."
                                        :disabled="!areas || areas.length === 0"
                                    />
                                </div>

                                <!-- Carreras -->
                                <div class="md:col-span-2">
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Carreras
                                        <span class="font-normal text-xs text-gray-500 ml-2">(Puede seleccionar múltiples)</span>
                                    </label>
                                    <MultiSelect
                                        v-model="editTeacher.career_ids"
                                        :options="filteredCareersForEdit.map(c => ({ id: c.id, name: c.nombre }))"
                                        :placeholder="editTeacher.area_ids.length === 0 ? 'Primero selecciona un sector/área' : 'Seleccionar carreras...'"
                                        :disabled="!editTeacher.area_ids || editTeacher.area_ids.length === 0 || filteredCareersForEdit.length === 0"
                                    />
                                    <p v-if="editTeacher.area_ids.length === 0" class="font-body mt-1 text-xs" :class="darkMode ? 'text-gray-400' : 'text-gray-500'">
                                        Selecciona primero un sector/área para ver las carreras disponibles
                                    </p>
                                    <p v-else-if="filteredCareersForEdit.length === 0" class="font-body mt-1 text-xs" :class="darkMode ? 'text-gray-400' : 'text-gray-500'">
                                        No hay carreras disponibles para los sectores seleccionados
                                    </p>
                                </div>

                                <!-- Grupos como Tutor -->
                                <div class="md:col-span-2">
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Grupos como Tutor
                                        <span class="font-normal text-xs text-gray-500 ml-2">(Puede seleccionar múltiples)</span>
                                    </label>
                                    <MultiSelect
                                        v-model="editTeacher.group_ids"
                                        :options="availableGroups"
                                        :placeholder="editTeacher.career_ids.length === 0 ? 'Primero selecciona carreras' : (loadingGroups ? 'Cargando grupos...' : 'Seleccionar grupos...')"
                                        :disabled="!editTeacher.career_ids || editTeacher.career_ids.length === 0 || loadingGroups || availableGroups.length === 0"
                                    />
                                    <p v-if="editTeacher.career_ids.length === 0" class="font-body mt-1 text-xs" :class="darkMode ? 'text-gray-400' : 'text-gray-500'">
                                        Selecciona primero las carreras que imparte para ver los grupos disponibles
                                    </p>
                                    <p v-else-if="loadingGroups" class="font-body mt-1 text-xs" :class="darkMode ? 'text-gray-400' : 'text-gray-500'">
                                        Cargando grupos...
                                    </p>
                                    <p v-else-if="availableGroups.length === 0" class="font-body mt-1 text-xs" :class="darkMode ? 'text-gray-400' : 'text-gray-500'">
                                        No hay grupos disponibles para las carreras seleccionadas
                                    </p>
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end space-x-4">
                                <button
                                    @click="showEditModal = false"
                                    :class="['font-body px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                                >
                                    Cancelar
                                </button>
                                <button
                                    @click="updateTeacher"
                                    :class="['font-body px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-blue-500 hover:bg-blue-600 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                                >
                                    Actualizar Maestro
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botón de regreso -->
                <div class="mt-8">
                    <Link :href="route('dashboard-admin')" :class="['inline-flex items-center px-6 py-3 rounded-lg font-medium transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-gray-300' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Regresar al Dashboard
                    </Link>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

