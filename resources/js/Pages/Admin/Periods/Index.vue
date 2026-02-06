<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Estados
const periods = ref([]);
const loading = ref(true);
const showAddModal = ref(false);
const togglingActive = ref(null); // ID del periodo que se está activando

// Formulario para agregar periodo
const newPeriod = ref({
    name: '',
    code: '',
    start_date: '',
    end_date: '',
    is_open_for_grades: true,
});

// Cargar periodos
const loadPeriods = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/admin/periods');
        periods.value = response.data;
    } catch (error) {
        console.error('Error cargando periodos:', error);
        alert('Error al cargar los periodos académicos');
    } finally {
        loading.value = false;
    }
};

// Agregar nuevo periodo
const addPeriod = async () => {
    try {
        const response = await axios.post('/admin/periods', {
            name: newPeriod.value.name,
            code: newPeriod.value.code,
            start_date: newPeriod.value.start_date,
            end_date: newPeriod.value.end_date,
            is_open_for_grades: newPeriod.value.is_open_for_grades,
        });

        periods.value.push(response.data);
        showAddModal.value = false;
        resetForm();
        await loadPeriods(); // Recargar la lista
    } catch (error) {
        console.error('Error agregando periodo:', error);
        const errorMessage = error.response?.data?.message || 
                            error.response?.data?.errors?.[Object.keys(error.response?.data?.errors || {})[0]]?.[0] || 
                            'Error desconocido';
        alert('Error al agregar periodo: ' + errorMessage);
    }
};

// Activar periodo (toggle)
const toggleActive = async (periodId) => {
    if (togglingActive.value === periodId) return; // Evitar múltiples clicks
    
    try {
        togglingActive.value = periodId;
        const response = await axios.post(`/admin/periods/${periodId}/toggle-active`);
        
        // Actualizar el periodo en la lista
        const index = periods.value.findIndex(p => p.id === periodId);
        if (index !== -1) {
            periods.value[index] = response.data.period;
        }
        
        // Recargar todos los periodos para actualizar el estado de todos
        await loadPeriods();
    } catch (error) {
        console.error('Error activando periodo:', error);
        alert('Error al activar el periodo: ' + (error.response?.data?.message || 'Error desconocido'));
    } finally {
        togglingActive.value = null;
    }
};

// Formatear fecha
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-MX', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
};

// Resetear formulario
const resetForm = () => {
    newPeriod.value = {
        name: '',
        code: '',
        start_date: '',
        end_date: '',
        is_open_for_grades: true,
    };
};

onMounted(() => {
    loadPeriods();
});
</script>

