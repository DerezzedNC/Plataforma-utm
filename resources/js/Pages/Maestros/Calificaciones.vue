<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { 
    ExclamationTriangleIcon,
    CheckCircleIcon,
    XCircleIcon,
    InformationCircleIcon,
    UserGroupIcon,
    BookOpenIcon,
    ArrowRightIcon,
    PlusIcon,
    PencilIcon,
    TrashIcon,
    XMarkIcon,
    Cog6ToothIcon
} from '@heroicons/vue/24/outline';

const { darkMode } = useDarkMode();

// Estados para selección
const loadingGroups = ref(true);
const loadingSubjects = ref(false);
const groups = ref([]);
const subjects = ref([]);
const selectedGroup = ref(null);
const selectedSubject = ref(null);
const academicLoad = ref(null);
const loadingAcademicLoad = ref(false);

// Estados para calificaciones
const loading = ref(false);
const saving = ref({});
const selectedCourseUnit = ref(null);
const courseUnits = ref([]);
const loadingCourseUnits = ref(false);
const calificaciones = ref([]);
const errors = ref({});
const showGradesTable = ref(false);

// Estados para confirmación
const loadingConfirmacion = ref(false);

// Estados para modal de configuración de unidades
const showUnitsModal = ref(false);
const loadingUnits = ref(false);
const unidadesForm = ref([]);

