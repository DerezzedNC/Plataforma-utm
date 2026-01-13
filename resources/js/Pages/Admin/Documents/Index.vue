<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Estados
const documents = ref([]);
const loading = ref(true);
const showDetailModal = ref(false);
const showHistoryModal = ref(false);
const selectedDocument = ref(null);
const searchQuery = ref('');
const filterEstado = ref('');
const showHistory = ref(false);

// Estados disponibles
const estados = [
    { value: '', label: 'Todos los estados' },
    { value: 'pendiente_revisar', label: 'Pendiente por Revisar' },
    { value: 'pagar_documentos', label: 'Pagar Documentos' },
    { value: 'en_proceso', label: 'En Proceso' },
    { value: 'listo_recoger', label: 'Listo para Recoger' },
    { value: 'finalizado', label: 'Finalizado' },
    { value: 'cancelado', label: 'Cancelado' },
];

// Formulario para cambiar estado
const statusForm = ref({
    estado: '',
    observaciones: '',
});

// Cargar documentos
const loadDocuments = async () => {
    try {
        loading.value = true;
        const endpoint = showHistory.value ? '/admin/documents/history' : '/admin/documents';
        const response = await axios.get(endpoint, {
            params: {
                estado: filterEstado.value || undefined,
            }
        });
        documents.value = response.data;
    } catch (error) {
        console.error('Error cargando documentos:', error);
        if (error.response) {
            console.error('Respuesta del servidor:', error.response.status, error.response.data);
        }
    } finally {
        loading.value = false;
    }
};

// Cargar historial
const loadHistory = async () => {
    showHistory.value = true;
    await loadDocuments();
};

// Volver a bandeja principal
const backToInbox = async () => {
    showHistory.value = false;
    filterEstado.value = '';
    await loadDocuments();
};

// Abrir modal de detalle
const openDetailModal = async (document) => {
    try {
        const response = await axios.get(`/admin/documents/${document.id}`);
        selectedDocument.value = response.data;
        statusForm.value = {
            estado: response.data.estado,
            observaciones: response.data.observaciones || '',
        };
        showDetailModal.value = true;
    } catch (error) {
        console.error('Error cargando documento:', error);
        alert('Error al cargar el documento');
    }
};

// Obtener siguiente estado disponible
const getNextStates = (currentEstado) => {
    const stateFlow = {
        'pendiente_revisar': ['pagar_documentos', 'en_proceso', 'cancelado'],
        'pagar_documentos': ['en_proceso', 'cancelado'],
        'en_proceso': ['listo_recoger', 'cancelado'],
        'listo_recoger': ['finalizado', 'cancelado'],
        'finalizado': [],
        'cancelado': [],
    };
    return stateFlow[currentEstado] || [];
};

// Actualizar estado del documento
const updateStatus = async () => {
    if (!selectedDocument.value || !statusForm.value.estado) {
        alert('Por favor selecciona un estado');
        return;
    }

    try {
        const response = await axios.put(`/admin/documents/${selectedDocument.value.id}/status`, {
            estado: statusForm.value.estado,
            observaciones: statusForm.value.observaciones || null,
        });

        selectedDocument.value = response.data;
        showDetailModal.value = false;
        await loadDocuments();
        
        // Si el estado es finalizado, mostrar mensaje
        if (statusForm.value.estado === 'finalizado') {
            alert('Documento marcado como finalizado. Ya no aparecerá en la bandeja principal.');
        }
    } catch (error) {
        console.error('Error actualizando estado:', error);
        alert('Error al actualizar el estado: ' + (error.response?.data?.message || 'Error desconocido'));
    }
};

// Cancelar documento
const cancelDocument = async () => {
    if (!selectedDocument.value) return;
    
    const motivo = prompt('Ingresa el motivo de la cancelación:');
    if (!motivo) return;

    try {
        await axios.post(`/admin/documents/${selectedDocument.value.id}/cancel`, {
            observaciones: motivo,
        });

        showDetailModal.value = false;
        await loadDocuments();
        alert('Documento cancelado exitosamente');
    } catch (error) {
        console.error('Error cancelando documento:', error);
        alert('Error al cancelar el documento: ' + (error.response?.data?.message || 'Error desconocido'));
    }
};

