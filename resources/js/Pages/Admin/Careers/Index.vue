<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, onMounted } from 'vue';
import axios from 'axios';

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Estados
const areas = ref([]);
const careers = ref([]);
const loading = ref(true);
const showAddAreaModal = ref(false);
const showEditAreaModal = ref(false);
const showAddCareerModal = ref(false);
const showEditCareerModal = ref(false);
const showDetailModal = ref(false);
const selectedArea = ref(null);
const selectedCareer = ref(null);
const searchQuery = ref('');
const filterArea = ref('');

// Formulario para agregar área
const newArea = ref({
    nombre: '',
    codigo: '',
    descripcion: '',
});

// Formulario para editar área
const editArea = ref({
    id: null,
    nombre: '',
    codigo: '',
    descripcion: '',
});

// Formulario para agregar carrera
const newCareer = ref({
    area_id: '',
    nombre: '',
    codigo: '',
});

// Formulario para editar carrera
const editCareer = ref({
    id: null,
    area_id: '',
    nombre: '',
    codigo: '',
});

// Cargar áreas
const loadAreas = async () => {
    try {
        const response = await axios.get('/admin/areas');
        areas.value = response.data;
    } catch (error) {
        console.error('Error cargando áreas:', error);
    }
};

// Cargar carreras
const loadCareers = async () => {
    try {
        loading.value = true;
        const params = {};
        if (filterArea.value) params.area_id = filterArea.value;
        
        const response = await axios.get('/admin/careers', { params });
        careers.value = response.data;
    } catch (error) {
        console.error('Error cargando carreras:', error);
    } finally {
        loading.value = false;
    }
};

// Abrir modal de detalle del área
const openDetailModal = async (area) => {
    selectedArea.value = area;
    showDetailModal.value = true;
    await loadCareers(); // Recargar para ver solo las carreras del área
};

// Agregar nueva área
const addArea = async () => {
    try {
        const response = await axios.post('/admin/areas', {
            nombre: newArea.value.nombre,
            codigo: newArea.value.codigo || null,
            descripcion: newArea.value.descripcion || null,
        });

        areas.value.push(response.data);
        showAddAreaModal.value = false;
        resetAreaForm();
        await loadAreas();
    } catch (error) {
        console.error('Error agregando área:', error);
        const errorMessage = error.response?.data?.message || error.response?.data?.errors?.[Object.keys(error.response?.data?.errors || {})[0]]?.[0] || 'Error desconocido';
        alert('Error al agregar área: ' + errorMessage);
    }
};

// Editar área
const openEditAreaModal = (area) => {
    selectedArea.value = area;
    editArea.value = {
        id: area.id,
        nombre: area.nombre,
        codigo: area.codigo || '',
        descripcion: area.descripcion || '',
    };
    showEditAreaModal.value = true;
};

const updateArea = async () => {
    try {
        const response = await axios.put(`/admin/areas/${editArea.value.id}`, {
            nombre: editArea.value.nombre,
            codigo: editArea.value.codigo || null,
            descripcion: editArea.value.descripcion || null,
        });

        const index = areas.value.findIndex(a => a.id === editArea.value.id);
        if (index !== -1) {
            areas.value[index] = response.data;
        }
        showEditAreaModal.value = false;
        await loadAreas();
    } catch (error) {
        console.error('Error actualizando área:', error);
        const errorMessage = error.response?.data?.message || error.response?.data?.errors?.[Object.keys(error.response?.data?.errors || {})[0]]?.[0] || 'Error desconocido';
        alert('Error al actualizar área: ' + errorMessage);
    }
};

// Eliminar área
const deleteArea = async (area) => {
    if (!confirm(`¿Estás seguro de eliminar el área: ${area.nombre}? Esto eliminará todas las carreras asociadas.`)) {
        return;
    }

    try {
        await axios.delete(`/admin/areas/${area.id}`);
        areas.value = areas.value.filter(a => a.id !== area.id);
        await loadAreas();
        await loadCareers();
    } catch (error) {
        console.error('Error eliminando área:', error);
        alert('Error al eliminar área');
    }
};

