<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, onMounted } from 'vue';
import axios from 'axios';

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Estados
const subjects = ref([]);
const careers = ref([]);
const areas = ref([]);
const loading = ref(true);
const showAddModal = ref(false);
const showEditModal = ref(false);
const selectedSubject = ref(null);
const searchQuery = ref('');
const filterGrado = ref('');
const filterCarrera = ref('');

// Formulario para agregar materia
const newSubject = ref({
    nombre: '',
    codigo: '',
    grado: '',
    career_ids: [],
});

// Formulario para editar materia
const editSubject = ref({
    id: null,
    nombre: '',
    codigo: '',
    grado: '',
    career_ids: [],
});

// Grados disponibles (1-5)
const grados = [1, 2, 3, 4, 5];

// Cargar carreras
const loadCareers = async () => {
    try {
        const response = await axios.get('/admin/careers');
        careers.value = response.data;
    } catch (error) {
        console.error('Error cargando carreras:', error);
    }
};

// Cargar áreas
const loadAreas = async () => {
    try {
        const response = await axios.get('/admin/areas');
        areas.value = response.data;
    } catch (error) {
        console.error('Error cargando áreas:', error);
    }
};

// Cargar materias
const loadSubjects = async () => {
    try {
        loading.value = true;
        const params = {};
        if (filterGrado.value) params.grado = filterGrado.value;
        if (filterCarrera.value) params.career_id = filterCarrera.value;
        
        const response = await axios.get('/admin/subjects', { params });
        subjects.value = response.data;
    } catch (error) {
        console.error('Error cargando materias:', error);
    } finally {
        loading.value = false;
    }
};

// Agregar nueva materia
const addSubject = async () => {
    try {
        const response = await axios.post('/admin/subjects', {
            nombre: newSubject.value.nombre,
            codigo: newSubject.value.codigo,
            grado: parseInt(newSubject.value.grado),
            career_ids: newSubject.value.career_ids.map(id => parseInt(id)),
        });

        subjects.value.push(response.data);
        showAddModal.value = false;
        resetForm();
        await loadSubjects();
    } catch (error) {
        console.error('Error agregando materia:', error);
        const errorMessage = error.response?.data?.message || error.response?.data?.errors?.[Object.keys(error.response?.data?.errors || {})[0]]?.[0] || 'Error desconocido';
        alert('Error al agregar materia: ' + errorMessage);
    }
};

// Editar materia
const openEditModal = async (subject) => {
    selectedSubject.value = subject;
    
    // Cargar las carreras asignadas a esta materia
    try {
        const response = await axios.get(`/admin/subjects/${subject.id}`);
        const subjectData = response.data;
        editSubject.value = {
            id: subject.id,
            nombre: subject.nombre,
            codigo: subject.codigo,
            grado: subject.grado.toString(),
            career_ids: subjectData.careers?.map(c => c.id.toString()) || [],
        };
    } catch (error) {
        console.error('Error cargando detalle de materia:', error);
        editSubject.value = {
            id: subject.id,
            nombre: subject.nombre,
            codigo: subject.codigo,
            grado: subject.grado.toString(),
            career_ids: [],
        };
    }
    
    showEditModal.value = true;
};

const updateSubject = async () => {
    try {
        const response = await axios.put(`/admin/subjects/${editSubject.value.id}`, {
            nombre: editSubject.value.nombre,
            codigo: editSubject.value.codigo,
            grado: parseInt(editSubject.value.grado),
            career_ids: editSubject.value.career_ids.map(id => parseInt(id)),
        });

        const index = subjects.value.findIndex(s => s.id === editSubject.value.id);
        if (index !== -1) {
            subjects.value[index] = response.data;
        }
        showEditModal.value = false;
        await loadSubjects();
    } catch (error) {
        console.error('Error actualizando materia:', error);
        const errorMessage = error.response?.data?.message || error.response?.data?.errors?.[Object.keys(error.response?.data?.errors || {})[0]]?.[0] || 'Error desconocido';
        alert('Error al actualizar materia: ' + errorMessage);
    }
};