// Obtener color del estado
const getEstadoColor = (estado) => {
    const colors = {
        'pendiente_revisar': { bg: 'bg-yellow-100', text: 'text-yellow-800', darkBg: 'bg-yellow-500/20', darkText: 'text-yellow-400' },
        'pagar_documentos': { bg: 'bg-blue-100', text: 'text-blue-800', darkBg: 'bg-blue-500/20', darkText: 'text-blue-400' },
        'en_proceso': { bg: 'bg-purple-100', text: 'text-purple-800', darkBg: 'bg-purple-500/20', darkText: 'text-purple-400' },
        'listo_recoger': { bg: 'bg-green-100', text: 'text-green-800', darkBg: 'bg-green-500/20', darkText: 'text-green-400' },
        'finalizado': { bg: 'bg-gray-100', text: 'text-gray-800', darkBg: 'bg-gray-500/20', darkText: 'text-gray-400' },
        'cancelado': { bg: 'bg-red-100', text: 'text-red-800', darkBg: 'bg-red-500/20', darkText: 'text-red-400' },
    };
    return colors[estado] || colors['pendiente_revisar'];
};

// Obtener label del estado
const getEstadoLabel = (estado) => {
    const estadoObj = estados.find(e => e.value === estado);
    return estadoObj ? estadoObj.label : estado;
};

// Filtrar documentos
const filteredDocuments = computed(() => {
    let filtered = documents.value;

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(doc => {
            const studentName = doc.student?.name?.toLowerCase() || '';
            const matricula = doc.student?.matricula?.toLowerCase() || '';
            const tipoDocumento = doc.tipo_documento?.toLowerCase() || '';
            return studentName.includes(query) || 
                   matricula.includes(query) || 
                   tipoDocumento.includes(query);
        });
    }

    return filtered;
});

// Cargar documentos al montar
onMounted(() => {
    loadDocuments();
});

// Watch para filtro de estado
watch(filterEstado, () => {
    loadDocuments();
});
</script>

