<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
// Heroicons - Outline version
import { 
    PlusIcon,
    MagnifyingGlassIcon,
    ClockIcon,
    PencilIcon,
    TrashIcon,
    XMarkIcon,
    BookOpenIcon
} from '@heroicons/vue/24/outline';

const { darkMode } = useDarkMode();

// Estados
const cursos = ref([]);
const areas = ref([]);
const loading = ref(false);
const showModal = ref(false);
const editingCourse = ref(null);
const searchQuery = ref('');
const filterType = ref('todos');

// Formulario
const formData = ref({
    tipo: 'interno',
    nombre: '',
    descripcion: '',
    tiempo_duracion: '',
    costo: null,
    link: '',
    aula: '',
    career_ids: [],
});

// Errores de validación
const errors = ref({});

// Cargar cursos
const loadCourses = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/teacher/courses');
        cursos.value = response.data;
    } catch (error) {
        console.error('Error al cargar cursos:', error);
        alert('Error al cargar los cursos');
    } finally {
        loading.value = false;
    }
};

// Cargar áreas y carreras
const loadAreas = async () => {
    try {
        const response = await axios.get('/teacher/courses/careers');
        areas.value = response.data;
    } catch (error) {
        console.error('Error al cargar áreas:', error);
        alert('Error al cargar las áreas de estudio');
    }
};

// Abrir modal para crear nuevo curso
const openCreateModal = () => {
    editingCourse.value = null;
    formData.value = {
        tipo: 'interno',
        nombre: '',
        descripcion: '',
        tiempo_duracion: '',
        costo: null,
        link: '',
        aula: '',
        career_ids: [],
    };
    errors.value = {};
    showModal.value = true;
};

// Abrir modal para editar curso
const openEditModal = (course) => {
    editingCourse.value = course;
    formData.value = {
        tipo: course.tipo,
        nombre: course.nombre,
        descripcion: course.descripcion,
        tiempo_duracion: course.tiempo_duracion,
        costo: course.costo || null,
        link: course.link || '',
        aula: course.aula || '',
        career_ids: course.careers?.map(c => c.id) || [],
    };
    errors.value = {};
    showModal.value = true;
};

// Cerrar modal
const closeModal = () => {
    showModal.value = false;
    editingCourse.value = null;
    formData.value = {
        tipo: 'interno',
        nombre: '',
        descripcion: '',
        tiempo_duracion: '',
        costo: null,
        link: '',
        aula: '',
        career_ids: [],
    };
    errors.value = {};
};

// Guardar curso
const saveCourse = async () => {
    try {
        errors.value = {};
        loading.value = true;

        const url = editingCourse.value 
            ? `/teacher/courses/${editingCourse.value.id}`
            : '/teacher/courses';
        
        const method = editingCourse.value ? 'put' : 'post';

        const response = await axios[method](url, formData.value);
        
        if (response.status === 201 || response.status === 200) {
            await loadCourses();
            closeModal();
            alert(editingCourse.value ? 'Curso actualizado exitosamente' : 'Curso creado exitosamente');
        }
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors || {};
        } else {
            alert('Error al guardar el curso: ' + (error.response?.data?.error || error.message));
        }
    } finally {
        loading.value = false;
    }
};

// Eliminar curso
const deleteCourse = async (course) => {
    if (!confirm('¿Estás seguro de que deseas eliminar este curso?')) {
        return;
    }

    try {
        loading.value = true;
        await axios.delete(`/teacher/courses/${course.id}`);
        await loadCourses();
        alert('Curso eliminado exitosamente');
    } catch (error) {
        alert('Error al eliminar el curso: ' + (error.response?.data?.error || error.message));
    } finally {
        loading.value = false;
    }
};

// Toggle activo/inactivo
const toggleActive = async (course) => {
    try {
        loading.value = true;
        await axios.put(`/teacher/courses/${course.id}`, {
            activo: !course.activo
        });
        await loadCourses();
    } catch (error) {
        alert('Error al actualizar el estado del curso: ' + (error.response?.data?.error || error.message));
    } finally {
        loading.value = false;
    }
};

// Manejar selección de carrera
const toggleCareer = (careerId) => {
    const index = formData.value.career_ids.indexOf(careerId);
    if (index > -1) {
        formData.value.career_ids.splice(index, 1);
    } else {
        formData.value.career_ids.push(careerId);
    }
};

