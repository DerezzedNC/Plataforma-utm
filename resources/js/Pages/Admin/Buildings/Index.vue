<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, onMounted } from 'vue';
import axios from 'axios';

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Estados
const buildings = ref([]);
const loading = ref(true);
const showAddModal = ref(false);
const showEditModal = ref(false);
const showDetailModal = ref(false);
const showAddRoomModal = ref(false);
const showEditRoomModal = ref(false);
const selectedBuilding = ref(null);
const selectedRoom = ref(null);
const searchQuery = ref('');
const buildingRooms = ref([]);
const loadingRooms = ref(false);

// Formulario para agregar edificio
const newBuilding = ref({
    nombre: '',
    codigo: '',
});

// Formulario para editar edificio
const editBuilding = ref({
    id: null,
    nombre: '',
    codigo: '',
});

// Formulario para agregar aula/laboratorio
const newRoom = ref({
    nombre: '',
    codigo: '',
    tipo: '',
});

// Formulario para editar aula/laboratorio
const editRoom = ref({
    id: null,
    nombre: '',
    codigo: '',
    tipo: '',
});

// Tipos disponibles
const tipos = ['Aula', 'Laboratorio', 'Taller', 'Auditorio'];

// Cargar edificios
const loadBuildings = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/admin/buildings');
        buildings.value = response.data;
    } catch (error) {
        console.error('Error cargando edificios:', error);
    } finally {
        loading.value = false;
    }
};

// Agregar nuevo edificio
const addNewBuilding = async () => {
    try {
        const response = await axios.post('/admin/buildings', {
            nombre: newBuilding.value.nombre,
            codigo: newBuilding.value.codigo,
        });

        buildings.value.push(response.data);
        showAddModal.value = false;
        resetForm();
        await loadBuildings();
    } catch (error) {
        console.error('Error agregando edificio:', error);
        const errorMessage = error.response?.data?.message || error.response?.data?.errors?.[Object.keys(error.response?.data?.errors || {})[0]]?.[0] || 'Error desconocido';
        alert('Error al agregar edificio: ' + errorMessage);
    }
};

// Editar edificio
const openEditModal = (building) => {
    selectedBuilding.value = building;
    editBuilding.value = {
        id: building.id,
        nombre: building.nombre,
        codigo: building.codigo,
    };
    showEditModal.value = true;
};

const updateBuilding = async () => {
    try {
        const response = await axios.put(`/admin/buildings/${editBuilding.value.id}`, {
            nombre: editBuilding.value.nombre,
            codigo: editBuilding.value.codigo,
        });

        const index = buildings.value.findIndex(b => b.id === editBuilding.value.id);
        if (index !== -1) {
            buildings.value[index] = response.data;
        }
        showEditModal.value = false;
        await loadBuildings();
    } catch (error) {
        console.error('Error actualizando edificio:', error);
        const errorMessage = error.response?.data?.message || error.response?.data?.errors?.[Object.keys(error.response?.data?.errors || {})[0]]?.[0] || 'Error desconocido';
        alert('Error al actualizar edificio: ' + errorMessage);
    }
};

// Eliminar edificio
const deleteBuilding = async (building) => {
    if (!confirm(`¿Estás seguro de eliminar el edificio: ${building.nombre}?`)) {
        return;
    }

    try {
        await axios.delete(`/admin/buildings/${building.id}`);
        buildings.value = buildings.value.filter(b => b.id !== building.id);
        await loadBuildings();
    } catch (error) {
        console.error('Error eliminando edificio:', error);
        alert('Error al eliminar edificio');
    }
};

// Resetear formulario
const resetForm = () => {
    newBuilding.value = {
        nombre: '',
        codigo: '',
    };
};

// Filtrar edificios
const filteredBuildings = () => {
    if (!searchQuery.value) return buildings.value;
    const query = searchQuery.value.toLowerCase();
    return buildings.value.filter(building => 
        building.nombre.toLowerCase().includes(query) ||
        building.codigo.toLowerCase().includes(query)
    );
};

// Abrir modal de detalle del edificio
const openDetailModal = async (building) => {
    selectedBuilding.value = building;
    showDetailModal.value = true;
    loadingRooms.value = true;
    buildingRooms.value = [];
    
    try {
        const response = await axios.get('/admin/rooms', { params: { building_id: building.id } });
        buildingRooms.value = response.data;
    } catch (error) {
        console.error('Error cargando aulas del edificio:', error);
    } finally {
        loadingRooms.value = false;
    }
};

