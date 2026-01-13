<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
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
const students = ref([]);
const loading = ref(true);
const showAddModal = ref(false);
const showEditModal = ref(false);
const selectedStudent = ref(null);
const searchQuery = ref('');

// Datos de áreas y carreras
const areas = ref([]);
const careers = ref([]);

// Formulario para agregar estudiante
const newStudent = ref({
    name: '',
    apellido_paterno: '',
    apellido_materno: '',
    matricula: '',
    email: '',
    password: '',
    password_confirmation: '',
    area_id: '',
    carrera: '',
});

// Formulario para editar estudiante
const editStudent = ref({
    id: null,
    name: '',
    apellido_paterno: '',
    apellido_materno: '',
    matricula: '',
    email: '',
    area_id: '',
    carrera: '',
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

// Cargar carreras según el área seleccionada
const loadCareers = async (areaId) => {
    if (!areaId) {
        careers.value = [];
        return;
    }
    try {
        const response = await axios.get('/admin/careers', { params: { area_id: areaId } });
        careers.value = response.data;
    } catch (error) {
        console.error('Error cargando carreras:', error);
    }
};

// Watch para área en formulario de agregar
watch(() => newStudent.value.area_id, (newAreaId) => {
    newStudent.value.carrera = '';
    loadCareers(newAreaId);
});

// Watch para generar correo automáticamente cuando se ingrese la matrícula
watch(() => newStudent.value.matricula, (newMatricula) => {
    if (newMatricula && newMatricula.trim() !== '') {
        // Generar correo automáticamente: {matricula}@alumno.utmetropolitana.edu.mx
        newStudent.value.email = `${newMatricula.trim()}@alumno.utmetropolitana.edu.mx`;
    } else {
        newStudent.value.email = '';
    }
});

// Watch para área en formulario de editar
watch(() => editStudent.value.area_id, (newAreaId) => {
    if (newAreaId) {
        editStudent.value.carrera = '';
        loadCareers(newAreaId);
    }
});


// Cargar estudiantes
const loadStudents = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/admin/students');
        students.value = response.data;
    } catch (error) {
        console.error('Error cargando estudiantes:', error);
        if (error.response) {
            console.error('Respuesta del servidor:', error.response.status, error.response.data);
        }
    } finally {
        loading.value = false;
    }
};

// Agregar nuevo estudiante
const addStudent = async () => {
    try {
        const fullName = `${newStudent.value.apellido_paterno} ${newStudent.value.apellido_materno} ${newStudent.value.name}`.trim();
        
        // El email se genera automáticamente en el backend usando la matrícula
        // Solo lo enviamos si el usuario lo modificó manualmente (aunque no debería ser necesario)
        const payload = {
            name: fullName,
            password: newStudent.value.password,
            password_confirmation: newStudent.value.password_confirmation,
            matricula: newStudent.value.matricula,
            carrera: newStudent.value.carrera,
        };
        
        // Solo agregar email si fue modificado manualmente (opcional)
        if (newStudent.value.email && !newStudent.value.email.includes(newStudent.value.matricula)) {
            payload.email = newStudent.value.email;
        }
        
        const response = await axios.post('/admin/students', payload);

        students.value.push(response.data);
        showAddModal.value = false;
        resetForm();
        await loadStudents(); // Recargar la lista
    } catch (error) {
        console.error('Error agregando estudiante:', error);
        if (error.response) {
            console.error('Respuesta del servidor:', error.response.status, error.response.data);
            const errorMessage = error.response.data?.message || 
                                (error.response.data?.errors ? JSON.stringify(error.response.data.errors) : 'Error desconocido');
            alert('Error al agregar estudiante: ' + errorMessage);
        } else {
            alert('Error al agregar estudiante: ' + (error.message || 'Error desconocido'));
        }
    }
};

// Editar estudiante
const openEditModal = async (student) => {
    selectedStudent.value = student;
    
    // Buscar la carrera para obtener su área
    let areaId = '';
    const carreraNombre = student.student_detail?.carrera || '';
    if (carreraNombre) {
        try {
            const careersResponse = await axios.get('/admin/careers');
            const allCareers = careersResponse.data;
            const career = allCareers.find(c => c.nombre === carreraNombre);
            if (career) {
                areaId = career.area_id;
                await loadCareers(areaId);
            }
        } catch (error) {
            console.error('Error cargando carrera para editar:', error);
        }
    }
    
    // Separar nombre completo en partes
    const nameParts = student.name.split(' ');
    editStudent.value = {
        id: student.id,
        apellido_paterno: nameParts[0] || '',
        apellido_materno: nameParts[1] || '',
        name: nameParts.slice(2).join(' ') || '',
        matricula: student.student_detail?.matricula || '',
        email: student.email,
        area_id: areaId,
        carrera: carreraNombre,
    };
    showEditModal.value = true;
};

