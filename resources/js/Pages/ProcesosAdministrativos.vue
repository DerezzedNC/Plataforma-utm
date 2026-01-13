<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, onMounted } from 'vue';
import axios from 'axios';
// Heroicons - Outline version
import { 
    ClockIcon,
    CheckCircleIcon,
    DocumentTextIcon,
    ArrowPathIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Estados reactivos
const tabActiva = ref('solicitar');
const loading = ref(false);
const showModal = ref(false);
const selectedDocument = ref(null);

// Documentos disponibles
const documentos = ref([
    {
        nombre: 'Constancia de Estudios',
        descripcion: 'Documento que certifica tu estatus como estudiante activo',
        tiempoEntrega: '2-3 días hábiles',
        requisitos: [
            'Estar inscrito en el cuatrimestre actual',
            'No tener adeudos'
        ]
    },
    {
        nombre: 'Historial Académico (Kardex)',
        descripcion: 'Documento oficial con todas las calificaciones',
        tiempoEntrega: '1-2 días hábiles',
        requisitos: [
            'Solicitud firmada'
        ]
    },
    {
        nombre: 'Boleta de Calificaciones',
        descripcion: 'Boleta oficial del semestre actual',
        tiempoEntrega: '1-2 días hábiles',
        requisitos: [
            'Estar inscrito en el semestre actual'
        ]
    },
    {
        nombre: 'Constancia de Inscripción',
        descripcion: 'Documento que certifica tu inscripción en el semestre actual',
        tiempoEntrega: '1 día hábil',
        requisitos: [
            'Estar inscrito en el semestre actual'
        ]
    },
    {
        nombre: 'Constancia de No Adeudo',
        descripcion: 'Documento que certifica que no tienes adeudos',
        tiempoEntrega: '1 día hábil',
        requisitos: [
            'No tener adeudos pendientes'
        ]
    }
]);

// Solicitudes del usuario (datos reales)
const misSolicitudes = ref([]);
const loadingSolicitudes = ref(false);

// Formulario para solicitar documento
const solicitudForm = ref({
    tipo_documento: '',
    motivo: '',
});

// Cargar solicitudes del estudiante
const loadSolicitudes = async () => {
    try {
        loadingSolicitudes.value = true;
        const response = await axios.get('/student/documents');
        misSolicitudes.value = response.data;
    } catch (error) {
        console.error('Error cargando solicitudes:', error);
        if (error.response) {
            console.error('Respuesta del servidor:', error.response.status, error.response.data);
        }
    } finally {
        loadingSolicitudes.value = false;
    }
};

// Abrir modal para solicitar documento
const abrirModalSolicitud = (documento) => {
    selectedDocument.value = documento;
    solicitudForm.value = {
        tipo_documento: documento.nombre,
        motivo: '',
    };
    showModal.value = true;
};

// Cerrar modal
const cerrarModal = () => {
    showModal.value = false;
    selectedDocument.value = null;
    solicitudForm.value = {
        tipo_documento: '',
        motivo: '',
    };
};

// Solicitar documento
const solicitarDocumento = async () => {
    if (!solicitudForm.value.tipo_documento) {
        alert('Por favor selecciona un tipo de documento');
        return;
    }

    try {
        loading.value = true;
        const response = await axios.post('/student/documents', {
            tipo_documento: solicitudForm.value.tipo_documento,
            motivo: solicitudForm.value.motivo || null,
        });

        alert('Solicitud enviada exitosamente. El administrador la revisará pronto.');
        cerrarModal();
        await loadSolicitudes();
        
        // Cambiar a la pestaña de consultar para ver la nueva solicitud
        tabActiva.value = 'consultar';
    } catch (error) {
        console.error('Error enviando solicitud:', error);
        alert('Error al enviar la solicitud: ' + (error.response?.data?.message || 'Error desconocido'));
    } finally {
        loading.value = false;
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
    const labels = {
        'pendiente_revisar': 'Pendiente por Revisar',
        'pagar_documentos': 'Pagar Documentos',
        'en_proceso': 'En Proceso',
        'listo_recoger': 'Listo para Recoger',
        'finalizado': 'Finalizado',
        'cancelado': 'Cancelado',
    };
    return labels[estado] || estado;
};

// Formatear fecha
const formatFecha = (fecha) => {
    if (!fecha) return 'N/A';
    return new Date(fecha).toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

// Cargar solicitudes al montar
onMounted(() => {
    loadSolicitudes();
});
</script>

<template>
    <Head title="Procesos Administrativos - UTM" />

    <AuthenticatedLayout>
        <!-- Contenido Principal -->
        <div class="min-h-screen">
            <div class="py-8">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <!-- Header -->
                    <div class="text-center mb-8">
                        <h1 :class="['font-heading text-4xl font-bold mb-4', darkMode ? 'text-white' : 'text-gray-900']">
                            Procesos Administrativos
                        </h1>
                        <p :class="['font-body text-xl', darkMode ? 'text-gray-400' : 'text-gray-600']">
                            Solicita documentos y consulta el estado de tus trámites
                        </p>
                    </div>

                    <!-- Pestañas -->
                    <div class="mb-8">
                        <div class="flex justify-center">
                            <div :class="['flex rounded-lg p-1', darkMode ? 'bg-gray-700' : 'bg-gray-100']">
                                <button
                                    @click="tabActiva = 'solicitar'"
                                    :class="[
                                        'font-body px-6 py-3 rounded-lg font-medium transition-all duration-300',
                                        tabActiva === 'solicitar'
                                            ? (darkMode ? 'bg-green-500 text-white shadow-lg' : 'bg-green-500 text-white shadow-lg')
                                            : (darkMode ? 'text-gray-300 hover:text-white' : 'text-gray-600 hover:text-gray-900')
                                    ]"
                                >
                                    Solicitar Documentos
                                </button>
                                <button
                                    @click="tabActiva = 'consultar'"
                                    :class="[
                                        'font-body px-6 py-3 rounded-lg font-medium transition-all duration-300',
                                        tabActiva === 'consultar'
                                            ? (darkMode ? 'bg-green-500 text-white shadow-lg' : 'bg-green-500 text-white shadow-lg')
                                            : (darkMode ? 'text-gray-300 hover:text-white' : 'text-gray-600 hover:text-gray-900')
                                    ]"
                                >
                                    Consultar Solicitudes
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Contenido de Solicitar Documentos -->
                    <div v-if="tabActiva === 'solicitar'">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div
                                v-for="(documento, index) in documentos"
                                :key="index"
                                :class="['rounded-2xl shadow-xl border p-8 hover:shadow-2xl transition-all duration-300', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']"
                            >
                                <!-- Header del documento -->
                                <div class="mb-6">
                                    <h3 :class="['font-heading text-2xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        {{ documento.nombre }}
                                    </h3>
                                    <p :class="['font-body text-lg', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                        {{ documento.descripcion }}
                                    </p>
                                </div>

                                <!-- Información del documento -->
                                <div class="space-y-4 mb-6">
                                    <!-- Tiempo de entrega -->
                                    <div class="flex items-center">
                                        <ClockIcon :class="['w-5 h-5 mr-3', darkMode ? 'text-green-400' : 'text-green-600']" />
                                        <span :class="['font-body font-medium', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                            Tiempo de entrega: {{ documento.tiempoEntrega }}
                                        </span>
                                    </div>

                                    <!-- Requisitos -->
                                    <div>
                                        <div class="flex items-center mb-2">
                                            <CheckCircleIcon :class="['w-5 h-5 mr-3', darkMode ? 'text-green-400' : 'text-green-600']" />
                                            <span :class="['font-body font-medium', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                Requisitos:
                                            </span>
                                        </div>
                                        <ul class="ml-8 space-y-1">
                                            <li
                                                v-for="requisito in documento.requisitos"
                                                :key="requisito"
                                                :class="['font-body flex items-center text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']"
                                            >
                                                <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                                                {{ requisito }}
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Botón de solicitar -->
                                <button
                                    @click="abrirModalSolicitud(documento)"
                                    :class="['font-body w-full py-4 px-6 rounded-lg font-semibold text-lg transition-all duration-300', darkMode ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white']"
                                >
                                    Solicitar Documento
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Contenido de Consultar Solicitudes -->
                    <div v-if="tabActiva === 'consultar'">
                        <div :class="['rounded-2xl shadow-xl border p-8', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                            <!-- Header -->
                            <div class="flex items-center justify-between mb-8">
                                <div class="flex items-center">
                                    <DocumentTextIcon :class="['w-6 h-6 mr-3', darkMode ? 'text-green-400' : 'text-green-600']" />
                                    <h2 :class="['font-heading text-3xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                        Mis Solicitudes
                                    </h2>
                                </div>
                                <button
                                    @click="loadSolicitudes"
                                    :class="['font-body px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2', darkMode ? 'bg-gray-600 hover:bg-gray-700 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                                    :disabled="loadingSolicitudes"
                                >
                                    <ArrowPathIcon class="w-5 h-5" :class="{'animate-spin': loadingSolicitudes}" />
                                    Actualizar
                                </button>
                            </div>

                            <!-- Loading state -->
                            <div v-if="loadingSolicitudes" class="text-center py-12">
                                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-green-400' : 'border-green-600'"></div>
                                <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando solicitudes...</p>
                            </div>

                            <!-- Lista de solicitudes -->
                            <div v-else-if="misSolicitudes.length > 0" class="space-y-6">
                                <div
                                    v-for="solicitud in misSolicitudes"
                                    :key="solicitud.id"
                                    :class="['border rounded-xl p-6 hover:shadow-lg transition-all duration-300', darkMode ? 'border-gray-600 bg-gray-700' : 'border-gray-200 bg-gray-50']"
                                >
                                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                                        <div class="flex-1">
                                            <h3 :class="['font-heading text-xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                                {{ solicitud.tipo_documento }}
                                            </h3>
                                            <div class="space-y-2">
                                                <p :class="['font-body text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                                    <strong>Solicitado el:</strong> {{ formatFecha(solicitud.solicitado_en || solicitud.created_at) }}
                                                </p>
                                                <p v-if="solicitud.listo_en" :class="['text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                                    <strong>Listo el:</strong> {{ formatFecha(solicitud.listo_en) }}
                                                </p>
                                                <p v-if="solicitud.entregado_en" :class="['text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                                    <strong>Entregado el:</strong> {{ formatFecha(solicitud.entregado_en) }}
                                                </p>
                                                <p v-if="solicitud.motivo" :class="['text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                                    <strong>Motivo:</strong> {{ solicitud.motivo }}
                                                </p>
                                                <p v-if="solicitud.observaciones" :class="['text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                                    <strong>Observaciones:</strong> {{ solicitud.observaciones }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="mt-4 md:mt-0">
                                            <span :class="['font-body px-4 py-2 rounded-full text-sm font-medium', getEstadoColor(solicitud.estado).bg, getEstadoColor(solicitud.estado).text, darkMode && getEstadoColor(solicitud.estado).darkBg, darkMode && getEstadoColor(solicitud.estado).darkText]">
                                                {{ getEstadoLabel(solicitud.estado) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Estado vacío -->
                            <div v-else class="text-center py-16">
                                <DocumentTextIcon :class="['w-16 h-16 mx-auto mb-4', darkMode ? 'text-gray-500' : 'text-gray-400']" />
                                <h3 :class="['font-heading text-xl font-medium mb-2', darkMode ? 'text-gray-400' : 'text-gray-500']">
                                    No tienes solicitudes
                                </h3>
                                <p :class="['font-body text-base mb-4', darkMode ? 'text-gray-500' : 'text-gray-400']">
                                    Cuando hagas una solicitud de documento aparecerá aquí
                                </p>
                                <button
                                    @click="tabActiva = 'solicitar'"
                                    :class="['font-body px-6 py-3 rounded-lg font-medium transition-colors', darkMode ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white']"
                                >
                                    Solicitar Documento
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Modal para Solicitar Documento -->
                    <div 
                        v-if="showModal && selectedDocument" 
                        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                        @click.self="cerrarModal"
                    >
                        <div :class="['rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                            <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                                <div class="flex items-center justify-between">
                                    <h2 :class="['font-heading text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                        Solicitar Documento
                                    </h2>
                                    <button 
                                        @click="cerrarModal"
                                        :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-gray-400' : 'hover:bg-gray-100 text-gray-600']"
                                    >
                                        <XMarkIcon class="w-6 h-6" />
                                    </button>
                                </div>
                            </div>

                            <div class="p-6 space-y-6">
                                <!-- Información del documento -->
                                <div>
                                    <h3 :class="['font-heading text-lg font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        {{ selectedDocument.nombre }}
                                    </h3>
                                    <p :class="['font-body text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                        {{ selectedDocument.descripcion }}
                                    </p>
                                    <p :class="['font-body text-sm mt-2', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                        <strong>Tiempo de entrega:</strong> {{ selectedDocument.tiempoEntrega }}
                                    </p>
                                </div>

                                <!-- Formulario -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Motivo de la Solicitud (Opcional)
                                    </label>
                                    <textarea
                                        v-model="solicitudForm.motivo"
                                        rows="4"
                                        placeholder="Describe el motivo de tu solicitud..."
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-green-500 focus:border-green-500', darkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400 focus:ring-green-400' : 'bg-white text-gray-900 placeholder-gray-500']"
                                    ></textarea>
                                </div>

                                <!-- Requisitos -->
                                <div>
                                    <p :class="['font-body text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Requisitos:
                                    </p>
                                    <ul class="space-y-2">
                                        <li
                                            v-for="requisito in selectedDocument.requisitos"
                                            :key="requisito"
                                            class="flex items-start"
                                        >
                                            <CheckCircleIcon class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" :class="darkMode ? 'text-green-400' : 'text-green-600'" />
                                            <span :class="['font-body text-sm', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                {{ requisito }}
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="p-6 border-t flex justify-end space-x-3" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                                <button
                                    @click="cerrarModal"
                                    :class="['font-body px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-600 hover:bg-gray-700 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                                >
                                    Cancelar
                                </button>
                                <button
                                    @click="solicitarDocumento"
                                    :disabled="loading"
                                    :class="['font-body px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? (loading ? 'bg-gray-600 text-gray-400 cursor-not-allowed' : 'bg-green-500 hover:bg-green-600 text-white') : (loading ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-green-500 hover:bg-green-600 text-white')]"
                                >
                                    {{ loading ? 'Enviando...' : 'Enviar Solicitud' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Botón para regresar -->
                    <div class="mt-12 text-center">
                        <Link :href="route('dashboard')" :class="['font-body inline-flex items-center px-6 py-3 rounded-lg font-medium transition-colors gap-2', darkMode ? 'bg-gray-600 hover:bg-gray-700 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']">
                            <ArrowLeftIcon class="w-5 h-5" />
                            Regresar al Dashboard
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>