// Cargar grupos del maestro
const loadGroups = async () => {
    try {
        loadingGroups.value = true;
        const response = await axios.get('/teacher/groups');
        groups.value = response.data;
    } catch (error) {
        console.error('Error cargando grupos:', error);
        alert('Error al cargar los grupos');
    } finally {
        loadingGroups.value = false;
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
        subjects.value = response.data;
        
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

// Obtener academic load
const loadAcademicLoad = async () => {
    if (!selectedGroup.value || !selectedSubject.value) {
        academicLoad.value = null;
        showGradesTable.value = false;
        calificaciones.value = []; // Limpiar calificaciones al cambiar de grupo
        courseUnits.value = [];
        selectedCourseUnit.value = null;
        return;
    }

    try {
        loadingAcademicLoad.value = true;
        // Limpiar calificaciones anteriores al cambiar de grupo/materia
        calificaciones.value = [];
        courseUnits.value = [];
        selectedCourseUnit.value = null;
        
        const response = await axios.get('/teacher/grades/academic-loads', {
            params: {
                carrera: selectedGroup.value.carrera,
                grupo: selectedGroup.value.grupo,
                materia: selectedSubject.value.materia,
            }
        });
        academicLoad.value = response.data;
        showGradesTable.value = true;
        await loadCourseUnits();
    } catch (error) {
        console.error('Error cargando carga académica:', error);
        alert(error.response?.data?.message || 'Error al cargar la carga académica');
        academicLoad.value = null;
        showGradesTable.value = false;
        calificaciones.value = []; // Limpiar calificaciones en caso de error
        courseUnits.value = [];
        selectedCourseUnit.value = null;
    } finally {
        loadingAcademicLoad.value = false;
    }
};

// Cargar unidades del curso
const loadCourseUnits = async () => {
    if (!academicLoad.value) return;

    try {
        loadingCourseUnits.value = true;
        const response = await axios.get('/teacher/grades/course-units', {
            params: {
                academic_load_id: academicLoad.value.id,
            }
        });
        courseUnits.value = response.data;
        
        // Seleccionar la primera unidad por defecto si hay unidades disponibles
        if (courseUnits.value.length > 0 && !selectedCourseUnit.value) {
            selectedCourseUnit.value = courseUnits.value[0].id;
            loadGrades();
        }
    } catch (error) {
        console.error('Error cargando unidades:', error);
        alert('Error al cargar las unidades del curso');
    } finally {
        loadingCourseUnits.value = false;
    }
};

// Watch para cargar materias cuando se selecciona un grupo
watch(selectedGroup, () => {
    selectedSubject.value = null;
    academicLoad.value = null;
    showGradesTable.value = false;
    loadSubjects();
});

// Watch para cargar academic load cuando se selecciona materia
watch(selectedSubject, () => {
    academicLoad.value = null;
    showGradesTable.value = false;
    if (selectedSubject.value) {
        loadAcademicLoad();
    }
});

// Cargar calificaciones
const loadGrades = async () => {
    if (!academicLoad.value || !selectedCourseUnit.value) return;

    try {
        loading.value = true;
        
        const response = await axios.get('/teacher/grades', {
            params: {
                academic_load_id: academicLoad.value.id,
                course_unit_id: selectedCourseUnit.value
            }
        });
        
        // Actualizar unidades si vienen en la respuesta
        if (response.data.course_units) {
            courseUnits.value = response.data.course_units;
        }
        
        // Asegurar que todas las calificaciones tengan un objeto calificacion válido
        calificaciones.value = (response.data.calificaciones || response.data).map(item => {
            if (!item.calificacion) {
                item.calificacion = {
                    id: null,
                    saber: null,
                    saber_hacer_convivir: null,
                    calificacion_final_unidad: null,
                };
            }
            return item;
        });
    } catch (error) {
        console.error('Error cargando calificaciones:', error);
        alert(error.response?.data?.message || 'Error al cargar las calificaciones');
    } finally {
        loading.value = false;
    }
};

// Handlers para actualizar valores locales y calcular en tiempo real (SIN guardar)
const handleSaberInput = (item, value) => {
    // Actualizar localmente el valor mientras el usuario escribe
    if (!item.calificacion) {
        item.calificacion = {};
    }
    item.calificacion.saber = value === '' ? null : (parseInt(value) || null);
    
    // Calcular calificación final localmente en tiempo real
    calcularCalificacionFinal(item);
};

const handleSaberHacerConvivirInput = (item, value) => {
    // Actualizar localmente el valor mientras el usuario escribe
    if (!item.calificacion) {
        item.calificacion = {};
    }
    item.calificacion.saber_hacer_convivir = value === '' ? null : (parseInt(value) || null);
    
    // Calcular calificación final localmente en tiempo real
    calcularCalificacionFinal(item);
};

// Calcular calificación final (Modelo 60-40: Saber * 0.6 + Saber Hacer * 0.4)
const calcularCalificacionFinal = (item) => {
    if (!item.calificacion) {
        item.calificacion = {};
    }
    
    const saber = item.calificacion.saber ?? 0;
    const saberHacer = item.calificacion.saber_hacer_convivir ?? 0;
    
    if (saber > 0 || saberHacer > 0) {
        // Fórmula 60-40: Saber * 0.6 + Saber Hacer * 0.4
        const calificacionFinal = (saber * 0.6) + (saberHacer * 0.4);
        item.calificacion.calificacion_final_unidad = customRound(calificacionFinal, 2);
    } else {
        item.calificacion.calificacion_final_unidad = null;
    }
};


// Guardar calificación (SOLO cuando sale del input)
const saveGrade = async (studentId, tipo, valor) => {
    if (valor === '' || valor === null || valor === undefined) {
        return;
    }

    const valorNum = parseInt(valor);
    if (isNaN(valorNum) || valorNum < 0 || valorNum > 100) {
        alert('Por favor ingresa un valor válido entre 0 y 100');
        return;
    }

    // Obtener referencia local al estudiante
    const calificacionLocal = calificaciones.value.find(c => c.student_id === studentId);
    if (!calificacionLocal) {
        console.error('Estudiante no encontrado:', studentId);
        return;
    }

    if (!selectedCourseUnit.value) {
        alert('Por favor selecciona una unidad');
        return;
    }

    try {
        // Preparar datos: enviar ambos valores para preservar el que no se está editando
        const data = {
            student_id: studentId,
            course_unit_id: selectedCourseUnit.value,
        };

        // Obtener valores actuales de la calificación local
        const saberActual = calificacionLocal.calificacion?.saber ?? null;
        const saberHacerActual = calificacionLocal.calificacion?.saber_hacer_convivir ?? null;

        // Enviar el valor que se está editando y preservar el otro
        if (tipo === 'saber') {
            data.saber = valorNum;
            // Preservar el valor de saber_hacer_convivir si existe
            if (saberHacerActual !== null) {
                data.saber_hacer_convivir = saberHacerActual;
            }
        } else if (tipo === 'saber_hacer_convivir') {
            data.saber_hacer_convivir = valorNum;
            // Preservar el valor de saber si existe
            if (saberActual !== null) {
                data.saber = saberActual;
            }
        }

        // Enviar petición al servidor
        const response = await axios.post('/teacher/grades', data);
        
        // Asegurar que calificacion existe
        if (!calificacionLocal.calificacion) {
            calificacionLocal.calificacion = {};
        }
        
        // Actualizar con la respuesta del servidor
        if (response.data && response.data.calificacion) {
            Object.assign(calificacionLocal.calificacion, {
                id: response.data.calificacion.id,
                saber: response.data.calificacion.saber,
                saber_hacer_convivir: response.data.calificacion.saber_hacer_convivir,
                calificacion_final_unidad: response.data.calificacion.calificacion_final_unidad,
            });
        }

        // NO recargar toda la lista - preservar estado local de otros estudiantes
    } catch (error) {
        console.error('Error guardando calificación:', error);
        alert(error.response?.data?.message || 'Error al guardar la calificación');
    }
};


// Aplicar redondeo personalizado
const customRound = (value, decimals = 2) => {
    if (value === null || value === undefined) return null;
    const factor = Math.pow(10, decimals);
    const decimal = value - Math.floor(value);
    if (decimal <= 0.5) {
        return Math.floor(value * factor) / factor;
    } else {
        return Math.ceil(value * factor) / factor;
    }
};

// Obtener color de calificación (ahora usa calificacion_final_unidad en escala 0-100)
const getGradeColor = (calificacion) => {
    if (!calificacion) return darkMode.value ? 'text-gray-400' : 'text-gray-600';
    if (calificacion >= 90) return 'text-green-600';
    if (calificacion >= 80) return 'text-blue-600';
    if (calificacion >= 70) return 'text-yellow-600';
    return 'text-red-600';
};

// Obtener color de asistencia
const getAttendanceColor = (porcentaje) => {
    if (porcentaje >= 80) return 'text-green-600';
    if (porcentaje >= 60) return 'text-yellow-600';
    return 'text-red-600';
};

// Watch selectedCourseUnit para recargar
watch(selectedCourseUnit, () => {
    if (showGradesTable.value && selectedCourseUnit.value) {
        loadGrades();
    }
});

// Modal de configuración de unidades
const openUnitsModal = () => {
    showUnitsModal.value = true;
    // Cargar unidades existentes o inicializar con una fila vacía
    if (courseUnits.value.length > 0) {
        unidadesForm.value = courseUnits.value.map(unit => ({
            id: unit.id,
            nombre: unit.nombre,
            porcentaje: unit.porcentaje
        }));
    } else {
        unidadesForm.value = [{ nombre: '', porcentaje: null }];
    }
};

const closeUnitsModal = () => {
    showUnitsModal.value = false;
    unidadesForm.value = [];
};

const addUnitRow = () => {
    unidadesForm.value.push({ nombre: '', porcentaje: null });
};

const removeUnitRow = (index) => {
    unidadesForm.value.splice(index, 1);
};

// Calcular suma total de porcentajes
const totalPorcentajes = computed(() => {
    return unidadesForm.value.reduce((sum, unit) => {
        return sum + (parseInt(unit.porcentaje) || 0);
    }, 0);
});

// Validar si la suma es 100%
const isValidTotal = computed(() => {
    return totalPorcentajes.value === 100;
});

// Guardar configuración de unidades
const saveUnits = async () => {
    if (!isValidTotal.value) {
        alert('La suma de los porcentajes debe ser exactamente 100%');
        return;
    }

    // Validar que todos los campos estén completos
    for (const unit of unidadesForm.value) {
        if (!unit.nombre || unit.nombre.trim() === '') {
            alert('Todos los nombres de unidad son requeridos');
            return;
        }
        if (!unit.porcentaje || unit.porcentaje < 1 || unit.porcentaje > 100) {
            alert('Todos los porcentajes deben estar entre 1 y 100');
            return;
        }
    }

    try {
        loadingUnits.value = true;
        const response = await axios.post('/teacher/grades/save-units', {
            academic_load_id: academicLoad.value.id,
            unidades: unidadesForm.value.map(unit => ({
                nombre: unit.nombre.trim(),
                porcentaje: parseInt(unit.porcentaje)
            }))
        });

        // Actualizar unidades locales
        courseUnits.value = response.data.course_units;
        
        // Seleccionar la primera unidad si no hay ninguna seleccionada
        if (courseUnits.value.length > 0 && !selectedCourseUnit.value) {
            selectedCourseUnit.value = courseUnits.value[0].id;
        }

        closeUnitsModal();
        alert('Unidades configuradas exitosamente');
        
        // Recargar calificaciones si hay una unidad seleccionada
        if (selectedCourseUnit.value) {
            await loadGrades();
        }
    } catch (error) {
        console.error('Error guardando unidades:', error);
        alert(error.response?.data?.message || 'Error al guardar las unidades');
    } finally {
        loadingUnits.value = false;
    }
};

// Confirmar calificaciones (publicar al portal)
const confirmarCalificaciones = async () => {
    if (!academicLoad.value || !selectedCourseUnit.value) return;
    
    if (!confirm('¿Estás seguro de confirmar estas calificaciones? Una vez confirmadas, se publicarán en el portal de alumnos y no podrán ser editadas a menos que actives el modo de edición.')) {
        return;
    }

    try {
        loadingConfirmacion.value = true;
        const response = await axios.post('/teacher/grades/confirm', {
            academic_load_id: academicLoad.value.id,
            course_unit_id: selectedCourseUnit.value
        });
        
        alert('Calificaciones confirmadas exitosamente. Ya están visibles en el portal de alumnos.');
        await loadGrades();
    } catch (error) {
        console.error('Error confirmando calificaciones:', error);
        alert(error.response?.data?.message || 'Error al confirmar las calificaciones');
    } finally {
        loadingConfirmacion.value = false;
    }
};


// Cargar al montar
onMounted(() => {
    loadGroups();
});
</script>

<template>
    <Head title="Calificaciones - UTM" />

    <AuthenticatedLayout>
        <div class="min-h-screen py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div :class="['mb-6 rounded-2xl p-6 border shadow-lg', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <h1 :class="['text-2xl md:text-3xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                        Gestión de Calificaciones
                    </h1>
                    <p :class="['text-lg', darkMode ? 'text-gray-300' : 'text-gray-600']">
                        Captura y administra las calificaciones de tus alumnos mediante actividades
                    </p>
                </div>

                <!-- Selección de Grupo y Materia -->
                <div v-if="!showGradesTable" :class="['mb-6 rounded-2xl border p-6', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <!-- Loading Groups -->
                    <div v-if="loadingGroups" class="text-center py-12">
                        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-green-400' : 'border-green-600'"></div>
                        <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando grupos...</p>
                    </div>

                    <!-- Formulario de Selección -->
                    <div v-else class="space-y-6">
                        <!-- Selección de Grupo -->
                        <div>
                            <label :class="['block text-lg font-semibold mb-4 flex items-center', darkMode ? 'text-white' : 'text-gray-900']">
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
                                            <div :class="['font-medium', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                                {{ group.nombre_completo }}
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Selección de Materia -->
                        <div v-if="selectedGroup">
                            <label :class="['block text-lg font-semibold mb-4 flex items-center', darkMode ? 'text-white' : 'text-gray-900']">
                                <BookOpenIcon :class="['w-6 h-6 mr-2', darkMode ? 'text-blue-400' : 'text-blue-600']" />
                                Materia
                            </label>
                            
                            <div v-if="loadingSubjects" class="text-center py-8">
                                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2" :class="darkMode ? 'border-blue-400' : 'border-blue-600'"></div>
                                <p :class="['mt-2 text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando materias...</p>
                            </div>
                            
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
                                        <span :class="['font-medium', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                            {{ subject.materia }}
                                        </span>
                                    </div>
                                </label>
                            </div>
                            
                            <div v-else :class="['p-4 rounded-lg text-center', darkMode ? 'bg-gray-700' : 'bg-gray-100']">
                                <p :class="['text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                    No hay materias asignadas para este grupo
                                </p>
                            </div>
                        </div>

                        <div v-if="loadingAcademicLoad" class="text-center py-4">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2" :class="darkMode ? 'border-blue-400' : 'border-blue-600'"></div>
                            <p :class="['mt-2 text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando información...</p>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Calificaciones -->
                <div v-if="showGradesTable && academicLoad" class="space-y-6">
                    <!-- Información del Grupo y Materia -->
                    <div :class="['rounded-xl p-4 border', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                        <div class="flex items-center justify-between flex-wrap gap-4">
                            <div>
                                <h2 :class="['text-xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    {{ academicLoad.subject.nombre }}
                                </h2>
                                <p :class="['text-sm', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                    {{ academicLoad.group.grado }}° - Grupo {{ academicLoad.group.grupo }} - {{ academicLoad.group.carrera }}
                                </p>
                            </div>
                            <div class="flex gap-2 flex-wrap">
                                <button
                                    @click="openUnitsModal"
                                    :class="['px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2', darkMode ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                                >
                                    <Cog6ToothIcon class="w-4 h-4" />
                                    Configurar Unidades
                                </button>
                                <button
                                    @click="confirmarCalificaciones"
                                    :disabled="loadingConfirmacion"
                                    :class="[
                                        'px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2',
                                        loadingConfirmacion
                                            ? 'bg-gray-400 cursor-not-allowed text-white'
                                            : darkMode ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-green-500 hover:bg-green-600 text-white'
                                    ]"
                                >
                                    <CheckCircleIcon class="w-4 h-4" />
                                    {{ loadingConfirmacion ? 'Confirmando...' : 'Confirmar Calificaciones' }}
                                </button>
                                <button
                                    @click="showGradesTable = false; selectedSubject = null; academicLoad = null;"
                                    :class="['px-4 py-2 rounded-lg text-sm font-medium', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                                >
                                    Cambiar Grupo/Materia
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Selector de Unidad -->
                    <div :class="['rounded-xl p-6 border', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                        <label :class="['block text-sm font-medium mb-4', darkMode ? 'text-gray-300' : 'text-gray-700']">
                            Unidad
                        </label>
                        <div v-if="loadingCourseUnits" class="text-center py-4">
                            <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2" :class="darkMode ? 'border-blue-400' : 'border-blue-600'"></div>
                            <p :class="['mt-2 text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando unidades...</p>
                        </div>
                        <select 
                            v-else
                            v-model="selectedCourseUnit"
                            @change="loadGrades()"
                            :class="['px-5 py-3 rounded-lg border w-full md:w-auto text-sm', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                        >
                            <option :value="null" disabled>Selecciona una unidad</option>
                            <option 
                                v-for="unit in courseUnits" 
                                :key="unit.id" 
                                :value="unit.id"
                            >
                                {{ unit.nombre }} ({{ unit.porcentaje }}%)
                            </option>
                        </select>
                        <p v-if="courseUnits.length === 0 && !loadingCourseUnits" :class="['mt-2 text-sm', darkMode ? 'text-red-400' : 'text-red-600']">
                            No hay unidades configuradas para esta materia. Haz clic en "Configurar Unidades" para configurarlas.
                        </p>
                    </div>

                    <!-- Loading -->
                    <div v-if="loading" class="text-center py-12">
                        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-green-400' : 'border-green-600'"></div>
                        <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando calificaciones...</p>
                    </div>

                    <!-- Tabla -->
                    <div v-else class="overflow-x-auto">
                        <div class="overflow-x-auto">
                            <table :class="['w-full rounded-lg overflow-hidden border-2 min-w-full', darkMode ? 'bg-gray-800 border-gray-500' : 'bg-white border-gray-300']">
                                <thead :class="[darkMode ? 'bg-gray-600' : 'bg-gray-100']">
                                    <tr>
                                        <!-- 1. Alumno (Fijo) -->
                                        <th :class="['px-4 py-3 text-left font-bold text-xs sticky left-0 z-10 bg-white', darkMode ? 'bg-gray-800 text-white' : 'bg-white text-gray-900']">Alumno</th>
                                        
                                        <!-- 2. Saber (60%) - Input -->
                                        <th :class="['px-4 py-3 text-center font-bold text-xs bg-white', darkMode ? 'bg-gray-800 text-white' : 'bg-white text-gray-900']">
                                            Saber<br>
                                            <span class="text-xs font-normal">(60% - 0-100 pts)</span>
                                        </th>
                                        
                                        <!-- 3. Saber Hacer (40%) - Input -->
                                        <th :class="['px-4 py-3 text-center font-bold text-xs bg-white', darkMode ? 'bg-gray-800 text-white' : 'bg-white text-gray-900']">
                                            Saber Hacer<br>
                                            <span class="text-xs font-normal">(40% - 0-100 pts)</span>
                                        </th>
                                        
                                        <!-- 4. Calificación Final de la Unidad (Solo Lectura) -->
                                        <th :class="['px-4 py-3 text-center font-bold text-xs bg-blue-100', darkMode ? 'bg-blue-900/30 text-white' : 'text-gray-900']">
                                            Calificación Unidad<br>
                                            <span class="text-xs font-normal">(60-40)</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr 
                                        v-for="(item, index) in calificaciones" 
                                        :key="item.inscripcion_id"
                                        :class="['border-b-2', darkMode ? 'border-gray-600' : 'border-gray-200', index % 2 === 0 ? (darkMode ? 'bg-gray-800' : 'bg-white') : (darkMode ? 'bg-gray-700' : 'bg-gray-50')]"
                                    >
                                        <!-- 1. Alumno (Fijo) -->
                                        <td :class="['px-4 py-3 sticky left-0 z-10 bg-white border-r-2', darkMode ? 'bg-gray-800 text-gray-200 border-gray-600' : 'bg-white text-gray-900 border-gray-200']">
                                            <div class="font-medium text-sm md:text-base">{{ item.student_name }}</div>
                                            <div :class="['text-xs', darkMode ? 'text-gray-400' : 'text-gray-500']">{{ item.matricula }}</div>
                                        </td>
                                        
                                        <!-- 2. Input Saber (0-100 pts) -->
                                        <td :class="['px-4 py-3 bg-white', darkMode ? 'bg-gray-800' : 'bg-white']">
                                            <input
                                                :value="item.calificacion?.saber ?? ''"
                                                @input="handleSaberInput(item, $event.target.value)"
                                                @change="saveGrade(item.student_id, 'saber', $event.target.value)"
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="1"
                                                class="no-spinner w-20 px-2 py-1 rounded border text-center text-sm"
                                                :class="[darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                            />
                                        </td>
                                        
                                        <!-- 3. Input Saber Hacer (0-100 pts) -->
                                        <td :class="['px-4 py-3 bg-white', darkMode ? 'bg-gray-800' : 'bg-white']">
                                            <input
                                                :value="item.calificacion?.saber_hacer_convivir ?? ''"
                                                @input="handleSaberHacerConvivirInput(item, $event.target.value)"
                                                @change="saveGrade(item.student_id, 'saber_hacer_convivir', $event.target.value)"
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="1"
                                                class="no-spinner w-20 px-2 py-1 rounded border text-center text-sm"
                                                :class="[darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                            />
                                        </td>
                                        
                                        <!-- 4. Calificación Final (Solo Lectura) -->
                                        <td :class="['px-4 py-3 text-center bg-blue-50', darkMode ? 'bg-blue-900/20' : '']">
                                            <span :class="['font-bold text-lg', getGradeColor(item.calificacion?.calificacion_final_unidad)]">
                                                {{ item.calificacion?.calificacion_final_unidad ? customRound(item.calificacion.calificacion_final_unidad)?.toFixed(2) : '--' }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Modal de Configuración de Unidades -->
        <div v-if="showUnitsModal" class="fixed inset-0 z-50 overflow-y-auto" @click.self="closeUnitsModal">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div :class="['relative rounded-2xl shadow-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800 border-2 border-gray-600' : 'bg-white border-2 border-gray-200']">
                    <!-- Header del Modal -->
                    <div :class="['sticky top-0 z-10 flex items-center justify-between p-6 border-b', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                        <div>
                            <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                Configurar Unidades
                            </h2>
                            <p :class="['text-sm mt-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                Configura las unidades dinámicas para esta materia. La suma de porcentajes debe ser exactamente 100%.
                            </p>
                        </div>
                        <button
                            @click="closeUnitsModal"
                            :class="['p-2 rounded-lg', darkMode ? 'hover:bg-gray-700 text-gray-300' : 'hover:bg-gray-100 text-gray-600']"
                        >
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                    </div>

                    <!-- Contenido del Modal -->
                    <div class="p-6 space-y-6">
                        <!-- Tabla de Unidades -->
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h3 :class="['text-lg font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                    Unidades del Curso
                                </h3>
                                <button
                                    @click="addUnitRow"
                                    :class="['px-3 py-1 rounded-lg text-sm font-medium flex items-center gap-2', darkMode ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                                >
                                    <PlusIcon class="w-4 h-4" />
                                    Agregar Unidad
                                </button>
                            </div>

                            <div class="overflow-x-auto">
                                <table :class="['w-full rounded-lg border', darkMode ? 'bg-gray-700 border-gray-600' : 'bg-white border-gray-300']">
                                    <thead :class="[darkMode ? 'bg-gray-600' : 'bg-gray-100']">
                                        <tr>
                                            <th :class="['px-4 py-3 text-left font-bold text-xs', darkMode ? 'text-white' : 'text-gray-900']">Nombre</th>
                                            <th :class="['px-4 py-3 text-left font-bold text-xs', darkMode ? 'text-white' : 'text-gray-900']">Porcentaje (%)</th>
                                            <th :class="['px-4 py-3 text-center font-bold text-xs', darkMode ? 'text-white' : 'text-gray-900']">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr 
                                            v-for="(unit, index) in unidadesForm" 
                                            :key="index"
                                            :class="['border-b', darkMode ? 'border-gray-600' : 'border-gray-200']"
                                        >
                                            <td :class="['px-4 py-3', darkMode ? 'text-gray-200' : 'text-gray-900']">
                                                <input
                                                    v-model="unit.nombre"
                                                    type="text"
                                                    placeholder="Ej: Unidad 1"
                                                    :class="['w-full px-3 py-2 rounded border text-sm', darkMode ? 'bg-gray-600 border-gray-500 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                                />
                                            </td>
                                            <td :class="['px-4 py-3', darkMode ? 'text-gray-200' : 'text-gray-900']">
                                                <input
                                                    v-model.number="unit.porcentaje"
                                                    type="number"
                                                    min="1"
                                                    max="100"
                                                    placeholder="50"
                                                    :class="['w-full px-3 py-2 rounded border text-sm', darkMode ? 'bg-gray-600 border-gray-500 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                                />
                                            </td>
                                            <td :class="['px-4 py-3 text-center']">
                                                <button
                                                    @click="removeUnitRow(index)"
                                                    :disabled="unidadesForm.length === 1"
                                                    :class="[
                                                        'p-2 rounded-lg',
                                                        unidadesForm.length === 1
                                                            ? 'opacity-50 cursor-not-allowed'
                                                            : '',
                                                        darkMode ? 'hover:bg-gray-600 text-red-400' : 'hover:bg-gray-100 text-red-600'
                                                    ]"
                                                >
                                                    <TrashIcon class="w-5 h-5" />
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Validación de Total -->
                            <div class="mt-4 p-4 rounded-lg border" :class="[
                                isValidTotal 
                                    ? (darkMode ? 'bg-green-900/30 border-green-600' : 'bg-green-50 border-green-200')
                                    : (darkMode ? 'bg-red-900/30 border-red-600' : 'bg-red-50 border-red-200')
                            ]">
                                <p :class="[
                                    'text-center font-semibold text-lg',
                                    isValidTotal 
                                        ? (darkMode ? 'text-green-400' : 'text-green-700')
                                        : (darkMode ? 'text-red-400' : 'text-red-700')
                                ]">
                                    Total: {{ totalPorcentajes }}% / 100%
                                </p>
                                <p v-if="!isValidTotal" :class="['text-center text-sm mt-2', darkMode ? 'text-red-300' : 'text-red-600']">
                                    La suma de los porcentajes debe ser exactamente 100% para poder guardar.
                                </p>
                            </div>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex gap-2 justify-end">
                            <button
                                @click="closeUnitsModal"
                                :class="['px-4 py-2 rounded-lg text-sm font-medium', darkMode ? 'bg-gray-600 hover:bg-gray-700 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                            >
                                Cancelar
                            </button>
                            <button
                                @click="saveUnits"
                                :disabled="!isValidTotal || loadingUnits"
                                :class="[
                                    'px-4 py-2 rounded-lg text-sm font-medium',
                                    (!isValidTotal || loadingUnits)
                                        ? 'bg-gray-400 cursor-not-allowed text-white'
                                        : (darkMode ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-green-500 hover:bg-green-600 text-white')
                                ]"
                            >
                                {{ loadingUnits ? 'Guardando...' : 'Guardar Unidades' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Ocultar spinners de inputs numéricos */
.no-spinner::-webkit-outer-spin-button,
.no-spinner::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.no-spinner[type=number] {
    -moz-appearance: textfield;
}
</style>
