<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
// Heroicons - Outline version
import { 
    UserGroupIcon,
    BookOpenIcon,
    ArrowRightIcon,
    ArrowLeftIcon
} from '@heroicons/vue/24/outline';

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Estados
const loading = ref(true);
const groups = ref([]);
const selectedGroup = ref(null);
const selectedSubject = ref(null);
const subjects = ref([]);
const loadingSubjects = ref(false);

// Cargar grupos del maestro (solo donde imparte materias)
const loadGroups = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/teacher/groups');
        // Asegurar que no haya duplicados en el frontend también
        const uniqueGroups = response.data.filter((group, index, self) => 
            index === self.findIndex(g => 
                g.carrera === group.carrera && g.grupo === group.grupo
            )
        );
        groups.value = uniqueGroups;
    } catch (error) {
        console.error('Error cargando grupos:', error);
        alert('Error al cargar los grupos');
    } finally {
        loading.value = false;
    }
};

// Cargar materias cuando se selecciona un grupo
const loadSubjects = async () => {
    if (!selectedGroup.value) {
        subjects.value = [];
        selectedSubject.value = null;
        return;
    }

    try {
        loadingSubjects.value = true;
        const response = await axios.get('/teacher/subjects', {
            params: {
                carrera: selectedGroup.value.carrera,
                grupo: selectedGroup.value.grupo,
            }
        });
        
        // Eliminar duplicados de materias en el frontend también
        const uniqueSubjects = response.data.filter((subject, index, self) => 
            index === self.findIndex(s => s.materia === subject.materia)
        );
        subjects.value = uniqueSubjects;
        
        // Si solo hay una materia, seleccionarla automáticamente
        if (subjects.value.length === 1) {
            selectedSubject.value = subjects.value[0];
        }
    } catch (error) {
        console.error('Error cargando materias:', error);
        alert('Error al cargar las materias');
    } finally {
        loadingSubjects.value = false;
    }
};

// Watch para cargar materias cuando se selecciona un grupo
watch(selectedGroup, () => {
    selectedSubject.value = null;
    loadSubjects();
});

// Función para continuar a la lista de alumnos
const continuarAListaAlumnos = () => {
    if (!selectedGroup.value || !selectedSubject.value) {
        alert('Por favor selecciona un grupo y una materia');
        return;
    }
    
    // Navegar a la lista de alumnos con los parámetros
    router.get(route('maestros.lista-alumnos'), {
        carrera: selectedGroup.value.carrera,
        grupo: selectedGroup.value.grupo,
        grado: selectedGroup.value.grado,
        schedule_id: selectedSubject.value.id,
        materia: selectedSubject.value.materia,
    });
};

// Cargar grupos al montar
onMounted(() => {
    loadGroups();
});
</script>