<template>
    <Head title="Gestión de Periodos Académicos - Administrador UTM" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                
                <!-- Header -->
                <div :class="['mb-8 rounded-2xl p-6 border shadow-lg', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 :class="['text-3xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Gestión de Periodos Académicos
                            </h1>
                            <p :class="['text-lg', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                Administrar periodos académicos del sistema
                            </p>
                        </div>
                        <button 
                            @click="showAddModal = true"
                            :class="['mt-4 md:mt-0 px-6 py-3 rounded-xl font-semibold transition-all hover:shadow-lg flex items-center gap-2', darkMode ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                        >
                            <PlusIcon class="w-5 h-5" />
                            Nuevo Periodo
                        </button>
                    </div>
                </div>

                <!-- Tabla de periodos -->
                <div :class="['rounded-2xl border shadow-lg overflow-hidden', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <!-- Loading state -->
                    <div v-if="loading" class="p-12 text-center">
                        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-blue-400' : 'border-blue-600'"></div>
                        <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando periodos...</p>
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="periods.length === 0" class="p-12 text-center">
                        <svg class="w-24 h-24 mx-auto mb-4 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <p :class="['text-lg font-semibold mb-4', darkMode ? 'text-gray-400' : 'text-gray-600']">
                            No hay periodos registrados
                        </p>
                        <button 
                            @click="showAddModal = true"
                            :class="['px-6 py-3 rounded-xl font-semibold transition-all flex items-center gap-2 mx-auto', darkMode ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                        >
                            <PlusIcon class="w-5 h-5" />
                            Crear Primer Periodo
                        </button>
                    </div>

                    <!-- Periods table -->
                    <div v-else class="overflow-x-auto">
                        <table class="w-full">
                            <thead :class="darkMode ? 'bg-gray-700' : 'bg-gray-100'">
                                <tr>
                                    <th :class="['px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-200' : 'text-gray-500']">
                                        Nombre
                                    </th>
                                    <th :class="['px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-200' : 'text-gray-500']">
                                        Código
                                    </th>
                                    <th :class="['px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-200' : 'text-gray-500']">
                                        Fechas
                                    </th>
                                    <th :class="['px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-200' : 'text-gray-500']">
                                        Estado
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y" :class="darkMode ? 'divide-gray-700' : 'divide-gray-200'">
                                <tr 
                                    v-for="period in periods" 
                                    :key="period.id"
                                    :class="['hover:bg-opacity-50 transition-colors', darkMode ? 'hover:bg-gray-700' : 'hover:bg-gray-50']"
                                >
                                    <td :class="['px-6 py-4 whitespace-nowrap', darkMode ? 'text-white' : 'text-gray-900']">
                                        <div class="font-semibold">{{ period.name }}</div>
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                        <span :class="['inline-flex items-center px-3 py-1 rounded-full text-sm font-medium', darkMode ? 'bg-gray-700 text-gray-300' : 'bg-gray-100 text-gray-700']">
                                            {{ period.code }}
                                        </span>
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                        <div class="text-sm">
                                            <div>{{ formatDate(period.start_date) }}</div>
                                            <div class="text-xs mt-1 opacity-75">hasta {{ formatDate(period.end_date) }}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <button
                                            @click="toggleActive(period.id)"
                                            :disabled="togglingActive === period.id"
                                            :class="[
                                                'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2',
                                                period.is_active 
                                                    ? (darkMode ? 'bg-green-500 focus:ring-green-500' : 'bg-green-500 focus:ring-green-500')
                                                    : (darkMode ? 'bg-gray-600 focus:ring-gray-500' : 'bg-gray-300 focus:ring-gray-400'),
                                                togglingActive === period.id ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                            ]"
                                        >
                                            <span
                                                :class="[
                                                    'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                                                    period.is_active ? 'translate-x-6' : 'translate-x-1'
                                                ]"
                                            ></span>
                                        </button>
                                        <div v-if="period.is_active" :class="['mt-2 text-xs font-medium', darkMode ? 'text-green-400' : 'text-green-600']">
                                            Activo
                                        </div>
                                        <div v-else :class="['mt-2 text-xs font-medium', darkMode ? 'text-gray-500' : 'text-gray-400']">
                                            Inactivo
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Modal Agregar Periodo -->
                <div 
                    v-if="showAddModal" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showAddModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    Nuevo Periodo Académico
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
                            <div class="space-y-6">
                                <!-- Nombre -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Nombre del Periodo *
                                    </label>
                                    <input
                                        v-model="newPeriod.name"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500']"
                                        placeholder="Ej: Enero - Abril 2026"
                                    >
                                </div>

                                <!-- Código -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Código *
                                    </label>
                                    <input
                                        v-model="newPeriod.code"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500']"
                                        placeholder="Ej: 2026-1"
                                    >
                                    <p :class="['mt-1 text-xs', darkMode ? 'text-gray-400' : 'text-gray-500']">
                                        Debe ser único (ej: año-semestre)
                                    </p>
                                </div>

                                <!-- Fecha de inicio -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Fecha de Inicio *
                                    </label>
                                    <input
                                        v-model="newPeriod.start_date"
                                        type="date"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
                                </div>

                                <!-- Fecha de fin -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Fecha de Fin *
                                    </label>
                                    <input
                                        v-model="newPeriod.end_date"
                                        type="date"
                                        :min="newPeriod.start_date"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
                                    <p :class="['mt-1 text-xs', darkMode ? 'text-gray-400' : 'text-gray-500']">
                                        Debe ser posterior a la fecha de inicio
                                    </p>
                                </div>

                                <!-- Abierto para calificaciones -->
                                <div class="flex items-center">
                                    <input
                                        v-model="newPeriod.is_open_for_grades"
                                        type="checkbox"
                                        :class="['w-4 h-4 rounded border', darkMode ? 'bg-gray-700 border-gray-600 text-blue-500' : 'bg-white border-gray-300 text-blue-600']"
                                    >
                                    <label :class="['ml-3 text-sm font-medium', darkMode ? 'text-white' : 'text-gray-900']">
                                        Abierto para calificaciones
                                    </label>
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
                                    @click="addPeriod"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                                >
                                    Crear Periodo
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