// Abrir modal para agregar aula
const openAddRoomModal = (building) => {
    selectedBuilding.value = building;
    showAddRoomModal.value = true;
    resetRoomForm();
};

// Agregar nueva aula/laboratorio
const addRoom = async () => {
    try {
        const response = await axios.post('/admin/rooms', {
            building_id: selectedBuilding.value.id,
            nombre: newRoom.value.nombre,
            codigo: newRoom.value.codigo,
            tipo: newRoom.value.tipo,
        });

        buildingRooms.value.push(response.data);
        showAddRoomModal.value = false;
        resetRoomForm();
        await loadBuildings(); // Recargar para actualizar el contador
    } catch (error) {
        console.error('Error agregando aula:', error);
        const errorMessage = error.response?.data?.message || error.response?.data?.errors?.[Object.keys(error.response?.data?.errors || {})[0]]?.[0] || 'Error desconocido';
        alert('Error al agregar aula/laboratorio: ' + errorMessage);
    }
};

// Editar aula/laboratorio
const openEditRoomModal = (room) => {
    selectedRoom.value = room;
    editRoom.value = {
        id: room.id,
        nombre: room.nombre,
        codigo: room.codigo,
        tipo: room.tipo,
    };
    showEditRoomModal.value = true;
};

const updateRoom = async () => {
    try {
        const response = await axios.put(`/admin/rooms/${editRoom.value.id}`, {
            building_id: selectedBuilding.value.id,
            nombre: editRoom.value.nombre,
            codigo: editRoom.value.codigo,
            tipo: editRoom.value.tipo,
        });

        const index = buildingRooms.value.findIndex(r => r.id === editRoom.value.id);
        if (index !== -1) {
            buildingRooms.value[index] = response.data;
        }
        showEditRoomModal.value = false;
        await loadBuildings(); // Recargar para actualizar el contador
    } catch (error) {
        console.error('Error actualizando aula:', error);
        const errorMessage = error.response?.data?.message || error.response?.data?.errors?.[Object.keys(error.response?.data?.errors || {})[0]]?.[0] || 'Error desconocido';
        alert('Error al actualizar aula/laboratorio: ' + errorMessage);
    }
};

// Eliminar aula/laboratorio
const deleteRoom = async (room) => {
    if (!confirm(`¿Estás seguro de eliminar el ${room.tipo}: ${room.nombre}?`)) {
        return;
    }

    try {
        await axios.delete(`/admin/rooms/${room.id}`);
        buildingRooms.value = buildingRooms.value.filter(r => r.id !== room.id);
        await loadBuildings(); // Recargar para actualizar el contador
    } catch (error) {
        console.error('Error eliminando aula:', error);
        alert('Error al eliminar aula/laboratorio');
    }
};

// Resetear formulario de aula
const resetRoomForm = () => {
    newRoom.value = {
        nombre: '',
        codigo: '',
        tipo: '',
    };
};

onMounted(() => {
    loadBuildings();
});
</script>