<template>
    <Head title="Seleccionar Grupo - Portal Docente UTM" />

    <AuthenticatedLayout>
        <div class="min-h-screen">
            <div class="py-8">
                <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <!-- Header -->
                    <div class="text-center mb-8">
                        <h1 :class="['font-heading text-4xl font-bold mb-4', darkMode ? 'text-white' : 'text-gray-900']">
                            Lista de Alumnos
                        </h1>
                        <p :class="['font-body text-xl', darkMode ? 'text-gray-400' : 'text-gray-600']">
                            Selecciona el grupo y materia para el pase de lista
                        </p>
                    </div>

                    <!-- Loading State -->
                    <div v-if="loading" :class="['rounded-2xl shadow-xl border p-12 text-center', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-blue-400' : 'border-blue-600'"></div>
                        <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando grupos...</p>
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="groups.length === 0" :class="['rounded-2xl shadow-xl border p-12 text-center', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                        <UserGroupIcon class="w-24 h-24 mx-auto mb-4 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" />
                        <h3 :class="['font-heading text-xl font-medium mb-2', darkMode ? 'text-gray-400' : 'text-gray-500']">
                            No tienes grupos asignados
                        </h3>
                        <p :class="['font-body text-base', darkMode ? 'text-gray-500' : 'text-gray-400']">
                            Contacta a la administración para asignarte grupos
                        </p>
                    </div>

                    <!-- Formulario de Selección -->
                    <div v-else :class="['rounded-2xl shadow-xl border p-8', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                        <div class="space-y-8">
                            
                            <!-- Selección de Grupo -->
                            <div>
                                <label :class="['font-body block text-lg font-semibold mb-4 flex items-center', darkMode ? 'text-white' : 'text-gray-900']">
                                    <UserGroupIcon :class="['w-6 h-6 mr-2', darkMode ? 'text-blue-400' : 'text-blue-600']" />
                                    Grupo
                                </label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <label
                                        v-for="group in groups"
                                        :key="`${group.carrera}-${group.grupo}`"
                                        :class="[
                                            'flex items-center p-4 rounded-xl border-2 cursor-pointer transition-all duration-300',
                                            selectedGroup && selectedGroup.carrera === group.carrera && selectedGroup.grupo === group.grupo
                                                ? (darkMode ? 'border-blue-500 bg-blue-900/30' : 'border-blue-500 bg-blue-50')
                                                : (darkMode ? 'border-gray-600 hover:border-blue-400' : 'border-gray-200 hover:border-blue-300')
                                        ]"
                                    >
                                        <input
                                            type="radio"
                                            v-model="selectedGroup"
                                            :value="group"
                                            class="sr-only"
                                        />
                                        <div class="flex items-center w-full">
                                            <div :class="[
                                                'w-4 h-4 rounded-full border-2 mr-3 flex items-center justify-center flex-shrink-0',
                                                selectedGroup && selectedGroup.carrera === group.carrera && selectedGroup.grupo === group.grupo
                                                    ? 'border-blue-500 bg-blue-500'
                                                    : (darkMode ? 'border-gray-500' : 'border-gray-300')
                                            ]">
                                                <div v-if="selectedGroup && selectedGroup.carrera === group.carrera && selectedGroup.grupo === group.grupo" class="w-2 h-2 rounded-full bg-white"></div>
                                            </div>
                                            <div class="flex-1">
                                                <div :class="['font-body font-medium', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                    {{ group.nombre_completo }}
                                                </div>
                                                <div :class="['font-body text-sm mt-1', darkMode ? 'text-gray-500' : 'text-gray-500']">
                                                    {{ group.carrera }}
                                                </div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Selección de Materia -->
                            <div v-if="selectedGroup">
                                <label :class="['font-body block text-lg font-semibold mb-4 flex items-center', darkMode ? 'text-white' : 'text-gray-900']">
                                    <BookOpenIcon :class="['w-6 h-6 mr-2', darkMode ? 'text-blue-400' : 'text-blue-600']" />
                                    Materia
                                </label>
                                
                                <!-- Loading subjects -->
                                <div v-if="loadingSubjects" class="text-center py-8">
                                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2" :class="darkMode ? 'border-blue-400' : 'border-blue-600'"></div>
                                    <p :class="['mt-2 text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando materias...</p>
                                </div>
                                
                                <!-- Subjects list -->
                                <div v-else-if="subjects.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <label
                                        v-for="subject in subjects"
                                        :key="subject.id"
                                        :class="[
                                            'flex items-center p-4 rounded-xl border-2 cursor-pointer transition-all duration-300',
                                            selectedSubject && selectedSubject.id === subject.id
                                                ? (darkMode ? 'border-blue-500 bg-blue-900/30' : 'border-blue-500 bg-blue-50')
                                                : (darkMode ? 'border-gray-600 hover:border-blue-400' : 'border-gray-200 hover:border-blue-300')
                                        ]"
                                    >
                                        <input
                                            type="radio"
                                            v-model="selectedSubject"
                                            :value="subject"
                                            class="sr-only"
                                        />
                                        <div class="flex items-center w-full">
                                            <div :class="[
                                                'w-4 h-4 rounded-full border-2 mr-3 flex items-center justify-center flex-shrink-0',
                                                selectedSubject && selectedSubject.id === subject.id
                                                    ? 'border-blue-500 bg-blue-500'
                                                    : (darkMode ? 'border-gray-500' : 'border-gray-300')
                                            ]">
                                                <div v-if="selectedSubject && selectedSubject.id === subject.id" class="w-2 h-2 rounded-full bg-white"></div>
                                            </div>
                                            <span :class="['font-body font-medium', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                {{ subject.materia }}
                                            </span>
                                        </div>
                                    </label>
                                </div>
                                
                                <!-- No subjects -->
                                <div v-else :class="['p-4 rounded-lg text-center', darkMode ? 'bg-gray-700' : 'bg-gray-100']">
                                    <p :class="['font-body text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                        No hay materias asignadas para este grupo
                                    </p>
                                </div>
                            </div>

                            <!-- Botón Continuar -->
                            <div class="pt-6">
                                <button
                                    @click="continuarAListaAlumnos"
                                    :disabled="!selectedGroup || !selectedSubject"
                                    :class="[
                                        'font-body w-full py-4 px-6 rounded-lg font-semibold text-lg transition-all duration-300 flex items-center justify-center gap-2',
                                        selectedGroup && selectedSubject
                                            ? (darkMode ? 'bg-blue-500 hover:bg-blue-600 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white')
                                            : (darkMode ? 'bg-gray-600 text-gray-400 cursor-not-allowed' : 'bg-gray-300 text-gray-500 cursor-not-allowed')
                                    ]"
                                >
                                    Continuar a Lista de Alumnos
                                    <ArrowRightIcon class="w-6 h-6" />
                                </button>
                            </div>

                        </div>
                    </div>

                    <!-- Botón para regresar -->
                    <div class="mt-12 text-center">
                        <Link :href="route('dashboard-maestro')" :class="['font-body inline-flex items-center px-6 py-3 rounded-lg font-medium transition-colors gap-2', darkMode ? 'bg-gray-600 hover:bg-gray-700 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']">
                            <ArrowLeftIcon class="w-5 h-5" />
                            Regresar al Dashboard
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