<template>
    <Head title="Gestión de Documentos - Administrador UTM" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                
                <!-- Header -->
                <div :class="['mb-8 rounded-2xl p-6 border shadow-lg', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 :class="['text-3xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                {{ showHistory ? 'Historial de Documentos' : 'Bandeja de Entrada' }}
                            </h1>
                            <p :class="['text-lg', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                {{ showHistory ? 'Todos los documentos procesados' : 'Gestiona las solicitudes de documentos de los estudiantes' }}
                            </p>
                        </div>
                        <div class="flex items-center space-x-3 mt-4 md:mt-0">
                            <button 
                                v-if="showHistory"
                                @click="backToInbox"
                                :class="['px-6 py-3 rounded-xl font-semibold transition-all hover:shadow-lg', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                            >
                                Volver a Bandeja
                            </button>
                            <button 
                                v-else
                                @click="loadHistory"
                                :class="['px-6 py-3 rounded-xl font-semibold transition-all hover:shadow-lg', darkMode ? 'bg-purple-600 hover:bg-purple-700 text-white' : 'bg-purple-500 hover:bg-purple-600 text-white']"
                            >
                                Ver Historial
                            </button>
                            <Link :href="route('dashboard-admin')" :class="['px-6 py-3 rounded-xl font-semibold transition-all hover:shadow-lg', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']">
                                Regresar
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div :class="['mb-6 rounded-xl border p-6', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex-1 relative">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar por nombre, matrícula o tipo de documento..."
                                :class="['w-full px-4 py-3 pl-12 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500']"
                            >
                            <svg class="w-5 h-5 absolute left-4 top-1/2 transform -translate-y-1/2" :class="darkMode ? 'text-gray-400' : 'text-gray-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <select
                                v-model="filterEstado"
                                :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                            >
                                <option v-for="estado in estados" :key="estado.value" :value="estado.value">
                                    {{ estado.label }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Lista de Documentos -->
                <div :class="['rounded-2xl border shadow-lg overflow-hidden', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <!-- Loading state -->
                    <div v-if="loading" class="p-12 text-center">
                        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-orange-400' : 'border-orange-600'"></div>
                        <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando documentos...</p>
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="filteredDocuments.length === 0" class="p-12 text-center">
                        <svg class="w-24 h-24 mx-auto mb-4 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p :class="['text-lg font-semibold', darkMode ? 'text-gray-400' : 'text-gray-600']">
                            {{ searchQuery || filterEstado ? 'No se encontraron documentos' : (showHistory ? 'No hay documentos en el historial' : 'No hay documentos pendientes') }}
                        </p>
                    </div>

                    <!-- Documents list -->
                    <div v-else class="divide-y" :class="darkMode ? 'divide-gray-700' : 'divide-gray-200'">
                        <div 
                            v-for="document in filteredDocuments" 
                            :key="document.id"
                            @click="openDetailModal(document)"
                            :class="['p-6 cursor-pointer transition-all hover:bg-opacity-50', darkMode ? 'hover:bg-gray-700' : 'hover:bg-gray-50']"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-4 mb-2">
                                        <h3 :class="['text-lg font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                            {{ document.student?.name || 'Sin nombre' }}
                                        </h3>
                                        <span :class="['px-3 py-1 rounded-full text-xs font-semibold', getEstadoColor(document.estado).bg, getEstadoColor(document.estado).text, darkMode && getEstadoColor(document.estado).darkBg, darkMode && getEstadoColor(document.estado).darkText]">
                                            {{ getEstadoLabel(document.estado) }}
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-3">
                                        <div>
                                            <p :class="['text-xs font-medium', darkMode ? 'text-gray-400' : 'text-gray-600']">Matrícula</p>
                                            <p :class="['text-sm font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                                {{ document.student?.matricula || 'N/A' }}
                                            </p>
                                        </div>
                                        <div>
                                            <p :class="['text-xs font-medium', darkMode ? 'text-gray-400' : 'text-gray-600']">Grado</p>
                                            <p :class="['text-sm font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                                {{ document.student?.grado || 'N/A' }}
                                            </p>
                                        </div>
                                        <div>
                                            <p :class="['text-xs font-medium', darkMode ? 'text-gray-400' : 'text-gray-600']">Grupo</p>
                                            <p :class="['text-sm font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                                {{ document.student?.grupo || 'N/A' }}
                                            </p>
                                        </div>
                                        <div>
                                            <p :class="['text-xs font-medium', darkMode ? 'text-gray-400' : 'text-gray-600']">Tipo de Documento</p>
                                            <p :class="['text-sm font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                                {{ document.tipo_documento || 'N/A' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div v-if="document.motivo" class="mt-3">
                                        <p :class="['text-xs font-medium', darkMode ? 'text-gray-400' : 'text-gray-600']">Motivo</p>
                                        <p :class="['text-sm', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                            {{ document.motivo }}
                                        </p>
                                    </div>
                                    <div v-if="document.observaciones" class="mt-2">
                                        <p :class="['text-xs font-medium', darkMode ? 'text-gray-400' : 'text-gray-600']">Observaciones</p>
                                        <p :class="['text-sm', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                            {{ document.observaciones }}
                                        </p>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <svg class="w-6 h-6" :class="darkMode ? 'text-gray-400' : 'text-gray-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Detalle del Documento -->
                <div 
                    v-if="showDetailModal && selectedDocument" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showDetailModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                        Detalle de Solicitud
                                    </h2>
                                    <p :class="['text-sm mt-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                        {{ selectedDocument.tipo_documento }}
                                    </p>
                                </div>
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

                        <div class="p-6 space-y-6">
                            <!-- Información del Estudiante -->
                            <div>
                                <h3 :class="['text-lg font-bold mb-3', darkMode ? 'text-white' : 'text-gray-900']">
                                    Información del Estudiante
                                </h3>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p :class="['text-sm font-medium', darkMode ? 'text-gray-400' : 'text-gray-600']">Nombre Completo</p>
                                        <p :class="['text-base font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                            {{ selectedDocument.student?.name || 'N/A' }}
                                        </p>
                                    </div>
                                    <div>
                                        <p :class="['text-sm font-medium', darkMode ? 'text-gray-400' : 'text-gray-600']">Matrícula</p>
                                        <p :class="['text-base font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                            {{ selectedDocument.student?.matricula || 'N/A' }}
                                        </p>
                                    </div>
                                    <div>
                                        <p :class="['text-sm font-medium', darkMode ? 'text-gray-400' : 'text-gray-600']">Grado</p>
                                        <p :class="['text-base font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                            {{ selectedDocument.student?.grado || 'N/A' }}
                                        </p>
                                    </div>
                                    <div>
                                        <p :class="['text-sm font-medium', darkMode ? 'text-gray-400' : 'text-gray-600']">Grupo</p>
                                        <p :class="['text-base font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                            {{ selectedDocument.student?.grupo || 'N/A' }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Información de la Solicitud -->
                            <div>
                                <h3 :class="['text-lg font-bold mb-3', darkMode ? 'text-white' : 'text-gray-900']">
                                    Información de la Solicitud
                                </h3>
                                <div class="space-y-3">
                                    <div>
                                        <p :class="['text-sm font-medium', darkMode ? 'text-gray-400' : 'text-gray-600']">Tipo de Documento</p>
                                        <p :class="['text-base font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                            {{ selectedDocument.tipo_documento }}
                                        </p>
                                    </div>
                                    <div>
                                        <p :class="['text-sm font-medium', darkMode ? 'text-gray-400' : 'text-gray-600']">Estado Actual</p>
                                        <span :class="['inline-block px-3 py-1 rounded-full text-sm font-semibold', getEstadoColor(selectedDocument.estado).bg, getEstadoColor(selectedDocument.estado).text, darkMode && getEstadoColor(selectedDocument.estado).darkBg, darkMode && getEstadoColor(selectedDocument.estado).darkText]">
                                            {{ getEstadoLabel(selectedDocument.estado) }}
                                        </span>
                                    </div>
                                    <div v-if="selectedDocument.motivo">
                                        <p :class="['text-sm font-medium', darkMode ? 'text-gray-400' : 'text-gray-600']">Motivo</p>
                                        <p :class="['text-base', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                            {{ selectedDocument.motivo }}
                                        </p>
                                    </div>
                                    <div v-if="selectedDocument.observaciones">
                                        <p :class="['text-sm font-medium', darkMode ? 'text-gray-400' : 'text-gray-600']">Observaciones</p>
                                        <p :class="['text-base', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                            {{ selectedDocument.observaciones }}
                                        </p>
                                    </div>
                                    <div v-if="selectedDocument.solicitado_en">
                                        <p :class="['text-sm font-medium', darkMode ? 'text-gray-400' : 'text-gray-600']">Solicitado el</p>
                                        <p :class="['text-base', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                            {{ new Date(selectedDocument.solicitado_en).toLocaleString('es-ES') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Cambiar Estado -->
                            <div v-if="selectedDocument.estado !== 'finalizado' && selectedDocument.estado !== 'cancelado'">
                                <h3 :class="['text-lg font-bold mb-3', darkMode ? 'text-white' : 'text-gray-900']">
                                    Cambiar Estado
                                </h3>
                                <div class="space-y-4">
                                    <div>
                                        <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                            Nuevo Estado *
                                        </label>
                                        <select
                                            v-model="statusForm.estado"
                                            :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                        >
                                            <option value="">{{ getEstadoLabel(selectedDocument.estado) }} (actual)</option>
                                            <option v-for="nextState in getNextStates(selectedDocument.estado)" :key="nextState" :value="nextState">
                                                {{ getEstadoLabel(nextState) }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                            Observaciones
                                        </label>
                                        <textarea
                                            v-model="statusForm.observaciones"
                                            rows="3"
                                            :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                            placeholder="Agregar observaciones..."
                                        ></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 border-t flex justify-end space-x-3" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <button
                                v-if="selectedDocument.estado !== 'finalizado' && selectedDocument.estado !== 'cancelado'"
                                @click="cancelDocument"
                                :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-red-500 hover:bg-red-600 text-white']"
                            >
                                Cancelar Solicitud
                            </button>
                            <button
                                v-if="selectedDocument.estado !== 'finalizado' && selectedDocument.estado !== 'cancelado' && statusForm.estado"
                                @click="updateStatus"
                                :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-orange-600 hover:bg-orange-700 text-white' : 'bg-orange-500 hover:bg-orange-600 text-white']"
                            >
                                Actualizar Estado
                            </button>
                            <button
                                @click="showDetailModal = false"
                                :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                            >
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

