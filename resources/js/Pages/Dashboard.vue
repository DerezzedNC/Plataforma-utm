<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
// Heroicons - Outline version
import { 
    CameraIcon,
    BookOpenIcon,
    AcademicCapIcon,
    UserGroupIcon,
    PhoneIcon,
    MapPinIcon,
    CalendarIcon,
    PencilIcon
} from '@heroicons/vue/24/outline';

// Obtenemos el usuario autenticado
const props = defineProps({
    auth: Object,
});

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Obtener el periodo activo desde las props compartidas
const page = usePage();
const currentPeriod = computed(() => page.props.currentPeriod);

// Estados
const loading = ref(true);
const showEditModal = ref(false);
const saving = ref(false);

// Datos del estudiante (cargados desde la API)
const studentData = ref({
    matricula: '',
    nombre: props.auth?.user?.name || 'Estudiante',
    email: props.auth?.user?.email || '',
    carrera: '',
    grado: '',
    grupo: '',
    telefono: '',
    direccion: '',
    fechaNacimiento: '',
    foto: '/images/profile-placeholder.svg',
    promedio: 0.0,
    cuatrimestre: '',
    nivel: ''
});

// Formulario para editar información personal
const personalInfoForm = ref({
    telefono: '',
    direccion: '',
    fecha_nacimiento: '',
});

// Cargar datos del estudiante
const loadStudentData = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/student/profile');
        const data = response.data;
        
        studentData.value = {
            matricula: data.student_detail?.matricula || 'N/A',
            nombre: data.user?.name || 'Estudiante',
            email: data.user?.email || '',
            carrera: data.student_detail?.carrera || 'No asignada',
            grado: data.student_detail?.grado || data.student_detail?.group?.grado ? `${data.student_detail.group.grado}°` : 'N/A',
            grupo: data.student_detail?.grupo || data.student_detail?.group?.grupo || 'N/A',
            telefono: data.student_detail?.telefono || 'No registrado',
            direccion: data.student_detail?.direccion || 'No registrada',
            fechaNacimiento: data.student_detail?.fecha_nacimiento 
                ? new Date(data.student_detail.fecha_nacimiento).toLocaleDateString('es-ES')
                : 'No registrada',
            foto: data.student_detail?.foto_perfil || '/images/profile-placeholder.svg',
            promedio: parseFloat(data.student_detail?.promedio_general || 0).toFixed(2),
            cuatrimestre: getCurrentCuatrimestre(),
            nivel: data.student_detail?.grado || (data.student_detail?.group?.grado ? `${data.student_detail.group.grado}° Cuatrimestre` : 'N/A')
        };

        // Llenar formulario de edición
        personalInfoForm.value = {
            telefono: data.student_detail?.telefono || '',
            direccion: data.student_detail?.direccion || '',
            fecha_nacimiento: data.student_detail?.fecha_nacimiento || '',
        };
    } catch (error) {
        console.error('Error cargando datos del estudiante:', error);
        // Si hay error, mantener datos básicos del usuario
        studentData.value.nombre = props.auth?.user?.name || 'Estudiante';
        studentData.value.email = props.auth?.user?.email || '';
    } finally {
        loading.value = false;
    }
};

// Obtener cuatrimestre actual (formato: 2024-1)
const getCurrentCuatrimestre = () => {
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth() + 1;
    // Enero-Abril: 1, Mayo-Agosto: 2, Septiembre-Diciembre: 3
    let cuatrimestre = 1;
    if (month >= 5 && month <= 8) {
        cuatrimestre = 2;
    } else if (month >= 9) {
        cuatrimestre = 3;
    }
    return `${year}-${cuatrimestre}`;
};

// Abrir modal de edición
const openEditModal = () => {
    showEditModal.value = true;
};

// Cerrar modal de edición
const closeEditModal = () => {
    showEditModal.value = false;
};