<template>
    <Head title="Gestión de Edificios - Administrador UTM" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                
                <!-- Header -->
                <div :class="['mb-8 rounded-2xl p-6 border shadow-lg', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 :class="['text-3xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Gestión de Edificios
                            </h1>
                            <p :class="['text-lg', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                Crear y administrar edificios y sus aulas
                            </p>
                        </div>
                        <button 
                            @click="showAddModal = true"
                            :class="['mt-4 md:mt-0 px-6 py-3 rounded-xl font-semibold transition-all hover:shadow-lg', darkMode ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-amber-500 hover:bg-amber-600 text-white']"
                        >
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Nuevo Edificio
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
                                placeholder="Buscar por nombre o código..."
                                :class="['w-full px-4 py-3 pl-12 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500']"
                            >
                            <svg class="w-5 h-5 absolute left-4 top-1/2 transform -translate-y-1/2" :class="darkMode ? 'text-gray-400' : 'text-gray-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <div :class="['px-4 py-3 rounded-lg', darkMode ? 'bg-gray-700 text-white' : 'bg-gray-100 text-gray-900']">
                            Total: {{ filteredBuildings().length }}
                        </div>
                    </div>
                </div>

                <!-- Tabla de edificios -->
                <div :class="['rounded-2xl border shadow-lg overflow-hidden', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <!-- Loading state -->
                    <div v-if="loading" class="p-12 text-center">
                        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-amber-400' : 'border-amber-600'"></div>
                        <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando edificios...</p>
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="filteredBuildings().length === 0" class="p-12 text-center">
                        <svg class="w-24 h-24 mx-auto mb-4 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        <p :class="['text-lg font-semibold', darkMode ? 'text-gray-400' : 'text-gray-600']">
                            {{ searchQuery ? 'No se encontraron edificios' : 'No hay edificios registrados' }}
                        </p>
                        <button 
                            v-if="!searchQuery"
                            @click="showAddModal = true"
                            :class="['mt-4 px-6 py-3 rounded-xl font-semibold transition-all', darkMode ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-amber-500 hover:bg-amber-600 text-white']"
                        >
                            Crear Primer Edificio
                        </button>
                    </div>

                    <!-- Buildings table -->
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
                                        Aulas/Laboratorios
                                    </th>
                                    <th :class="['px-6 py-4 text-center text-sm font-bold uppercase tracking-wider', darkMode ? 'text-gray-200' : 'text-gray-700']">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y" :class="darkMode ? 'divide-gray-700' : 'divide-gray-200'">
                                <tr 
                                    v-for="building in filteredBuildings()" 
                                    :key="building.id"
                                    :class="['hover:bg-opacity-50 transition-colors', darkMode ? 'hover:bg-gray-700' : 'hover:bg-gray-50']"
                                >
                                    <td :class="['px-6 py-4 whitespace-nowrap text-sm font-semibold', darkMode ? 'text-amber-400' : 'text-amber-600']">
                                        {{ building.codigo }}
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap text-sm font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                        {{ building.nombre }}
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap']">
                                        <span :class="['inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold', darkMode ? 'bg-indigo-500/20 text-indigo-400' : 'bg-indigo-100 text-indigo-800']">
                                            {{ building.rooms_count || 0 }} aulas/labs
                                        </span>
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap text-center text-sm font-medium']">
                                        <div class="flex items-center justify-center space-x-2">
                                            <button
                                                @click="openDetailModal(building)"
                                                :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-amber-400' : 'hover:bg-amber-50 text-amber-600']"
                                                title="Ver Detalle"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            </button>
                                            <button
                                                @click="openEditModal(building)"
                                                :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-blue-400' : 'hover:bg-blue-50 text-blue-600']"
                                                title="Editar"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </button>
                                            <button
                                                @click="deleteBuilding(building)"
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

                <!-- Modal Agregar Edificio -->
                <div 
                    v-if="showAddModal" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showAddModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    Nuevo Edificio
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
                                        Nombre del Edificio *
                                    </label>
                                    <input
                                        v-model="newBuilding.nombre"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                        placeholder="Ej: Edificio A"
                                    >
                                </div>

                                <!-- Código -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Código *
                                    </label>
                                    <input
                                        v-model="newBuilding.codigo"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                        placeholder="Ej: EDIF-A"
                                    >
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
                                    @click="addNewBuilding"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-amber-500 hover:bg-amber-600 text-white']"
                                >
                                    Crear Edificio
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Editar Edificio -->
                <div 
                    v-if="showEditModal" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showEditModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    Editar Edificio
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
                                        Nombre del Edificio *
                                    </label>
                                    <input
                                        v-model="editBuilding.nombre"
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
                                        v-model="editBuilding.codigo"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
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
                                    @click="updateBuilding"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-amber-500 hover:bg-amber-600 text-white']"
                                >
                                    Actualizar Edificio
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Detalle del Edificio -->
                <div 
                    v-if="showDetailModal && selectedBuilding" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showDetailModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                        Detalle del Edificio
                                    </h2>
                                    <p :class="['text-sm mt-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                        {{ selectedBuilding.nombre }} ({{ selectedBuilding.codigo }})
                                    </p>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <button 
                                        @click="openAddRoomModal(selectedBuilding)"
                                        :class="['px-4 py-2 rounded-lg font-semibold transition-colors', darkMode ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-amber-500 hover:bg-amber-600 text-white']"
                                    >
                                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Agregar Aula/Lab
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
                            <div v-if="loadingRooms" class="text-center py-12">
                                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-amber-400' : 'border-amber-600'"></div>
                                <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando aulas/laboratorios...</p>
                            </div>

                            <!-- Empty state -->
                            <div v-else-if="buildingRooms.length === 0" class="text-center py-12">
                                <svg class="w-24 h-24 mx-auto mb-4 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <p :class="['text-lg font-semibold mb-4', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                    No hay aulas/laboratorios en este edificio
                                </p>
                                <button 
                                    @click="openAddRoomModal(selectedBuilding)"
                                    :class="['px-6 py-3 rounded-xl font-semibold transition-colors', darkMode ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-amber-500 hover:bg-amber-600 text-white']"
                                >
                                    Agregar Primera Aula/Lab
                                </button>
                            </div>

                            <!-- Rooms list -->
                            <div v-else class="space-y-3">
                                <div 
                                    v-for="room in buildingRooms" 
                                    :key="room.id"
                                    :class="['p-4 rounded-lg border flex items-center justify-between', darkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-200']"
                                >
                                    <div class="flex-1">
                                        <p :class="['font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                            {{ room.nombre }}
                                        </p>
                                        <p :class="['text-sm mt-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                            Código: {{ room.codigo }} - Tipo: {{ room.tipo }}
                                        </p>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button
                                            @click="openEditRoomModal(room)"
                                            :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-600 text-blue-400' : 'hover:bg-blue-50 text-blue-600']"
                                            title="Editar"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        <button
                                            @click="deleteRoom(room)"
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

                <!-- Modal Agregar Aula/Laboratorio -->
                <div 
                    v-if="showAddRoomModal && selectedBuilding" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showAddRoomModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                        Nueva Aula/Laboratorio
                                    </h2>
                                    <p :class="['text-sm mt-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                        {{ selectedBuilding.nombre }} ({{ selectedBuilding.codigo }})
                                    </p>
                                </div>
                                <button 
                                    @click="showAddRoomModal = false; resetRoomForm()"
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
                                        Nombre *
                                    </label>
                                    <input
                                        v-model="newRoom.nombre"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                        placeholder="Ej: Aula 101"
                                    >
                                </div>

                                <!-- Código -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Código *
                                    </label>
                                    <input
                                        v-model="newRoom.codigo"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                        placeholder="Ej: AULA-101"
                                    >
                                </div>

                                <!-- Tipo -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Tipo *
                                    </label>
                                    <select
                                        v-model="newRoom.tipo"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
                                        <option value="">Seleccione un tipo</option>
                                        <option v-for="tipo in tipos" :key="tipo" :value="tipo">
                                            {{ tipo }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end space-x-4">
                                <button
                                    @click="showAddRoomModal = false; resetRoomForm()"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                                >
                                    Cancelar
                                </button>
                                <button
                                    @click="addRoom"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-amber-500 hover:bg-amber-600 text-white']"
                                >
                                    Crear Aula/Laboratorio
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Editar Aula/Laboratorio -->
                <div 
                    v-if="showEditRoomModal && selectedRoom" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showEditRoomModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                        Editar Aula/Laboratorio
                                    </h2>
                                    <p :class="['text-sm mt-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                        {{ selectedBuilding?.nombre }} ({{ selectedBuilding?.codigo }})
                                    </p>
                                </div>
                                <button 
                                    @click="showEditRoomModal = false"
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
                                        Nombre *
                                    </label>
                                    <input
                                        v-model="editRoom.nombre"
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
                                        v-model="editRoom.codigo"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
                                </div>

                                <!-- Tipo -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Tipo *
                                    </label>
                                    <select
                                        v-model="editRoom.tipo"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
                                        <option value="">Seleccione un tipo</option>
                                        <option v-for="tipo in tipos" :key="tipo" :value="tipo">
                                            {{ tipo }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end space-x-4">
                                <button
                                    @click="showEditRoomModal = false"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                                >
                                    Cancelar
                                </button>
                                <button
                                    @click="updateRoom"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-amber-500 hover:bg-amber-600 text-white']"
                                >
                                    Actualizar Aula/Laboratorio
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