// Abrir modal para agregar carrera
const openAddCareerModal = (area = null) => {
    if (area) {
        selectedArea.value = area;
        newCareer.value.area_id = area.id.toString();
    }
    showAddCareerModal.value = true;
    resetCareerForm();
};

// Agregar nueva carrera
const addCareer = async () => {
    try {
        const response = await axios.post('/admin/careers', {
            area_id: parseInt(newCareer.value.area_id),
            nombre: newCareer.value.nombre,
            codigo: newCareer.value.codigo,
        });

        careers.value.push(response.data);
        showAddCareerModal.value = false;
        resetCareerForm();
        await loadCareers();
        await loadAreas(); // Recargar para actualizar el contador
    } catch (error) {
        console.error('Error agregando carrera:', error);
        const errorMessage = error.response?.data?.message || error.response?.data?.errors?.[Object.keys(error.response?.data?.errors || {})[0]]?.[0] || 'Error desconocido';
        alert('Error al agregar carrera: ' + errorMessage);
    }
};

// Editar carrera
const openEditCareerModal = (career) => {
    selectedCareer.value = career;
    editCareer.value = {
        id: career.id,
        area_id: career.area_id.toString(),
        nombre: career.nombre,
        codigo: career.codigo,
    };
    showEditCareerModal.value = true;
};

const updateCareer = async () => {
    try {
        const response = await axios.put(`/admin/careers/${editCareer.value.id}`, {
            area_id: parseInt(editCareer.value.area_id),
            nombre: editCareer.value.nombre,
            codigo: editCareer.value.codigo,
        });

        const index = careers.value.findIndex(c => c.id === editCareer.value.id);
        if (index !== -1) {
            careers.value[index] = response.data;
        }
        showEditCareerModal.value = false;
        await loadCareers();
        await loadAreas(); // Recargar para actualizar el contador
    } catch (error) {
        console.error('Error actualizando carrera:', error);
        const errorMessage = error.response?.data?.message || error.response?.data?.errors?.[Object.keys(error.response?.data?.errors || {})[0]]?.[0] || 'Error desconocido';
        alert('Error al actualizar carrera: ' + errorMessage);
    }
};

// Eliminar carrera
const deleteCareer = async (career) => {
    if (!confirm(`¿Estás seguro de eliminar la carrera: ${career.nombre}?`)) {
        return;
    }

    try {
        await axios.delete(`/admin/careers/${career.id}`);
        careers.value = careers.value.filter(c => c.id !== career.id);
        await loadCareers();
        await loadAreas(); // Recargar para actualizar el contador
    } catch (error) {
        console.error('Error eliminando carrera:', error);
        alert('Error al eliminar carrera');
    }
};

// Resetear formularios
const resetAreaForm = () => {
    newArea.value = {
        nombre: '',
        codigo: '',
        descripcion: '',
    };
};

const resetCareerForm = () => {
    newCareer.value = {
        area_id: selectedArea.value ? selectedArea.value.id.toString() : '',
        nombre: '',
        codigo: '',
    };
};

// Filtrar carreras
const filteredCareers = () => {
    let result = careers.value;
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(career => 
            career.nombre.toLowerCase().includes(query) ||
            career.codigo.toLowerCase().includes(query) ||
            career.area?.nombre.toLowerCase().includes(query)
        );
    }
    
    return result;
};

onMounted(() => {
    loadAreas();
    loadCareers();
});
</script>