// Guardar información personal
const savePersonalInfo = async () => {
    try {
        saving.value = true;
        const response = await axios.put('/student/profile/personal-info', personalInfoForm.value);
        
        // Actualizar datos locales
        studentData.value.telefono = response.data.student_detail?.telefono || 'No registrado';
        studentData.value.direccion = response.data.student_detail?.direccion || 'No registrada';
        studentData.value.fechaNacimiento = response.data.student_detail?.fecha_nacimiento
            ? new Date(response.data.student_detail.fecha_nacimiento).toLocaleDateString('es-ES')
            : 'No registrada';
        
        alert('Información personal actualizada exitosamente');
        closeEditModal();
    } catch (error) {
        console.error('Error actualizando información personal:', error);
        alert('Error al actualizar la información: ' + (error.response?.data?.message || 'Error desconocido'));
    } finally {
        saving.value = false;
    }
};

// Manejo de subida de foto
const handlePhotoUpload = async (event) => {
    const file = event.target.files[0];
    if (!file) {
        return;
    }

    // Validar tamaño (2MB máximo)
    if (file.size > 2 * 1024 * 1024) {
        alert('La imagen es demasiado grande. Por favor, selecciona una imagen de menos de 2MB.');
        return;
    }

    // Validar tipo de archivo
    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!validTypes.includes(file.type)) {
        alert('Formato de imagen no válido. Por favor, usa JPEG, PNG o GIF.');
        return;
    }

    try {
        // Crear FormData para enviar el archivo
        const formData = new FormData();
        formData.append('foto_perfil', file);

        const response = await axios.post('/student/profile/photo', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });

        // Actualizar la foto en los datos locales
        if (response.data.foto_perfil) {
            studentData.value.foto = response.data.foto_perfil;
            alert('Foto de perfil actualizada exitosamente');
        } else {
            alert('Foto actualizada, pero no se recibió la nueva ruta');
        }
    } catch (error) {
        console.error('Error actualizando foto:', error);
        const errorMessage = error.response?.data?.message || 
                           error.response?.data?.errors?.foto_perfil?.[0] || 
                           'Error al actualizar la foto';
        alert('Error: ' + errorMessage);
    }
};

// Cargar datos al montar
onMounted(() => {
    loadStudentData();
});
</script>