const updateStudent = async () => {
    try {
        const fullName = `${editStudent.value.apellido_paterno} ${editStudent.value.apellido_materno} ${editStudent.value.name}`.trim();
        
        const response = await axios.put(`/admin/students/${editStudent.value.id}`, {
            name: fullName,
            email: editStudent.value.email,
            matricula: editStudent.value.matricula,
            carrera: editStudent.value.carrera,
        });

        const index = students.value.findIndex(s => s.id === editStudent.value.id);
        if (index !== -1) {
            students.value[index] = response.data;
        }
        showEditModal.value = false;
        await loadStudents(); // Recargar la lista
    } catch (error) {
        console.error('Error actualizando estudiante:', error);
        alert('Error al actualizar estudiante: ' + (error.response?.data?.message || 'Error desconocido'));
    }
};

// Eliminar estudiante
const deleteStudent = async (student) => {
    if (!confirm(`¿Estás seguro de eliminar a ${student.name}?`)) {
        return;
    }

    try {
        await axios.delete(`/admin/students/${student.id}`);
        students.value = students.value.filter(s => s.id !== student.id);
    } catch (error) {
        console.error('Error eliminando estudiante:', error);
        alert('Error al eliminar estudiante');
    }
};

// Resetear formulario
const resetForm = () => {
    newStudent.value = {
        name: '',
        apellido_paterno: '',
        apellido_materno: '',
        matricula: '',
        email: '',
        password: '',
        password_confirmation: '',
        area_id: '',
        carrera: '',
    };
    careers.value = [];
};

// Filtrar estudiantes
const filteredStudents = () => {
    if (!searchQuery.value) return students.value;
    const query = searchQuery.value.toLowerCase();
    return students.value.filter(student => 
        student.name.toLowerCase().includes(query) ||
        student.student_detail?.matricula?.toLowerCase().includes(query) ||
        student.email.toLowerCase().includes(query)
    );
};

onMounted(() => {
    loadAreas();
    loadStudents();
});
</script>