// Verificar si una carrera está seleccionada
const isCareerSelected = (careerId) => {
    return formData.value.career_ids.includes(careerId);
};

// Cursos filtrados
const cursosFiltrados = computed(() => {
    let filtered = cursos.value;

    // Filtrar por tipo
    if (filterType.value !== 'todos') {
        filtered = filtered.filter(c => c.tipo === filterType.value);
    }

    // Filtrar por búsqueda
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(c => 
            c.nombre.toLowerCase().includes(query) ||
            c.descripcion.toLowerCase().includes(query)
        );
    }

    return filtered;
});

// Obtener todas las carreras de todas las áreas
const allCareers = computed(() => {
    const careers = [];
    areas.value.forEach(area => {
        if (area.careers) {
            area.careers.forEach(career => {
                careers.push({ ...career, area_nombre: area.nombre });
            });
        }
    });
    return careers;
});

onMounted(() => {
    loadCourses();
    loadAreas();
});
</script>

<template>
    <Head title="Gestión de Cursos" />

    <AuthenticatedLayout>
        <div class="min-h-screen py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex justify-between items-center">
                        <div>
                            <h1 :class="['font-heading text-4xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Gestión de Cursos
                            </h1>
                            <p :class="['font-body text-lg', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                Crea y administra cursos internos y externos
                            </p>
                        </div>
                        <button
                            @click="openCreateModal"
                            :class="['font-body px-6 py-3 rounded-lg font-semibold transition-colors flex items-center gap-2', darkMode ? 'bg-blue-500 hover:bg-blue-600 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                        >
                            <PlusIcon class="w-5 h-5" />
                            Nuevo Curso
                        </button>
                    </div>
                </div>

                <!-- Filtros y búsqueda -->
                <div class="mb-6 flex flex-col sm:flex-row gap-4">
                    <div class="flex-1 relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Buscar cursos..."
                            :class="['font-body w-full px-4 py-2 pl-12 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-800 border-gray-600 text-white placeholder-gray-400 focus:ring-blue-400' : 'bg-white text-gray-900 placeholder-gray-500']"
                        />
                        <MagnifyingGlassIcon class="w-5 h-5 absolute left-4 top-1/2 transform -translate-y-1/2" :class="darkMode ? 'text-gray-400' : 'text-gray-500'" />
                    </div>
                    <div>
                        <select
                            v-model="filterType"
                            :class="['font-body px-4 py-2 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-800 border-gray-600 text-white focus:ring-blue-400' : 'bg-white text-gray-900']"
                        >
                            <option value="todos">Todos</option>
                            <option value="interno">Internos</option>
                            <option value="externo">Externos</option>
                        </select>
                    </div>
                </div>

                <!-- Lista de cursos -->
                <div v-if="loading && cursos.length === 0" class="text-center py-12">
                    <p :class="[darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando cursos...</p>
                </div>

                <div v-else-if="cursosFiltrados.length === 0" class="text-center py-12">
                    <BookOpenIcon class="w-24 h-24 mx-auto mb-4 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" />
                    <p :class="['font-body', darkMode ? 'text-gray-400' : 'text-gray-600']">
                        No se encontraron cursos
                    </p>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="curso in cursosFiltrados"
                        :key="curso.id"
                        :class="['rounded-xl shadow-lg border p-6 transition-all', darkMode ? 'bg-gray-800 border-gray-600 hover:border-gray-500' : 'bg-white border-gray-200 hover:border-gray-300']"
                    >
                        <div class="flex justify-between items-start mb-4">
                            <span
                                :class="['px-3 py-1 rounded-full text-sm font-semibold', curso.tipo === 'interno' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200']"
                            >
                                {{ curso.tipo === 'interno' ? 'Interno' : 'Externo' }}
                            </span>
                            <span
                                :class="['px-3 py-1 rounded-full text-sm font-semibold', curso.activo ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200']"
                            >
                                {{ curso.activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>

                        <h3 :class="['font-heading text-xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                            {{ curso.nombre }}
                        </h3>

                        <p :class="['font-body text-sm mb-4 line-clamp-2', darkMode ? 'text-gray-400' : 'text-gray-600']">
                            {{ curso.descripcion }}
                        </p>

                        <div class="space-y-2 mb-4">
                            <div class="flex items-center text-sm" :class="['font-body', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                <ClockIcon class="w-4 h-4 mr-2" />
                                {{ curso.tiempo_duracion }}
                            </div>
                            <div v-if="curso.careers && curso.careers.length > 0" class="flex flex-wrap gap-1">
                                <span
                                    v-for="career in curso.careers"
                                    :key="career.id"
                                    :class="['px-2 py-1 rounded text-xs', darkMode ? 'bg-gray-700 text-gray-300' : 'bg-gray-100 text-gray-700']"
                                >
                                    {{ career.nombre }}
                                </span>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button
                                @click="openEditModal(curso)"
                                :class="['font-body flex-1 px-4 py-2 rounded-lg font-semibold text-sm transition-colors flex items-center justify-center gap-1', darkMode ? 'bg-blue-500 hover:bg-blue-600 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                            >
                                <PencilIcon class="w-4 h-4" />
                                Editar
                            </button>
                            <button
                                @click="toggleActive(curso)"
                                :class="['font-body flex-1 px-4 py-2 rounded-lg font-semibold text-sm transition-colors', curso.activo ? (darkMode ? 'bg-yellow-600 hover:bg-yellow-700 text-white' : 'bg-yellow-600 hover:bg-yellow-700 text-white') : (darkMode ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-green-600 hover:bg-green-700 text-white')]"
                            >
                                {{ curso.activo ? 'Desactivar' : 'Activar' }}
                            </button>
                            <button
                                @click="deleteCourse(curso)"
                                :class="['font-body px-4 py-2 rounded-lg font-semibold text-sm transition-colors flex items-center justify-center', darkMode ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-red-600 hover:bg-red-700 text-white']"
                            >
                                <TrashIcon class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modal para crear/editar curso -->
                <div
                    v-if="showModal"
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="closeModal"
                >
                    <div
                        :class="['w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-xl shadow-xl', darkMode ? 'bg-gray-800' : 'bg-white']"
                        @click.stop
                    >
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-6">
                                <h2 :class="['font-heading text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    {{ editingCourse ? 'Editar Curso' : 'Nuevo Curso' }}
                                </h2>
                                <button
                                    @click="closeModal"
                                    :class="['p-2 rounded-lg transition-colors', darkMode ? 'text-gray-400 hover:text-white hover:bg-gray-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100']"
                                >
                                    <XMarkIcon class="w-6 h-6" />
                                </button>
                            </div>

                            <form @submit.prevent="saveCourse" class="space-y-6">
                                <!-- Tipo de curso -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                        Tipo de Curso *
                                    </label>
                                    <div class="flex gap-4">
                                        <label :class="['font-body flex items-center cursor-pointer', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                            <input
                                                type="radio"
                                                v-model="formData.tipo"
                                                value="interno"
                                                class="mr-2"
                                            />
                                            Interno (de la escuela)
                                        </label>
                                        <label :class="['font-body flex items-center cursor-pointer', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                            <input
                                                type="radio"
                                                v-model="formData.tipo"
                                                value="externo"
                                                class="mr-2"
                                            />
                                            Externo (fuera de la escuela)
                                        </label>
                                    </div>
                                    <p v-if="errors.tipo" class="font-body text-red-500 text-sm mt-1">{{ errors.tipo[0] }}</p>
                                </div>

                                <!-- Nombre -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                        Nombre del Curso *
                                    </label>
                                    <input
                                        v-model="formData.nombre"
                                        type="text"
                                        :class="['font-body w-full px-4 py-2 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-blue-400' : 'bg-white text-gray-900', errors.nombre ? 'border-red-500' : '']"
                                        placeholder="Ej: Desarrollo Web con React"
                                    />
                                    <p v-if="errors.nombre" class="font-body text-red-500 text-sm mt-1">{{ errors.nombre[0] }}</p>
                                </div>

                                <!-- Descripción -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                        Descripción *
                                    </label>
                                    <textarea
                                        v-model="formData.descripcion"
                                        rows="4"
                                        :class="['font-body w-full px-4 py-2 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-blue-400' : 'bg-white text-gray-900', errors.descripcion ? 'border-red-500' : '']"
                                        placeholder="Describe de qué trata el curso..."
                                    ></textarea>
                                    <p v-if="errors.descripcion" class="font-body text-red-500 text-sm mt-1">{{ errors.descripcion[0] }}</p>
                                </div>

                                <!-- Tiempo/Duración -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                        Tiempo / Sesiones *
                                    </label>
                                    <input
                                        v-model="formData.tiempo_duracion"
                                        type="text"
                                        :class="['font-body w-full px-4 py-2 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-blue-400' : 'bg-white text-gray-900', errors.tiempo_duracion ? 'border-red-500' : '']"
                                        placeholder="Ej: 8 semanas, 40 horas, 10 sesiones"
                                    />
                                    <p v-if="errors.tiempo_duracion" class="font-body text-red-500 text-sm mt-1">{{ errors.tiempo_duracion[0] }}</p>
                                </div>

                                <!-- Campos opcionales -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <!-- Costo -->
                                    <div>
                                        <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                            Costo (opcional)
                                        </label>
                                        <input
                                            v-model.number="formData.costo"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            :class="['font-body w-full px-4 py-2 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-blue-400' : 'bg-white text-gray-900', errors.costo ? 'border-red-500' : '']"
                                            placeholder="0.00"
                                        />
                                        <p v-if="errors.costo" class="font-body text-red-500 text-sm mt-1">{{ errors.costo[0] }}</p>
                                    </div>

                                    <!-- Link -->
                                    <div>
                                        <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                            Link (opcional)
                                        </label>
                                        <input
                                            v-model="formData.link"
                                            type="url"
                                            :class="['font-body w-full px-4 py-2 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-blue-400' : 'bg-white text-gray-900', errors.link ? 'border-red-500' : '']"
                                            placeholder="https://..."
                                        />
                                        <p v-if="errors.link" class="font-body text-red-500 text-sm mt-1">{{ errors.link[0] }}</p>
                                    </div>

                                    <!-- Aula -->
                                    <div>
                                        <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                            Aula (opcional)
                                        </label>
                                        <input
                                            v-model="formData.aula"
                                            type="text"
                                            :class="['font-body w-full px-4 py-2 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-blue-500 focus:border-blue-500', darkMode ? 'bg-gray-700 border-gray-600 text-white focus:ring-blue-400' : 'bg-white text-gray-900', errors.aula ? 'border-red-500' : '']"
                                            placeholder="Ej: B-205, Lab. Cómputo 1"
                                        />
                                        <p v-if="errors.aula" class="font-body text-red-500 text-sm mt-1">{{ errors.aula[0] }}</p>
                                    </div>
                                </div>

                                <!-- Carreras -->
                                <div>
                                    <label :class="['font-body block text-sm font-semibold mb-2', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                        Carreras donde se publicará * (Selecciona al menos una)
                                    </label>
                                    <div v-if="loading && areas.length === 0" class="font-body text-sm" :class="[darkMode ? 'text-gray-400' : 'text-gray-600']">
                                        Cargando carreras...
                                    </div>
                                    <div v-else class="max-h-60 overflow-y-auto border rounded-lg p-4" :class="[darkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-300']">
                                        <div v-for="area in areas" :key="area.id" class="mb-4 last:mb-0">
                                            <h4 :class="['font-heading font-semibold mb-2', darkMode ? 'text-blue-400' : 'text-blue-600']">
                                                {{ area.nombre }}
                                            </h4>
                                            <div class="ml-4 space-y-2">
                                                <label
                                                    v-for="career in area.careers"
                                                    :key="career.id"
                                                    :class="['font-body flex items-center cursor-pointer', darkMode ? 'text-gray-300' : 'text-gray-700']"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        :checked="isCareerSelected(career.id)"
                                                        @change="toggleCareer(career.id)"
                                                        class="mr-2"
                                                    />
                                                    {{ career.nombre }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <p v-if="errors.career_ids" class="font-body text-red-500 text-sm mt-1">{{ errors.career_ids[0] }}</p>
                                    <p v-else-if="formData.career_ids.length === 0" class="font-body text-yellow-500 text-sm mt-1">
                                        Debes seleccionar al menos una carrera
                                    </p>
                                </div>

                                <!-- Botones -->
                                <div class="flex justify-end gap-4">
                                    <button
                                        type="button"
                                        @click="closeModal"
                                        :class="['font-body px-6 py-2 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-600 hover:bg-gray-700 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-900']"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="loading"
                                        :class="['font-body px-6 py-2 rounded-lg font-semibold transition-colors', darkMode ? 'bg-blue-500 hover:bg-blue-600 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white', loading ? 'opacity-50 cursor-not-allowed' : '']"
                                    >
                                        {{ loading ? 'Guardando...' : (editingCourse ? 'Actualizar' : 'Crear') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