<template>
    <Head title="Portal Estudiantil - UTM" />

    <AuthenticatedLayout>
        <div class="min-h-screen py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                
                <!-- Header de Bienvenida -->
                <div :class="['mb-8 rounded-2xl p-6 border shadow-lg', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 :class="['font-heading text-3xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                {{ studentData.nombre }}
                            </h1>
                            <p :class="['font-body text-lg font-semibold', darkMode ? 'text-green-400' : 'text-green-600']">
                                {{ studentData.matricula }}
                            </p>
                        </div>
                        <div :class="['font-body mt-4 md:mt-0 px-4 py-2 rounded-lg inline-block', darkMode ? 'bg-green-600 text-white' : 'bg-green-100 text-green-800']">
                            <p class="text-sm font-semibold">Cuatrimestre Actual: {{ studentData.cuatrimestre }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <!-- Columna Izquierda - Información del Estudiante -->
                    <div class="lg:col-span-1 space-y-6">
                        
                        <!-- Tarjeta de Perfil con Foto -->
                        <div :class="['rounded-2xl shadow-lg border overflow-hidden', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                            <!-- Header con gradiente -->
                            <div class="bg-gradient-to-r from-green-500 to-green-600 h-24"></div>
                            
                            <!-- Foto de perfil -->
                            <div class="flex justify-center -mt-16 mb-4">
                                <div class="relative">
                                    <img 
                                        :src="studentData.foto" 
                                        :alt="studentData.nombre"
                                        class="w-32 h-32 rounded-full border-4 object-cover"
                                        :class="darkMode ? 'border-gray-800' : 'border-white'"
                                    />
                                    <label 
                                        for="photo-upload"
                                        :class="['absolute bottom-0 right-0 w-10 h-10 rounded-full flex items-center justify-center cursor-pointer transition-all hover:scale-110', darkMode ? 'bg-gray-700 hover:bg-gray-600' : 'bg-green-500 hover:bg-green-600']"
                                        title="Cambiar foto"
                                    >
                                        <CameraIcon class="w-5 h-5 text-white" />
                                    </label>
                                    <input 
                                        id="photo-upload" 
                                        type="file" 
                                        accept="image/*" 
                                        class="hidden" 
                                        @change="handlePhotoUpload"
                                    />
                                </div>
                            </div>

                            <!-- Información del estudiante -->
                            <div class="px-6 pb-6">
                                <h3 :class="['font-heading text-xl font-bold mb-1 text-center', darkMode ? 'text-white' : 'text-gray-900']">
                                    {{ studentData.nombre }}
                                </h3>
                                <p :class="['font-body text-center mb-4', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                    {{ studentData.email }}
                                </p>

                                <!-- Información Académica -->
                                <div :class="['rounded-lg p-4 mb-4', darkMode ? 'bg-gray-700' : 'bg-gray-50']">
                                    <h4 :class="['font-heading text-sm font-bold mb-3', darkMode ? 'text-white' : 'text-gray-900']">
                                        Información Académica
                                    </h4>
                                    <div class="space-y-2">
                                        <div class="flex items-center text-sm">
                                            <BookOpenIcon class="w-5 h-5 mr-2 flex-shrink-0" :class="darkMode ? 'text-green-400' : 'text-green-600'" />
                                            <span :class="['font-body', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                <strong>Carrera:</strong> {{ studentData.carrera }}
                                            </span>
                                        </div>
                                        <div class="flex items-center text-sm">
                                            <AcademicCapIcon class="w-5 h-5 mr-2 flex-shrink-0" :class="darkMode ? 'text-green-400' : 'text-green-600'" />
                                            <span :class="['font-body', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                <strong>Nivel:</strong> {{ studentData.nivel }}
                                            </span>
                                        </div>
                                        <div class="flex items-center text-sm">
                                            <UserGroupIcon class="w-5 h-5 mr-2 flex-shrink-0" :class="darkMode ? 'text-green-400' : 'text-green-600'" />
                                            <span :class="['font-body', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                <strong>Grupo:</strong> {{ studentData.grupo }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Información Personal -->
                                <div :class="['rounded-lg p-4', darkMode ? 'bg-gray-700' : 'bg-gray-50']">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 :class="['font-heading text-sm font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                            Información Personal
                                        </h4>
                                        <button
                                            @click="openEditModal"
                                            :class="['font-body px-3 py-1 text-xs rounded-lg font-medium transition-colors flex items-center gap-1', darkMode ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white']"
                                        >
                                            <PencilIcon class="w-3 h-3" />
                                            Editar
                                        </button>
                                    </div>
                                    <div class="space-y-2">
                                        <div class="flex items-center text-sm">
                                            <svg class="w-5 h-5 mr-2 flex-shrink-0" :class="darkMode ? 'text-blue-400' : 'text-blue-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                            </svg>
                                            <span :class="darkMode ? 'text-gray-300' : 'text-gray-700'">
                                                {{ studentData.telefono }}
                                            </span>
                                        </div>
                                        <div class="flex items-center text-sm">
                                            <svg class="w-5 h-5 mr-2 flex-shrink-0" :class="darkMode ? 'text-blue-400' : 'text-blue-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            </svg>
                                            <span :class="darkMode ? 'text-gray-300' : 'text-gray-700'">
                                                {{ studentData.direccion }}
                                            </span>
                                        </div>
                                        <div class="flex items-center text-sm">
                                            <svg class="w-5 h-5 mr-2 flex-shrink-0" :class="darkMode ? 'text-blue-400' : 'text-blue-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                            <span :class="darkMode ? 'text-gray-300' : 'text-gray-700'">
                                                {{ studentData.fechaNacimiento }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tarjeta de Estadísticas Rápidas -->
                        <div :class="['rounded-2xl shadow-lg border overflow-hidden', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                            <div class="px-6 py-4 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                                <h3 :class="['text-lg font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    Estadísticas Rápidas
                                </h3>
                            </div>
                            <div class="p-6 space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p :class="['text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">Promedio General</p>
                                        <p :class="['text-2xl font-bold', darkMode ? 'text-green-400' : 'text-green-600']">
                                            {{ studentData.promedio }}
                                        </p>
                                    </div>
                                    <div :class="['w-16 h-16 rounded-full flex items-center justify-center', darkMode ? 'bg-green-500/20' : 'bg-green-100']">
                                        <svg class="w-8 h-8" :class="darkMode ? 'text-green-400' : 'text-green-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha - Contenido Principal -->
                    <div class="lg:col-span-2 space-y-6">
                        
                        <!-- Información del Cuatrimestre Actual -->
                        <div :class="['rounded-2xl shadow-lg border overflow-hidden', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                            <div class="px-6 py-4 bg-gradient-to-r from-green-500 to-green-600">
                                <h2 :class="['text-xl font-bold text-white']">
                                    Información del Cuatrimestre Actual
                                </h2>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <p :class="['text-sm mb-1', darkMode ? 'text-gray-400' : 'text-gray-600']">Cuatrimestre Académico</p>
                                        <p :class="['text-lg font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                            {{ currentPeriod?.code || studentData.cuatrimestre || 'No asignado' }}
                                        </p>
                                    </div>
                                    <div>
                                        <p :class="['text-sm mb-1', darkMode ? 'text-gray-400' : 'text-gray-600']">Periodo</p>
                                        <p :class="['text-lg font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                            {{ currentPeriod?.name || 'No hay periodo activo' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-6 pt-6 border-t" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                                    <div class="flex items-start space-x-4">
                                        <div :class="['p-3 rounded-lg', darkMode ? 'bg-blue-500/20' : 'bg-blue-100']">
                                            <svg class="w-6 h-6" :class="darkMode ? 'text-blue-400' : 'text-blue-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </div>
                                        <div class="flex-1">
                                            <h4 :class="['font-semibold mb-1', darkMode ? 'text-white' : 'text-gray-900']">
                                                Fecha de Inscripción
                                            </h4>
                                            <p :class="['text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                                Del 5 de febrero al 15 de febrero de 2024
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Grid de Accesos Rápidos -->
                        <div>
                            <h3 :class="['text-xl font-bold mb-4', darkMode ? 'text-white' : 'text-gray-900']">
                                Accesos Rápidos
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                
                                <!-- Cursos Extra -->
                                <Link :href="route('cursos-extra')" :class="['rounded-xl border hover:shadow-lg transition-all duration-300 group', darkMode ? 'bg-gray-800 border-gray-600 hover:border-green-500' : 'bg-white border-gray-200 hover:border-green-500']">
                                    <div class="p-6">
                                        <div :class="['w-12 h-12 rounded-lg flex items-center justify-center mb-4', darkMode ? 'bg-green-500/20 group-hover:bg-green-500/30' : 'bg-green-100 group-hover:bg-green-200']">
                                            <svg class="w-6 h-6" :class="darkMode ? 'text-green-400' : 'text-green-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                            </svg>
                                        </div>
                                        <h4 :class="['text-lg font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                            Cursos Extra
                                        </h4>
                                        <p :class="['text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                            Cursos internos y externos disponibles
                                        </p>
                                    </div>
                                </Link>

                                <!-- Horario -->
                                <Link :href="route('consultar-horario')" :class="['rounded-xl border hover:shadow-lg transition-all duration-300 group', darkMode ? 'bg-gray-800 border-gray-600 hover:border-green-500' : 'bg-white border-gray-200 hover:border-green-500']">
                                    <div class="p-6">
                                        <div :class="['w-12 h-12 rounded-lg flex items-center justify-center mb-4', darkMode ? 'bg-green-500/20 group-hover:bg-green-500/30' : 'bg-green-100 group-hover:bg-green-200']">
                                            <svg class="w-6 h-6" :class="darkMode ? 'text-green-400' : 'text-green-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </div>
                                        <h4 :class="['text-lg font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                            Horario
                                        </h4>
                                        <p :class="['text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                            Consulta tu horario de clases
                                        </p>
                                    </div>
                                </Link>

                                <!-- Historial Académico -->
                                <Link :href="route('historial-academico')" :class="['rounded-xl border hover:shadow-lg transition-all duration-300 group', darkMode ? 'bg-gray-800 border-gray-600 hover:border-green-500' : 'bg-white border-gray-200 hover:border-green-500']">
                                    <div class="p-6">
                                        <div :class="['w-12 h-12 rounded-lg flex items-center justify-center mb-4', darkMode ? 'bg-green-500/20 group-hover:bg-green-500/30' : 'bg-green-100 group-hover:bg-green-200']">
                                            <svg class="w-6 h-6" :class="darkMode ? 'text-green-400' : 'text-green-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                            </svg>
                                        </div>
                                        <h4 :class="['text-lg font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                            Historial Académico
                                        </h4>
                                        <p :class="['text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                            Consulta tus calificaciones
                                        </p>
                                    </div>
                                </Link>

                                <!-- Procesos Administrativos -->
                                <Link :href="route('procesos-administrativos')" :class="['rounded-xl border hover:shadow-lg transition-all duration-300 group', darkMode ? 'bg-gray-800 border-gray-600 hover:border-green-500' : 'bg-white border-gray-200 hover:border-green-500']">
                                    <div class="p-6">
                                        <div :class="['w-12 h-12 rounded-lg flex items-center justify-center mb-4', darkMode ? 'bg-green-500/20 group-hover:bg-green-500/30' : 'bg-green-100 group-hover:bg-green-200']">
                                            <svg class="w-6 h-6" :class="darkMode ? 'text-green-400' : 'text-green-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            </svg>
                                        </div>
                                        <h4 :class="['text-lg font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                            Trámites
                                        </h4>
                                        <p :class="['text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                            Procesos administrativos
                                        </p>
                                    </div>
                                </Link>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-12 text-center">
                    <p :class="['text-sm', darkMode ? 'text-gray-500' : 'text-gray-400']">
                        © 2024 UTM - Desarrollado por Angel Noh y Mauricio Chale del 4-E
                    </p>
                </div>

            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div :class="['rounded-2xl p-8', darkMode ? 'bg-gray-800' : 'bg-white']">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-green-400' : 'border-green-600'"></div>
                <p :class="['mt-4 text-center', darkMode ? 'text-white' : 'text-gray-900']">Cargando información...</p>
            </div>
        </div>

        <!-- Modal para Editar Información Personal -->
        <div 
            v-if="showEditModal" 
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
            @click.self="closeEditModal"
        >
            <div :class="['rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                    <div class="flex items-center justify-between">
                        <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                            Editar Información Personal
                        </h2>
                        <button 
                            @click="closeEditModal"
                            :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-gray-400' : 'hover:bg-gray-100 text-gray-600']"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="p-6 space-y-6">
                    <!-- Teléfono -->
                    <div>
                        <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                            Teléfono
                        </label>
                        <input
                            v-model="personalInfoForm.telefono"
                            type="text"
                            placeholder="Ej: +52 998 123 4567"
                            :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500']"
                        >
                    </div>

                    <!-- Dirección -->
                    <div>
                        <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                            Dirección
                        </label>
                        <input
                            v-model="personalInfoForm.direccion"
                            type="text"
                            placeholder="Ej: Mérida, Yucatán"
                            :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500']"
                        >
                    </div>

                    <!-- Fecha de Nacimiento -->
                    <div>
                        <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                            Fecha de Nacimiento
                        </label>
                        <input
                            v-model="personalInfoForm.fecha_nacimiento"
                            type="date"
                            :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                        >
                    </div>
                </div>

                <div class="p-6 border-t flex justify-end space-x-3" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                    <button
                        @click="closeEditModal"
                        :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="savePersonalInfo"
                        :disabled="saving"
                        :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? (saving ? 'bg-gray-600 text-gray-400 cursor-not-allowed' : 'bg-green-600 hover:bg-green-700 text-white') : (saving ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-green-500 hover:bg-green-600 text-white')]"
                    >
                        {{ saving ? 'Guardando...' : 'Guardar Cambios' }}
                    </button>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>