<template>
    <Head title="Gestión de Áreas y Carreras - Administrador UTM" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                
                <!-- Header -->
                <div :class="['mb-8 rounded-2xl p-6 border shadow-lg', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 :class="['text-3xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Gestión de Áreas y Carreras
                            </h1>
                            <p :class="['text-lg', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                Organizar carreras por áreas de estudio
                            </p>
                        </div>
                        <div class="flex flex-col md:flex-row gap-3 mt-4 md:mt-0">
                            <button 
                                @click="showAddAreaModal = true"
                                :class="['px-6 py-3 rounded-xl font-semibold transition-all hover:shadow-lg', darkMode ? 'bg-cyan-600 hover:bg-cyan-700 text-white' : 'bg-cyan-500 hover:bg-cyan-600 text-white']"
                            >
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Nueva Área
                            </button>
                            <button 
                                @click="openAddCareerModal()"
                                :class="['px-6 py-3 rounded-xl font-semibold transition-all hover:shadow-lg', darkMode ? 'bg-rose-600 hover:bg-rose-700 text-white' : 'bg-rose-500 hover:bg-rose-600 text-white']"
                            >
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Nueva Carrera
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Búsqueda y filtros -->
                <div :class="['mb-6 rounded-xl border p-6', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex-1 relative">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar por nombre, código o área..."
                                :class="['w-full px-4 py-3 pl-12 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500']"
                            >
                            <svg class="w-5 h-5 absolute left-4 top-1/2 transform -translate-y-1/2" :class="darkMode ? 'text-gray-400' : 'text-gray-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <select
                            v-model="filterArea"
                            @change="loadCareers"
                            :class="['px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                        >
                            <option value="">Todas las áreas</option>
                            <option v-for="area in areas" :key="area.id" :value="area.id">
                                {{ area.nombre }}
                            </option>
                        </select>
                        <div :class="['px-4 py-3 rounded-lg', darkMode ? 'bg-gray-700 text-white' : 'bg-gray-100 text-gray-900']">
                            Total: {{ filteredCareers().length }}
                        </div>
                    </div>
                </div>

                <!-- Grid de Áreas y Carreras -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Sección de Áreas -->
                    <div :class="['rounded-2xl border shadow-lg overflow-hidden', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <h2 :class="['text-xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                Áreas de Estudio
                            </h2>
                        </div>
                        <div class="p-6 max-h-[600px] overflow-y-auto">
                            <div v-if="areas.length === 0" class="text-center py-8">
                                <p :class="['text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                    No hay áreas registradas
                                </p>
                            </div>
                            <div v-else class="space-y-3">
                                <div 
                                    v-for="area in areas" 
                                    :key="area.id"
                                    :class="['p-4 rounded-lg border flex items-center justify-between cursor-pointer transition-colors', darkMode ? 'bg-gray-700 border-gray-600 hover:bg-gray-600' : 'bg-gray-50 border-gray-200 hover:bg-gray-100']"
                                    @click="openDetailModal(area)"
                                >
                                    <div class="flex-1">
                                        <p :class="['font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                            {{ area.nombre }}
                                        </p>
                                        <p :class="['text-sm mt-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                            {{ area.careers_count || 0 }} carreras
                                        </p>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button
                                            @click.stop="openEditAreaModal(area)"
                                            :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-600 text-blue-400' : 'hover:bg-blue-50 text-blue-600']"
                                            title="Editar"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        <button
                                            @click.stop="deleteArea(area)"
                                            :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-600 text-red-400' : 'hover:bg-red-50 text-red-600']"
                                            title="Eliminar"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sección de Carreras -->
                    <div :class="['rounded-2xl border shadow-lg overflow-hidden', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <h2 :class="['text-xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                Carreras
                            </h2>
                        </div>
                        <div class="p-6 max-h-[600px] overflow-y-auto">
                            <!-- Loading state -->
                            <div v-if="loading" class="text-center py-12">
                                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-rose-400' : 'border-rose-600'"></div>
                                <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando carreras...</p>
                            </div>

                            <!-- Empty state -->
                            <div v-else-if="filteredCareers().length === 0" class="text-center py-8">
                                <svg class="w-24 h-24 mx-auto mb-4 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                                <p :class="['text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                    {{ searchQuery ? 'No se encontraron carreras' : 'No hay carreras registradas' }}
                                </p>
                            </div>

                            <!-- Careers list -->
                            <div v-else class="space-y-3">
                                <div 
                                    v-for="career in filteredCareers()" 
                                    :key="career.id"
                                    :class="['p-4 rounded-lg border flex items-center justify-between', darkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-200']"
                                >
                                    <div class="flex-1">
                                        <p :class="['font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                            {{ career.nombre }}
                                        </p>
                                        <p :class="['text-sm mt-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                            Código: {{ career.codigo }} - Área: {{ career.area?.nombre }}
                                        </p>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button
                                            @click="openEditCareerModal(career)"
                                            :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-600 text-blue-400' : 'hover:bg-blue-50 text-blue-600']"
                                            title="Editar"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        <button
                                            @click="deleteCareer(career)"
                                            :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-600 text-red-400' : 'hover:bg-red-50 text-red-600']"
                                            title="Eliminar"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Agregar Área -->
                <div 
                    v-if="showAddAreaModal" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showAddAreaModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    Nueva Área de Estudio
                                </h2>
                                <button 
                                    @click="showAddAreaModal = false; resetAreaForm()"
                                    :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-gray-400' : 'hover:bg-gray-100 text-gray-600']"
                                >
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="space-y-6">
                                <!-- Nombre -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Nombre del Área *
                                    </label>
                                    <input
                                        v-model="newArea.nombre"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                        placeholder="Ej: Desarrollo de Software"
                                    >
                                </div>

                                <!-- Código -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Código (Opcional)
                                    </label>
                                    <input
                                        v-model="newArea.codigo"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                        placeholder="Ej: DS"
                                    >
                                </div>

                                <!-- Descripción -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Descripción (Opcional)
                                    </label>
                                    <textarea
                                        v-model="newArea.descripcion"
                                        rows="3"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                        placeholder="Descripción del área de estudio..."
                                    ></textarea>
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end space-x-4">
                                <button
                                    @click="showAddAreaModal = false; resetAreaForm()"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                                >
                                    Cancelar
                                </button>
                                <button
                                    @click="addArea"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-cyan-600 hover:bg-cyan-700 text-white' : 'bg-cyan-500 hover:bg-cyan-600 text-white']"
                                >
                                    Crear Área
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Editar Área -->
                <div 
                    v-if="showEditAreaModal" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showEditAreaModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    Editar Área de Estudio
                                </h2>
                                <button 
                                    @click="showEditAreaModal = false"
                                    :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-gray-400' : 'hover:bg-gray-100 text-gray-600']"
                                >
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="space-y-6">
                                <!-- Nombre -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Nombre del Área *
                                    </label>
                                    <input
                                        v-model="editArea.nombre"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
                                </div>

                                <!-- Código -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Código (Opcional)
                                    </label>
                                    <input
                                        v-model="editArea.codigo"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
                                </div>

                                <!-- Descripción -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Descripción (Opcional)
                                    </label>
                                    <textarea
                                        v-model="editArea.descripcion"
                                        rows="3"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    ></textarea>
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end space-x-4">
                                <button
                                    @click="showEditAreaModal = false"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                                >
                                    Cancelar
                                </button>
                                <button
                                    @click="updateArea"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-cyan-600 hover:bg-cyan-700 text-white' : 'bg-cyan-500 hover:bg-cyan-600 text-white']"
                                >
                                    Actualizar Área
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Agregar Carrera -->
                <div 
                    v-if="showAddCareerModal" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showAddCareerModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    Nueva Carrera
                                </h2>
                                <button 
                                    @click="showAddCareerModal = false; resetCareerForm()"
                                    :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-gray-400' : 'hover:bg-gray-100 text-gray-600']"
                                >
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="space-y-6">
                                <!-- Área -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Área de Estudio *
                                    </label>
                                    <select
                                        v-model="newCareer.area_id"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
                                        <option value="">Seleccione un área</option>
                                        <option v-for="area in areas" :key="area.id" :value="area.id">
                                            {{ area.nombre }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Nombre -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Nombre de la Carrera *
                                    </label>
                                    <input
                                        v-model="newCareer.nombre"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                        placeholder="Ej: Ingeniería en Desarrollo de Software"
                                    >
                                </div>

                                <!-- Código -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Código *
                                    </label>
                                    <input
                                        v-model="newCareer.codigo"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                        placeholder="Ej: IDS-2024"
                                    >
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end space-x-4">
                                <button
                                    @click="showAddCareerModal = false; resetCareerForm()"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                                >
                                    Cancelar
                                </button>
                                <button
                                    @click="addCareer"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-rose-600 hover:bg-rose-700 text-white' : 'bg-rose-500 hover:bg-rose-600 text-white']"
                                >
                                    Crear Carrera
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Editar Carrera -->
                <div 
                    v-if="showEditCareerModal" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showEditCareerModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    Editar Carrera
                                </h2>
                                <button 
                                    @click="showEditCareerModal = false"
                                    :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-gray-400' : 'hover:bg-gray-100 text-gray-600']"
                                >
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="space-y-6">
                                <!-- Área -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Área de Estudio *
                                    </label>
                                    <select
                                        v-model="editCareer.area_id"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
                                        <option value="">Seleccione un área</option>
                                        <option v-for="area in areas" :key="area.id" :value="area.id">
                                            {{ area.nombre }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Nombre -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Nombre de la Carrera *
                                    </label>
                                    <input
                                        v-model="editCareer.nombre"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
                                </div>

                                <!-- Código -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Código *
                                    </label>
                                    <input
                                        v-model="editCareer.codigo"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end space-x-4">
                                <button
                                    @click="showEditCareerModal = false"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                                >
                                    Cancelar
                                </button>
                                <button
                                    @click="updateCareer"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-rose-600 hover:bg-rose-700 text-white' : 'bg-rose-500 hover:bg-rose-600 text-white']"
                                >
                                    Actualizar Carrera
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Detalle del Área -->
                <div 
                    v-if="showDetailModal && selectedArea" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showDetailModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                        Detalle del Área
                                    </h2>
                                    <p :class="['text-sm mt-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                        {{ selectedArea.nombre }}
                                    </p>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <button 
                                        @click="openAddCareerModal(selectedArea)"
                                        :class="['px-4 py-2 rounded-lg font-semibold transition-colors', darkMode ? 'bg-rose-600 hover:bg-rose-700 text-white' : 'bg-rose-500 hover:bg-rose-600 text-white']"
                                    >
                                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Agregar Carrera
                                    </button>
                                    <button 
                                        @click="showDetailModal = false"
                                        :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-gray-400' : 'hover:bg-gray-100 text-gray-600']"
                                    >
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <!-- Loading state -->
                            <div v-if="loading" class="text-center py-12">
                                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-rose-400' : 'border-rose-600'"></div>
                                <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando carreras...</p>
                            </div>

                            <!-- Empty state -->
                            <div v-else-if="careers.filter(c => c.area_id === selectedArea.id).length === 0" class="text-center py-12">
                                <svg class="w-24 h-24 mx-auto mb-4 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                                <p :class="['text-lg font-semibold mb-4', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                    No hay carreras en este área
                                </p>
                                <button 
                                    @click="openAddCareerModal(selectedArea)"
                                    :class="['px-6 py-3 rounded-xl font-semibold transition-colors', darkMode ? 'bg-rose-600 hover:bg-rose-700 text-white' : 'bg-rose-500 hover:bg-rose-600 text-white']"
                                >
                                    Agregar Primera Carrera
                                </button>
                            </div>

                            <!-- Careers list -->
                            <div v-else class="space-y-3">
                                <div 
                                    v-for="career in careers.filter(c => c.area_id === selectedArea.id)" 
                                    :key="career.id"
                                    :class="['p-4 rounded-lg border flex items-center justify-between', darkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-200']"
                                >
                                    <div class="flex-1">
                                        <p :class="['font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                            {{ career.nombre }}
                                        </p>
                                        <p :class="['text-sm mt-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                            Código: {{ career.codigo }}
                                        </p>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button
                                            @click="openEditCareerModal(career)"
                                            :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-600 text-blue-400' : 'hover:bg-blue-50 text-blue-600']"
                                            title="Editar"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        <button
                                            @click="deleteCareer(career)"
                                            :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-600 text-red-400' : 'hover:bg-red-50 text-red-600']"
                                            title="Eliminar"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
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