<template>
    <Head title="Gestión de Estudiantes - Administrador UTM" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                
                <!-- Header -->
                <div :class="['mb-8 rounded-2xl p-6 border shadow-lg', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 :class="['font-heading text-3xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Gestión de Estudiantes
                            </h1>
                            <p :class="['font-body text-lg', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                Administrar información de todos los estudiantes
                            </p>
                        </div>
                        <button 
                            @click="showAddModal = true"
                            :class="['font-body mt-4 md:mt-0 px-6 py-3 rounded-lg font-semibold transition-all hover:shadow-lg flex items-center gap-2', darkMode ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white']"
                        >
                            <PlusIcon class="w-5 h-5" />
                            Nuevo Estudiante
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
                                placeholder="Buscar por nombre, matrícula o correo..."
                                :class="['font-body w-full px-4 py-3 pl-12 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-green-500 focus:border-green-500', darkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400 focus:ring-green-400' : 'bg-white text-gray-900 placeholder-gray-500']"
                            >
                            <MagnifyingGlassIcon class="w-5 h-5 absolute left-4 top-1/2 transform -translate-y-1/2" :class="darkMode ? 'text-gray-400' : 'text-gray-500'" />
                        </div>
                        <div :class="['font-body px-4 py-3 rounded-lg font-medium', darkMode ? 'bg-gray-700 text-white' : 'bg-gray-100 text-gray-900']">
                            Total: {{ filteredStudents().length }}
                        </div>
                    </div>
                </div>

                <!-- Tabla de estudiantes -->
                <div :class="['rounded-2xl border shadow-lg overflow-hidden', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <!-- Loading state -->
                    <div v-if="loading" class="p-12 text-center">
                        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-green-400' : 'border-green-600'"></div>
                        <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando estudiantes...</p>
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="filteredStudents().length === 0" class="p-12 text-center">
                        <UserGroupIcon class="w-24 h-24 mx-auto mb-4 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" />
                        <p :class="['font-heading text-lg font-semibold', darkMode ? 'text-gray-400' : 'text-gray-600']">
                            {{ searchQuery ? 'No se encontraron estudiantes' : 'No hay estudiantes registrados' }}
                        </p>
                        <button 
                            v-if="!searchQuery"
                            @click="showAddModal = true"
                            :class="['font-body mt-4 px-6 py-3 rounded-lg font-semibold transition-all flex items-center gap-2 mx-auto', darkMode ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white']"
                        >
                            <PlusIcon class="w-5 h-5" />
                            Agregar Primer Estudiante
                        </button>
                    </div>

                    <!-- Students table -->
                    <div v-else class="overflow-x-auto">
                        <table class="w-full">
                            <thead :class="darkMode ? 'bg-gray-700' : 'bg-gray-50'">
                                <tr>
                                    <th :class="['font-body px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-300' : 'text-gray-500']">
                                        Matrícula
                                    </th>
                                    <th :class="['font-body px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-300' : 'text-gray-500']">
                                        Nombre Completo
                                    </th>
                                    <th :class="['font-body px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-300' : 'text-gray-500']">
                                        Carrera
                                    </th>
                                    <th :class="['font-body px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-300' : 'text-gray-500']">
                                        Grupo
                                    </th>
                                    <th :class="['font-body px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-300' : 'text-gray-500']">
                                        Grado
                                    </th>
                                    <th :class="['font-body px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-300' : 'text-gray-500']">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y" :class="darkMode ? 'divide-gray-700 bg-gray-800' : 'divide-gray-200 bg-white'">
                                <tr 
                                    v-for="student in filteredStudents()" 
                                    :key="student.id"
                                    :class="['font-body transition-colors', darkMode ? 'hover:bg-gray-700/50' : 'hover:bg-gray-50']"
                                >
                                    <td :class="['px-6 py-4 whitespace-nowrap text-sm font-medium', darkMode ? 'text-green-400' : 'text-green-600']">
                                        {{ student.student_detail?.matricula || 'N/A' }}
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap text-sm', darkMode ? 'text-white' : 'text-gray-900']">
                                        {{ student.name }}
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap text-sm', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                        {{ student.student_detail?.carrera || 'N/A' }}
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap']">
                                        <span :class="['font-body inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold', darkMode ? 'bg-green-500/20 text-green-400' : 'bg-green-100 text-green-800']">
                                            {{ student.student_detail?.grupo || 'N/A' }}
                                        </span>
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap text-sm', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                        {{ student.student_detail?.grado || 'N/A' }}
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap text-center text-sm font-medium']">
                                        <div class="flex items-center justify-center space-x-2">
                                            <button
                                                @click="openEditModal(student)"
                                                :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-green-400' : 'hover:bg-green-50 text-green-600']"
                                                title="Editar"
                                            >
                                                <PencilIcon class="w-5 h-5" />
                                            </button>
                                            <button
                                                @click="deleteStudent(student)"
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

                <!-- Modal Agregar Estudiante -->
                <div 
                    v-if="showAddModal" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showAddModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <h2 :class="['font-heading text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    Nuevo Estudiante
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
                                        v-model="newStudent.apellido_paterno"
                                        type="text"
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-green-500 focus:border-green-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-green-400' : 'bg-white text-gray-900']"
                                        placeholder="González"
                                    >
                                </div>

                                <!-- Apellido Materno -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Apellido Materno *
                                    </label>
                                    <input
                                        v-model="newStudent.apellido_materno"
                                        type="text"
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-green-500 focus:border-green-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-green-400' : 'bg-white text-gray-900']"
                                        placeholder="López"
                                    >
                                </div>

                                <!-- Nombres -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Nombres *
                                    </label>
                                    <input
                                        v-model="newStudent.name"
                                        type="text"
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-green-500 focus:border-green-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-green-400' : 'bg-white text-gray-900']"
                                        placeholder="Juan Carlos"
                                    >
                                </div>

                                <!-- Matrícula -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Matrícula *
                                    </label>
                                    <input
                                        v-model="newStudent.matricula"
                                        type="text"
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-green-500 focus:border-green-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-green-400' : 'bg-white text-gray-900']"
                                        placeholder="2024001234"
                                    >
                                </div>

                                <!-- Correo Institucional (Generado automáticamente) -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Correo Institucional *
                                        <span class="font-normal text-xs text-gray-500 ml-2">(Generado automáticamente)</span>
                                    </label>
                                    <input
                                        v-model="newStudent.email"
                                        type="email"
                                        readonly
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 cursor-not-allowed', darkMode ? 'bg-gray-800 border-gray-600 text-gray-400' : 'bg-gray-100 text-gray-600']"
                                        placeholder="Se generará automáticamente al ingresar la matrícula"
                                    >
                                    <p class="font-body mt-1 text-xs" :class="darkMode ? 'text-gray-400' : 'text-gray-500'">
                                        Formato: {matrícula}@alumno.utmetropolitana.edu.mx
                                    </p>
                                </div>

                                <!-- Contraseña -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Contraseña *
                                    </label>
                                    <input
                                        v-model="newStudent.password"
                                        type="password"
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-green-500 focus:border-green-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-green-400' : 'bg-white text-gray-900']"
                                        placeholder="••••••••"
                                    >
                                </div>

                                <!-- Confirmar Contraseña -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Confirmar Contraseña *
                                    </label>
                                    <input
                                        v-model="newStudent.password_confirmation"
                                        type="password"
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-green-500 focus:border-green-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-green-400' : 'bg-white text-gray-900']"
                                        placeholder="••••••••"
                                    >
                                </div>

                                <!-- Área de Estudio -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Área de Estudio *
                                    </label>
                                    <select
                                        v-model="newStudent.area_id"
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-green-500 focus:border-green-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-green-400' : 'bg-white text-gray-900']"
                                    >
                                        <option value="">Seleccione un área de estudio</option>
                                        <option v-for="area in areas" :key="area.id" :value="area.id">
                                            {{ area.nombre }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Carrera -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Carrera *
                                    </label>
                                    <select
                                        v-model="newStudent.carrera"
                                        :disabled="!newStudent.area_id || careers.length === 0"
                                        :class="['font-body w-full px-4 py-3 rounded-lg border border-gray-300 transition-all', darkMode ? (newStudent.area_id && careers.length > 0 ? 'bg-gray-700 border-gray-600 text-white focus:ring-2 focus:ring-green-400 focus:border-green-400' : 'bg-gray-800 border-gray-700 text-gray-500 cursor-not-allowed') : (newStudent.area_id && careers.length > 0 ? 'bg-white text-gray-900 focus:ring-2 focus:ring-green-500 focus:border-green-500' : 'bg-gray-100 text-gray-500 cursor-not-allowed')]"
                                    >
                                        <option value="">{{ newStudent.area_id ? (careers.length === 0 ? 'No hay carreras disponibles' : 'Seleccione una carrera') : 'Primero seleccione un área' }}</option>
                                        <option v-for="career in careers" :key="career.id" :value="career.nombre">
                                            {{ career.nombre }}
                                        </option>
                                    </select>
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
                                    @click="addStudent"
                                    :class="['font-body px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white']"
                                >
                                    Agregar Estudiante
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Editar Estudiante -->
                <div 
                    v-if="showEditModal" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showEditModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    Editar Estudiante
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
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Mismos campos que agregar, pero para editar -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Apellido Paterno *
                                    </label>
                                    <input
                                        v-model="editStudent.apellido_paterno"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
                                </div>

                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Apellido Materno *
                                    </label>
                                    <input
                                        v-model="editStudent.apellido_materno"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
                                </div>

                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Nombres *
                                    </label>
                                    <input
                                        v-model="editStudent.name"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
                                </div>

                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Matrícula *
                                    </label>
                                    <input
                                        v-model="editStudent.matricula"
                                        type="text"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
                                </div>

                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Correo Institucional *
                                    </label>
                                    <input
                                        v-model="editStudent.email"
                                        type="email"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
                                </div>

                                  <!-- Área de Estudio -->
                                  <div>
                                      <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                          Área de Estudio *
                                      </label>
                                      <select
                                          v-model="editStudent.area_id"
                                          :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                      >
                                          <option value="">Seleccione un área de estudio</option>
                                          <option v-for="area in areas" :key="area.id" :value="area.id">
                                              {{ area.nombre }}
                                          </option>
                                      </select>
                                  </div>

                                  <!-- Carrera -->
                                  <div>
                                      <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                          Carrera *
                                      </label>
                                      <select
                                          v-model="editStudent.carrera"
                                          :disabled="!editStudent.area_id || careers.length === 0"
                                          :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? (editStudent.area_id && careers.length > 0 ? 'bg-gray-700 border-gray-600 text-white' : 'bg-gray-800 border-gray-700 text-gray-500 cursor-not-allowed') : (editStudent.area_id && careers.length > 0 ? 'bg-white border-gray-300 text-gray-900' : 'bg-gray-100 border-gray-300 text-gray-500 cursor-not-allowed')]"
                                      >
                                          <option value="">{{ editStudent.area_id ? (careers.length === 0 ? 'No hay carreras disponibles' : 'Seleccione una carrera') : 'Primero seleccione un área' }}</option>
                                          <option v-for="career in careers" :key="career.id" :value="career.nombre">
                                              {{ career.nombre }}
                                          </option>
                                      </select>
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
                                    @click="updateStudent"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                                >
                                    Actualizar Estudiante
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