// Eliminar materia
const deleteSubject = async (subject) => {
    if (!confirm(`¿Estás seguro de eliminar la materia: ${subject.nombre}?`)) {
        return;
    }

    try {
        await axios.delete(`/admin/subjects/${subject.id}`);
        subjects.value = subjects.value.filter(s => s.id !== subject.id);
        await loadSubjects();
    } catch (error) {
        console.error('Error eliminando materia:', error);
        alert('Error al eliminar materia');
    }
};

// Resetear formulario
const resetForm = () => {
    newSubject.value = {
        nombre: '',
        codigo: '',
        grado: '',
        career_ids: [],
    };
};

// Filtrar materias
const filteredSubjects = () => {
    let result = subjects.value;
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(subject => 
            subject.nombre.toLowerCase().includes(query) ||
            subject.codigo.toLowerCase().includes(query) ||
            subject.careers?.some(c => c.nombre.toLowerCase().includes(query)) ||
            subject.carrera?.toLowerCase().includes(query)
        );
    }
    
    return result;
};

// Obtener nombres de carreras de una materia
const getCareerNames = (subject) => {
    if (subject.careers && subject.careers.length > 0) {
        return subject.careers.map(c => c.nombre).join(', ');
    }
    return subject.carrera || 'Sin carrera asignada';
};

onMounted(() => {
    loadAreas();
    loadCareers();
    loadSubjects();
});
</script>

<template>
    <Head title="Gestión de Materias - Administrador UTM" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                
                <!-- Header -->
                <div :class="['mb-8 rounded-2xl p-6 border shadow-lg', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 :class="['text-3xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Gestión de Materias
                            </h1>
                            <p :class="['text-lg', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                Crear y administrar materias por grado y carrera
                            </p>
                        </div>
                        <button 
                            @click="showAddModal = true"
                            :class="['mt-4 md:mt-0 px-6 py-3 rounded-xl font-semibold transition-all hover:shadow-lg', darkMode ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-emerald-500 hover:bg-emerald-600 text-white']"
                        >
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Nueva Materia
                        </button>
                    </div>
                </div>

                <!-- Búsqueda y filtros -->
                <div :class="['mb-6 rounded-xl border p-6', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex-1 relative">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar por nombre, código o carrera..."
                                :class="['w-full px-4 py-3 pl-12 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500']"
                            >
                            <svg class="w-5 h-5 absolute left-4 top-1/2 transform -translate-y-1/2" :class="darkMode ? 'text-gray-400' : 'text-gray-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <select
                            v-model="filterGrado"
                            @change="loadSubjects"
                            :class="['px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                        >
                            <option value="">Todos los grados</option>
                            <option v-for="grado in grados" :key="grado" :value="grado">
                                {{ grado }}° Grado
                            </option>
                        </select>
                        <select
                            v-model="filterCarrera"
                            @change="loadSubjects"
                            :class="['px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                        >
                            <option value="">Todas las carreras</option>
                            <option v-for="career in careers" :key="career.id" :value="career.id">
                                {{ career.nombre }}
                            </option>
                        </select>
                        <div :class="['px-4 py-3 rounded-lg', darkMode ? 'bg-gray-700 text-white' : 'bg-gray-100 text-gray-900']">
                            Total: {{ filteredSubjects().length }}
                        </div>
                    </div>
                </div>

                <!-- Tabla de materias -->
                <div :class="['rounded-2xl border shadow-lg overflow-hidden', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <!-- Loading state -->
                    <div v-if="loading" class="p-12 text-center">
                        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-emerald-400' : 'border-emerald-600'"></div>
                        <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando materias...</p>
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="filteredSubjects().length === 0" class="p-12 text-center">
                        <svg class="w-24 h-24 mx-auto mb-4 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <p :class="['text-lg font-semibold', darkMode ? 'text-gray-400' : 'text-gray-600']">
                            {{ searchQuery ? 'No se encontraron materias' : 'No hay materias registradas' }}
                        </p>
                        <button 
                            v-if="!searchQuery"
                            @click="showAddModal = true"
                            :class="['mt-4 px-6 py-3 rounded-xl font-semibold transition-all', darkMode ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-emerald-500 hover:bg-emerald-600 text-white']"
                        >
                            Crear Primera Materia
                        </button>
                    </div>

                    <!-- Subjects table -->
                    <div v-else class="overflow-x-auto">
                        <table class="w-full">
                            <thead :class="darkMode ? 'bg-gray-700' : 'bg-gray-100'">
                                <tr>
                                    <th :class="['px-6 py-4 text-left text-sm font-bold uppercase tracking-wider', darkMode ? 'text-gray-200' : 'text-gray-700']">
                                        Código
                                    </th>
                                    <th :class="['px-6 py-4 text-left text-sm font-bold uppercase tracking-wider', darkMode ? 'text-gray-200' : 'text-gray-700']">
                                        Nombre
                                    </th>
                                    <th :class="['px-6 py-4 text-left text-sm font-bold uppercase tracking-wider', darkMode ? 'text-gray-200' : 'text-gray-700']">
                                        Grado
                                    </th>
                                    <th :class="['px-6 py-4 text-left text-sm font-bold uppercase tracking-wider', darkMode ? 'text-gray-200' : 'text-gray-700']">
                                        Carrera
                                    </th>
                                    <th :class="['px-6 py-4 text-center text-sm font-bold uppercase tracking-wider', darkMode ? 'text-gray-200' : 'text-gray-700']">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y" :class="darkMode ? 'divide-gray-700' : 'divide-gray-200'">
                                <tr 
                                    v-for="subject in filteredSubjects()" 
                                    :key="subject.id"
                                    :class="['hover:bg-opacity-50 transition-colors', darkMode ? 'hover:bg-gray-700' : 'hover:bg-gray-50']"
                                >
                                    <td :class="['px-6 py-4 whitespace-nowrap text-sm font-semibold', darkMode ? 'text-emerald-400' : 'text-emerald-600']">
                                        {{ subject.codigo }}
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap text-sm font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                        {{ subject.nombre }}
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap']">
                                        <span :class="['inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold', darkMode ? 'bg-green-500/20 text-green-400' : 'bg-green-100 text-green-800']">
                                            {{ subject.grado }}° Grado
                                        </span>
                                    </td>
                                    <td :class="['px-6 py-4 text-sm', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                        <div v-if="subject.careers && subject.careers.length > 0" class="flex flex-wrap gap-2">
                                            <span 
                                                v-for="career in subject.careers" 
                                                :key="career.id"
                                                :class="['px-2 py-1 rounded text-xs', darkMode ? 'bg-blue-500/20 text-blue-300' : 'bg-blue-100 text-blue-700']"
                                            >
                                                {{ career.nombre }}
                                            </span>
                                        </div>
                                        <span v-else :class="['text-xs italic', darkMode ? 'text-gray-500' : 'text-gray-400']">
                                            Sin carrera asignada
                                        </span>
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap text-center text-sm font-medium']">
                                        <div class="flex items-center justify-center space-x-2">
                                            <button
                                                @click="openEditModal(subject)"
                                                :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-blue-400' : 'hover:bg-blue-50 text-blue-600']"
                                                title="Editar"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </button>
                                            <button
                                                @click="deleteSubject(subject)"
                                                :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-red-400' : 'hover:bg-red-50 text-red-600']"
                                                title="Eliminar"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Modal Agregar Materia -->
                <div 
                    v-if="showAddModal" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showAddModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    Nueva Materia
                                </h2>
                                <button 
                                    @click="showAddModal = false; resetForm()"
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
                                        Nombre de la Materia *
                                    </label>
                                    <input
                                        v-model="newSubject.nombre"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                        placeholder="Ej: Programación Orientada a Objetos"
                                    >
                                </div>

                                <!-- Código -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Código *
                                    </label>
                                    <input
                                        v-model="newSubject.codigo"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                        placeholder="Ej: POO-2024"
                                    >
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Grado -->
                                    <div>
                                        <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                            Grado *
                                        </label>
                                        <select
                                            v-model="newSubject.grado"
                                            :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                        >
                                            <option value="">Seleccione un grado</option>
                                            <option v-for="grado in grados" :key="grado" :value="grado">
                                                {{ grado }}° Grado
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Carreras (Múltiple selección) -->
                                    <div class="md:col-span-2">
                                        <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                            Carreras * (Puede seleccionar múltiples)
                                        </label>
                                        <div :class="['max-h-48 overflow-y-auto border rounded-lg p-4 space-y-2', darkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-300']">
                                            <div v-if="careers.length === 0" :class="['text-sm italic', darkMode ? 'text-gray-400' : 'text-gray-500']">
                                                No hay carreras disponibles. Crea carreras primero.
                                            </div>
                                            <label 
                                                v-for="career in careers" 
                                                :key="career.id"
                                                :class="['flex items-center space-x-3 p-2 rounded hover:bg-opacity-50 cursor-pointer', darkMode ? 'hover:bg-gray-600' : 'hover:bg-gray-100']"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :value="career.id.toString()"
                                                    v-model="newSubject.career_ids"
                                                    :class="['w-5 h-5 rounded', darkMode ? 'text-emerald-600 bg-gray-600 border-gray-500' : 'text-emerald-600']"
                                                >
                                                <span :class="['text-sm', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                    {{ career.nombre }} <span :class="['text-xs', darkMode ? 'text-gray-500' : 'text-gray-500']">({{ career.area?.nombre }})</span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end space-x-4">
                                <button
                                    @click="showAddModal = false; resetForm()"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                                >
                                    Cancelar
                                </button>
                                <button
                                    @click="addSubject"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-emerald-500 hover:bg-emerald-600 text-white']"
                                >
                                    Crear Materia
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Editar Materia -->
                <div 
                    v-if="showEditModal" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showEditModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    Editar Materia
                                </h2>
                                <button 
                                    @click="showEditModal = false"
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
                                        Nombre de la Materia *
                                    </label>
                                    <input
                                        v-model="editSubject.nombre"
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
                                        v-model="editSubject.codigo"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Grado -->
                                    <div>
                                        <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                            Grado *
                                        </label>
                                        <select
                                            v-model="editSubject.grado"
                                            :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                        >
                                            <option value="">Seleccione un grado</option>
                                            <option v-for="grado in grados" :key="grado" :value="grado">
                                                {{ grado }}° Grado
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Carreras (Múltiple selección) -->
                                    <div class="md:col-span-2">
                                        <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                            Carreras * (Puede seleccionar múltiples)
                                        </label>
                                        <div :class="['max-h-48 overflow-y-auto border rounded-lg p-4 space-y-2', darkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-300']">
                                            <div v-if="careers.length === 0" :class="['text-sm italic', darkMode ? 'text-gray-400' : 'text-gray-500']">
                                                No hay carreras disponibles. Crea carreras primero.
                                            </div>
                                            <label 
                                                v-for="career in careers" 
                                                :key="career.id"
                                                :class="['flex items-center space-x-3 p-2 rounded hover:bg-opacity-50 cursor-pointer', darkMode ? 'hover:bg-gray-600' : 'hover:bg-gray-100']"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :value="career.id.toString()"
                                                    v-model="editSubject.career_ids"
                                                    :class="['w-5 h-5 rounded', darkMode ? 'text-emerald-600 bg-gray-600 border-gray-500' : 'text-emerald-600']"
                                                >
                                                <span :class="['text-sm', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                    {{ career.nombre }} <span :class="['text-xs', darkMode ? 'text-gray-500' : 'text-gray-500']">({{ career.area?.nombre }})</span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end space-x-4">
                                <button
                                    @click="showEditModal = false"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                                >
                                    Cancelar
                                </button>
                                <button
                                    @click="updateSubject"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-emerald-500 hover:bg-emerald-600 text-white']"
                                >
                                    Actualizar Materia
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

