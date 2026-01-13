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
    EyeIcon,
    AcademicCapIcon
} from '@heroicons/vue/24/outline';

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Estados
const groups = ref([]);
const loading = ref(true);
const showAddModal = ref(false);
const showEditModal = ref(false);
const showDetailModal = ref(false);
const showAssignModal = ref(false);
const showAssignTutorModal = ref(false);
const selectedGroup = ref(null);
const searchQuery = ref('');
const groupStudents = ref([]);
const availableStudents = ref([]);
const loadingStudents = ref(false);
const selectedStudentIds = ref([]);
const availableTutors = ref([]);
const loadingTutors = ref(false);
const selectedTutorId = ref(null);

// Datos de áreas y carreras
const areas = ref([]);
const careers = ref([]);

// Formulario para agregar grupo
const newGroup = ref({
    area_id: '',
    carrera: '',
    grado: '',
    grupo: '',
});

// Formulario para editar grupo
const editGroup = ref({
    id: null,
    area_id: '',
    carrera: '',
    grado: '',
    grupo: '',
});

// Grados disponibles (1-5)
const grados = [1, 2, 3, 4, 5];

// Grupos disponibles (A-E) - se filtrarán dinámicamente
const allGrupos = ['A', 'B', 'C', 'D', 'E'];
const gruposDisponibles = ref([]); // Inicialmente vacío hasta que se seleccione carrera y grado
const gradosDisponibles = ref([1, 2, 3, 4, 5]);

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
watch(() => newGroup.value.area_id, (newAreaId) => {
    newGroup.value.carrera = '';
    newGroup.value.grado = '';
    newGroup.value.grupo = '';
    loadCareers(newAreaId);
    gruposDisponibles.value = []; // Limpiar hasta que se seleccione carrera
    gradosDisponibles.value = grados;
});

// Watch para carrera - cargar opciones disponibles
watch(() => newGroup.value.carrera, async (newCarrera, oldCarrera) => {
    console.log('📊 Watch de carrera activado:', { nueva: newCarrera, anterior: oldCarrera });
    
    if (!newCarrera) {
        gruposDisponibles.value = [];
        gradosDisponibles.value = grados;
        newGroup.value.grado = '';
        newGroup.value.grupo = '';
        return;
    }
    
    try {
        console.log('🔍 Consultando opciones para carrera:', newCarrera);
        const response = await axios.get('/admin/groups/available-options', {
            params: { carrera: newCarrera }
        });
        
        console.log('📦 Respuesta completa:', response.data);
        const gradosInfo = response.data.grados_info;
        
        // Filtrar grados que tienen grupos disponibles
        // IMPORTANTE: Mostrar el grado si tiene_grupos_disponibles es true
        // Si no existe la info, asumir que tiene disponibles (por seguridad, mostrar todos)
        gradosDisponibles.value = grados.filter(g => {
            const infoGrado = gradosInfo[g];
            
            // Si no hay información del grado, mostrar el grado (por defecto, todos están disponibles)
            if (!infoGrado) {
                console.log(`Grado ${g}: Sin información, mostrando por defecto`);
                return true;
            }
            
            // Si tiene_grupos_disponibles es explícitamente true, mostrar el grado
            const tieneDisponibles = infoGrado.tiene_grupos_disponibles === true;
            
            console.log(`Grado ${g}:`, {
                info: infoGrado,
                tieneDisponibles: tieneDisponibles,
                gruposDisponibles: infoGrado.grupos_disponibles || [],
                gruposOcupados: infoGrado.grupos_ocupados || []
            });
            
            return tieneDisponibles;
        });
        
        console.log('✅ Grados disponibles:', gradosDisponibles.value);
        
        // Limpiar grupo seleccionado y grupos disponibles hasta que se seleccione un grado
        newGroup.value.grupo = '';
        gruposDisponibles.value = [];
        
        // Si hay un grado seleccionado, actualizar grupos disponibles
        if (newGroup.value.grado) {
            console.log('🔄 Grado ya seleccionado, actualizando grupos disponibles');
            await updateGruposDisponibles(newCarrera, parseInt(newGroup.value.grado));
        }
    } catch (error) {
        console.error('❌ Error cargando opciones disponibles:', error);
        console.error('Detalles:', error.response?.data);
        gruposDisponibles.value = [];
        gradosDisponibles.value = grados;
    }
}, { immediate: false });

// Watch para grado - actualizar grupos disponibles
watch(() => newGroup.value.grado, async (newGrado, oldGrado) => {
    console.log('📊 Watch de grado activado:', { nuevo: newGrado, anterior: oldGrado, carrera: newGroup.value.carrera });
    
    if (!newGrado || !newGroup.value.carrera) {
        gruposDisponibles.value = [];
        newGroup.value.grupo = '';
        return;
    }
    
    // Forzar actualización
    await updateGruposDisponibles(newGroup.value.carrera, parseInt(newGrado));
}, { immediate: false });

// Función para actualizar grupos disponibles
const updateGruposDisponibles = async (carrera, grado) => {
    if (!carrera || !grado) {
        gruposDisponibles.value = [];
        return;
    }
    
    try {
        console.log('🔍 Buscando grupos disponibles para:', carrera, 'Grado:', grado);
        const response = await axios.get('/admin/groups/available-options', {
            params: { carrera, grado }
        });
        
        console.log('📦 Respuesta del servidor:', response.data);
        
        // Asegurarse de que grupos_disponibles sea un array
        const grupos = response.data.grupos_disponibles || [];
        gruposDisponibles.value = Array.isArray(grupos) ? grupos : [];
        
        console.log('✅ Grupos disponibles actualizados:', gruposDisponibles.value);
        console.log('❌ Grupos ocupados:', response.data.grupos_ocupados || []);
        
        // Si el grupo seleccionado ya no está disponible, limpiarlo
        if (newGroup.value.grupo && !gruposDisponibles.value.includes(newGroup.value.grupo)) {
            console.log('⚠️ Limpiando grupo seleccionado porque ya no está disponible');
            newGroup.value.grupo = '';
        }
    } catch (error) {
        console.error('❌ Error actualizando grupos disponibles:', error);
        console.error('Detalles:', error.response?.data);
        gruposDisponibles.value = [];
    }
};

// Watch para área en formulario de editar
watch(() => editGroup.value.area_id, (newAreaId) => {
    if (newAreaId) {
        editGroup.value.carrera = '';
        loadCareers(newAreaId);
    }
});

// Cargar grupos
const loadGroups = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/admin/groups');
        groups.value = response.data;
        
        // Si el modal está abierto y hay una carrera seleccionada, actualizar opciones
        if (showAddModal.value && newGroup.value.carrera) {
            await refreshAvailableOptions();
        }
    } catch (error) {
        console.error('Error cargando grupos:', error);
        if (error.response) {
            console.error('Respuesta del servidor:', error.response.status, error.response.data);
        }
    } finally {
        loading.value = false;
    }
};

// Función para refrescar opciones disponibles
const refreshAvailableOptions = async () => {
    if (!newGroup.value.carrera) return;
    
    try {
        const response = await axios.get('/admin/groups/available-options', {
            params: { carrera: newGroup.value.carrera }
        });
        
        const gradosInfo = response.data.grados_info;
        
        // Filtrar grados que tienen grupos disponibles
        // Mostrar el grado si tiene_grupos_disponibles es true o si no existe la info (por defecto, todos disponibles)
        gradosDisponibles.value = grados.filter(g => {
            const infoGrado = gradosInfo[g];
            // Si no hay info, mostrar el grado (por defecto todos están disponibles)
            if (!infoGrado) return true;
            // Mostrar si tiene_grupos_disponibles es explícitamente true
            return infoGrado.tiene_grupos_disponibles === true;
        });
        
        // Si hay un grado seleccionado, actualizar grupos disponibles
        if (newGroup.value.grado) {
            await updateGruposDisponibles(newGroup.value.carrera, parseInt(newGroup.value.grado));
        } else {
            gruposDisponibles.value = [];
        }
    } catch (error) {
        console.error('Error refrescando opciones:', error);
    }
};

// Agregar nuevo grupo
const addGroup = async () => {
    // Validación adicional: verificar que el grupo seleccionado esté en la lista de disponibles
    if (!gruposDisponibles.value.includes(newGroup.value.grupo)) {
        alert('El grupo seleccionado no está disponible. Por favor, seleccione otro grupo.');
        return;
    }
    
    // Validar que todos los campos estén completos
    if (!newGroup.value.carrera || !newGroup.value.grado || !newGroup.value.grupo) {
        alert('Por favor, complete todos los campos requeridos.');
        return;
    }
    
    try {
        const carreraCreada = newGroup.value.carrera;
        const gradoCreado = newGroup.value.grado;
        const grupoCreado = newGroup.value.grupo;
        
        const response = await axios.post('/admin/groups', {
            carrera: carreraCreada,
            grado: parseInt(gradoCreado),
            grupo: grupoCreado,
        });

        groups.value.push(response.data);
        showAddModal.value = false;
        resetForm();
        await loadGroups(); // Recargar la lista
        
        // Actualizar opciones disponibles después de crear el grupo
        if (carreraCreada) {
            try {
                const optionsResponse = await axios.get('/admin/groups/available-options', {
                    params: { carrera: carreraCreada }
                });
                const gradosInfo = optionsResponse.data.grados_info;
                gradosDisponibles.value = grados.filter(g => {
                    const infoGrado = gradosInfo[g];
                    if (!infoGrado) return true; // Por defecto, todos disponibles
                    return infoGrado.tiene_grupos_disponibles === true;
                });
            } catch (error) {
                console.error('Error actualizando opciones:', error);
            }
        }
    } catch (error) {
        console.error('Error agregando grupo:', error);
        if (error.response) {
            console.error('Respuesta del servidor:', error.response.status, error.response.data);
        }
        const errorMessage = error.response?.data?.message || error.response?.data?.errors?.[Object.keys(error.response?.data?.errors || {})[0]]?.[0] || 'Error desconocido';
        alert('Error al agregar grupo: ' + errorMessage);
    }
};

// Editar grupo
const openEditModal = async (group) => {
    selectedGroup.value = group;
    
    // Buscar la carrera para obtener su área
    let areaId = '';
    try {
        const careersResponse = await axios.get('/admin/careers');
        const allCareers = careersResponse.data;
        const career = allCareers.find(c => c.nombre === group.carrera);
        if (career) {
            areaId = career.area_id;
            await loadCareers(areaId);
        }
    } catch (error) {
        console.error('Error cargando carrera para editar:', error);
    }
    
    editGroup.value = {
        id: group.id,
        area_id: areaId,
        carrera: group.carrera,
        grado: group.grado.toString(),
        grupo: group.grupo,
    };
    showEditModal.value = true;
};

const updateGroup = async () => {
    try {
        const response = await axios.put(`/admin/groups/${editGroup.value.id}`, {
            carrera: editGroup.value.carrera,
            grado: parseInt(editGroup.value.grado),
            grupo: editGroup.value.grupo,
        });

        const index = groups.value.findIndex(g => g.id === editGroup.value.id);
        if (index !== -1) {
            groups.value[index] = response.data;
        }
        showEditModal.value = false;
        await loadGroups(); // Recargar la lista
    } catch (error) {
        console.error('Error actualizando grupo:', error);
        if (error.response) {
            console.error('Respuesta del servidor:', error.response.status, error.response.data);
        }
        const errorMessage = error.response?.data?.message || error.response?.data?.errors?.[Object.keys(error.response?.data?.errors || {})[0]]?.[0] || 'Error desconocido';
        alert('Error al actualizar grupo: ' + errorMessage);
    }
};

// Eliminar grupo
const deleteGroup = async (group) => {
    const grupoName = `${group.carrera} - Grado ${group.grado} - Grupo ${group.grupo}`;
    if (!confirm(`¿Estás seguro de eliminar el grupo: ${grupoName}?`)) {
        return;
    }

    try {
        await axios.delete(`/admin/groups/${group.id}`);
        groups.value = groups.value.filter(g => g.id !== group.id);
        await loadGroups(); // Recargar la lista
        
        // Si el modal de agregar está abierto y es la misma carrera/grado, actualizar opciones
        if (showAddModal.value && newGroup.value.carrera === group.carrera) {
            if (newGroup.value.grado && newGroup.value.grado == group.grado) {
                // Si se eliminó un grupo del mismo grado, actualizar grupos disponibles
                await updateGruposDisponibles(group.carrera, parseInt(group.grado));
            } else {
                // Recargar todas las opciones
                const response = await axios.get('/admin/groups/available-options', {
                    params: { carrera: group.carrera }
                });
                const gradosInfo = response.data.grados_info;
                gradosDisponibles.value = grados.filter(g => {
                    const infoGrado = gradosInfo[g];
                    if (!infoGrado) return true; // Por defecto, todos disponibles
                    return infoGrado.tiene_grupos_disponibles === true;
                });
            }
        }
    } catch (error) {
        console.error('Error eliminando grupo:', error);
        alert('Error al eliminar grupo');
    }
};

// Resetear formulario
const resetForm = () => {
    newGroup.value = {
        area_id: '',
        carrera: '',
        grado: '',
        grupo: '',
    };
    careers.value = [];
    gruposDisponibles.value = allGrupos;
    gradosDisponibles.value = grados;
};

// Abrir modal de agregar grupo
const openAddModal = () => {
    resetForm();
    showAddModal.value = true;
    // Asegurar que las opciones estén inicializadas
    gruposDisponibles.value = [];
    gradosDisponibles.value = grados;
    
    // Si ya hay una carrera seleccionada, cargar opciones
    if (newGroup.value.carrera) {
        // Forzar actualización del watcher
        const carrera = newGroup.value.carrera;
        newGroup.value.carrera = '';
        setTimeout(() => {
            newGroup.value.carrera = carrera;
        }, 100);
    }
};

// Filtrar grupos
const filteredGroups = () => {
    if (!searchQuery.value) return groups.value;
    const query = searchQuery.value.toLowerCase();
    return groups.value.filter(group => 
        group.carrera.toLowerCase().includes(query) ||
        group.grado.toString().includes(query) ||
        group.grupo.toLowerCase().includes(query)
    );
};

// Abrir modal de detalle del grupo
const openDetailModal = async (group) => {
    selectedGroup.value = group;
    showDetailModal.value = true;
    loadingStudents.value = true;
    groupStudents.value = [];
    
    try {
        const response = await axios.get(`/admin/groups/${group.id}/students`);
        groupStudents.value = response.data;
    } catch (error) {
        console.error('Error cargando estudiantes del grupo:', error);
    } finally {
        loadingStudents.value = false;
    }
};

// Abrir modal para asignar estudiantes
const openAssignModal = async (group) => {
    selectedGroup.value = group;
    showAssignModal.value = true;
    loadingStudents.value = true;
    availableStudents.value = [];
    selectedStudentIds.value = [];
    
    try {
        const response = await axios.get(`/admin/groups/${group.id}/available-students`);
        availableStudents.value = response.data;
    } catch (error) {
        console.error('Error cargando estudiantes disponibles:', error);
    } finally {
        loadingStudents.value = false;
    }
};

// Asignar estudiantes al grupo
const assignStudents = async () => {
    if (selectedStudentIds.value.length === 0) {
        alert('Por favor selecciona al menos un estudiante');
        return;
    }

    try {
        await axios.post(`/admin/groups/${selectedGroup.value.id}/assign-students`, {
            student_ids: selectedStudentIds.value
        });
        
        showAssignModal.value = false;
        selectedStudentIds.value = [];
        await loadGroups(); // Recargar grupos para actualizar el contador
        await openDetailModal(selectedGroup.value); // Recargar estudiantes del grupo
    } catch (error) {
        console.error('Error asignando estudiantes:', error);
        alert('Error al asignar estudiantes: ' + (error.response?.data?.message || 'Error desconocido'));
    }
};

// Remover estudiante del grupo
const removeStudent = async (student) => {
    if (!confirm(`¿Estás seguro de remover a ${student.user?.name} del grupo?`)) {
        return;
    }

    try {
        await axios.delete(`/admin/groups/${selectedGroup.value.id}/students/${student.user_id}`);
        await openDetailModal(selectedGroup.value); // Recargar estudiantes
        await loadGroups(); // Recargar grupos para actualizar el contador
    } catch (error) {
        console.error('Error removiendo estudiante:', error);
        alert('Error al remover estudiante');
    }
};

// Toggle selección de estudiante
const toggleStudent = (studentId) => {
    const index = selectedStudentIds.value.indexOf(studentId);
    if (index > -1) {
        selectedStudentIds.value.splice(index, 1);
    } else {
        selectedStudentIds.value.push(studentId);
    }
};

// Cargar maestros disponibles para asignar como tutores
const loadAvailableTutors = async () => {
    try {
        loadingTutors.value = true;
        const response = await axios.get('/admin/groups/tutors/available');
        availableTutors.value = response.data;
    } catch (error) {
        console.error('Error cargando maestros:', error);
    } finally {
        loadingTutors.value = false;
    }
};

// Abrir modal para asignar tutor
const openAssignTutorModal = async (group) => {
    selectedGroup.value = group;
    selectedTutorId.value = group.tutor?.id || null;
    showAssignTutorModal.value = true;
    await loadAvailableTutors();
};

// Asignar tutor al grupo
const assignTutor = async () => {
    if (!selectedGroup.value) return;

    try {
        await axios.put(`/admin/groups/${selectedGroup.value.id}/assign-tutor`, {
            tutor_id: selectedTutorId.value || null
        });
        
        showAssignTutorModal.value = false;
        selectedTutorId.value = null;
        await loadGroups(); // Recargar grupos para actualizar el tutor
    } catch (error) {
        console.error('Error asignando tutor:', error);
        const message = error.response?.data?.message || 'Error al asignar tutor';
        alert(message);
    }
};

onMounted(() => {
    loadAreas();
    loadGroups();
});
</script>

<template>
    <Head title="Gestión de Grupos - Administrador UTM" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                
                <!-- Header -->
                <div :class="['mb-8 rounded-2xl p-6 border shadow-lg', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 :class="['font-heading text-3xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Gestión de Grupos
                            </h1>
                            <p :class="['font-body text-lg', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                Crear y administrar grupos académicos por carrera, grado y grupo
                            </p>
                        </div>
                        <button 
                            @click="openAddModal"
                            :class="['font-body mt-4 md:mt-0 px-6 py-3 rounded-lg font-semibold transition-all hover:shadow-lg flex items-center gap-2', darkMode ? 'bg-purple-500 hover:bg-purple-600 text-white' : 'bg-purple-500 hover:bg-purple-600 text-white']"
                        >
                            <PlusIcon class="w-5 h-5" />
                            Nuevo Grupo
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
                                placeholder="Buscar por carrera, grado o grupo..."
                                :class="['font-body w-full px-4 py-3 pl-12 rounded-lg border border-gray-300 transition-all focus:ring-2 focus:ring-purple-500 focus:border-purple-500', darkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400 focus:ring-purple-400' : 'bg-white text-gray-900 placeholder-gray-500']"
                            >
                            <MagnifyingGlassIcon class="w-5 h-5 absolute left-4 top-1/2 transform -translate-y-1/2" :class="darkMode ? 'text-gray-400' : 'text-gray-500'" />
                        </div>
                        <div :class="['font-body px-4 py-3 rounded-lg', darkMode ? 'bg-gray-700 text-white' : 'bg-gray-100 text-gray-900']">
                            Total: {{ filteredGroups().length }}
                        </div>
                    </div>
                </div>

                <!-- Tabla de grupos -->
                <div :class="['rounded-2xl border shadow-lg overflow-hidden', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <!-- Loading state -->
                    <div v-if="loading" class="p-12 text-center">
                        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-purple-400' : 'border-purple-600'"></div>
                        <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando grupos...</p>
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="filteredGroups().length === 0" class="p-12 text-center">
                        <UserGroupIcon class="w-24 h-24 mx-auto mb-4 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" />
                        <p :class="['font-body text-lg font-semibold', darkMode ? 'text-gray-400' : 'text-gray-600']">
                            {{ searchQuery ? 'No se encontraron grupos' : 'No hay grupos registrados' }}
                        </p>
                        <button 
                            v-if="!searchQuery"
                            @click="openAddModal"
                            :class="['font-body mt-4 px-6 py-3 rounded-lg font-semibold transition-all flex items-center gap-2 mx-auto', darkMode ? 'bg-purple-500 hover:bg-purple-600 text-white' : 'bg-purple-500 hover:bg-purple-600 text-white']"
                        >
                            <PlusIcon class="w-5 h-5" />
                            Crear Primer Grupo
                        </button>
                    </div>

                    <!-- Groups table -->
                    <div v-else class="overflow-x-auto">
                        <table class="w-full">
                            <thead :class="darkMode ? 'bg-gray-700' : 'bg-gray-100'">
                                <tr>
                                    <th :class="['font-body px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-200' : 'text-gray-500']">
                                        Carrera
                                    </th>
                                    <th :class="['font-body px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-200' : 'text-gray-500']">
                                        Grado
                                    </th>
                                    <th :class="['font-body px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-200' : 'text-gray-500']">
                                        Grupo
                                    </th>
                                    <th :class="['font-body px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-200' : 'text-gray-500']">
                                        Estudiantes
                                    </th>
                                    <th :class="['font-body px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-200' : 'text-gray-500']">
                                        Tutor
                                    </th>
                                    <th :class="['font-body px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide', darkMode ? 'text-gray-200' : 'text-gray-500']">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y" :class="darkMode ? 'divide-gray-700' : 'divide-gray-200'">
                                <tr 
                                    v-for="group in filteredGroups()" 
                                    :key="group.id"
                                    :class="['hover:bg-opacity-50 transition-colors', darkMode ? 'hover:bg-gray-700' : 'hover:bg-gray-50']"
                                >
                                    <td :class="['font-body px-6 py-4 whitespace-nowrap text-sm font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                        {{ group.carrera }}
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap']">
                                        <span :class="['font-body inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold', darkMode ? 'bg-green-500/20 text-green-400' : 'bg-green-100 text-green-800']">
                                            {{ group.grado }}° Grado
                                        </span>
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap']">
                                        <span :class="['font-body inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold', darkMode ? 'bg-purple-500/20 text-purple-400' : 'bg-purple-100 text-purple-800']">
                                            Grupo {{ group.grupo }}
                                        </span>
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap']">
                                        <span :class="['font-body inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold', darkMode ? 'bg-indigo-500/20 text-indigo-400' : 'bg-indigo-100 text-indigo-800']">
                                            {{ group.students_count || 0 }} estudiantes
                                        </span>
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap']">
                                        <div v-if="group.tutor" class="flex items-center">
                                            <span :class="['font-body text-sm', darkMode ? 'text-white' : 'text-gray-900']">
                                                {{ group.tutor.name }}
                                            </span>
                                        </div>
                                        <span v-else :class="['font-body text-sm italic', darkMode ? 'text-gray-500' : 'text-gray-400']">
                                            Sin tutor asignado
                                        </span>
                                    </td>
                                    <td :class="['px-6 py-4 whitespace-nowrap text-center text-sm font-medium']">
                                        <div class="flex items-center justify-center space-x-2">
                                            <button
                                                @click="openDetailModal(group)"
                                                :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-indigo-400' : 'hover:bg-indigo-50 text-indigo-600']"
                                                title="Ver Detalle"
                                            >
                                                <EyeIcon class="w-5 h-5" />
                                            </button>
                                            <button
                                                @click="openAssignTutorModal(group)"
                                                :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-blue-400' : 'hover:bg-blue-50 text-blue-600']"
                                                title="Asignar Tutor"
                                            >
                                                <AcademicCapIcon class="w-5 h-5" />
                                            </button>
                                            <button
                                                @click="openEditModal(group)"
                                                :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-indigo-400' : 'hover:bg-indigo-50 text-indigo-600']"
                                                title="Editar"
                                            >
                                                <PencilIcon class="w-5 h-5" />
                                            </button>
                                            <button
                                                @click="deleteGroup(group)"
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

                <!-- Modal Agregar Grupo -->
                <div 
                    v-if="showAddModal" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showAddModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    Nuevo Grupo
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
                                <!-- Área de Estudio -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Área de Estudio *
                                    </label>
                                    <select
                                        v-model="newGroup.area_id"
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
                                        v-model="newGroup.carrera"
                                        :disabled="!newGroup.area_id || careers.length === 0"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? (newGroup.area_id && careers.length > 0 ? 'bg-gray-700 border-gray-600 text-white' : 'bg-gray-800 border-gray-700 text-gray-500 cursor-not-allowed') : (newGroup.area_id && careers.length > 0 ? 'bg-white border-gray-300 text-gray-900' : 'bg-gray-100 border-gray-300 text-gray-500 cursor-not-allowed')]"
                                    >
                                        <option value="">{{ newGroup.area_id ? (careers.length === 0 ? 'No hay carreras disponibles' : 'Seleccione una carrera') : 'Primero seleccione un área' }}</option>
                                        <option v-for="career in careers" :key="career.id" :value="career.nombre">
                                            {{ career.nombre }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Grado -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Grado *
                                    </label>
                                    <select
                                        v-model="newGroup.grado"
                                        :disabled="!newGroup.carrera"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? (newGroup.carrera ? 'bg-gray-700 border-gray-600 text-white' : 'bg-gray-800 border-gray-700 text-gray-500 cursor-not-allowed') : (newGroup.carrera ? 'bg-white border-gray-300 text-gray-900' : 'bg-gray-100 border-gray-300 text-gray-500 cursor-not-allowed')]"
                                    >
                                        <option value="">{{ newGroup.carrera ? 'Seleccione un grado' : 'Primero seleccione una carrera' }}</option>
                                        <option 
                                            v-for="grado in gradosDisponibles" 
                                            :key="grado" 
                                            :value="grado"
                                            :disabled="!gradosDisponibles.includes(grado)"
                                        >
                                            {{ grado }}° Grado
                                        </option>
                                    </select>
                                    <p v-if="newGroup.carrera && gradosDisponibles.length === 0" :class="['mt-1 text-xs', darkMode ? 'text-yellow-400' : 'text-yellow-600']">
                                        Todos los grados ya tienen grupos creados para esta carrera
                                    </p>
                                </div>

                                <!-- Grupo -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Grupo *
                                    </label>
                                    <select
                                        v-model="newGroup.grupo"
                                        :disabled="!newGroup.grado || !newGroup.carrera || gruposDisponibles.length === 0"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? (newGroup.grado && newGroup.carrera && gruposDisponibles.length > 0 ? 'bg-gray-700 border-gray-600 text-white' : 'bg-gray-800 border-gray-700 text-gray-500 cursor-not-allowed') : (newGroup.grado && newGroup.carrera && gruposDisponibles.length > 0 ? 'bg-white border-gray-300 text-gray-900' : 'bg-gray-100 border-gray-300 text-gray-500 cursor-not-allowed')]"
                                    >
                                        <option value="">
                                            {{ !newGroup.carrera ? 'Primero seleccione una carrera' : (!newGroup.grado ? 'Primero seleccione un grado' : (gruposDisponibles.length === 0 ? 'No hay grupos disponibles (todos están ocupados)' : 'Seleccione un grupo')) }}
                                        </option>
                                        <option 
                                            v-for="grupo in gruposDisponibles" 
                                            :key="grupo" 
                                            :value="grupo"
                                        >
                                            Grupo {{ grupo }}
                                        </option>
                                    </select>
                                    <p v-if="newGroup.grado && newGroup.carrera && gruposDisponibles.length === 0" :class="['mt-1 text-xs', darkMode ? 'text-yellow-400' : 'text-yellow-600']">
                                        ⚠️ Todos los grupos (A-E) ya están ocupados para este grado y carrera. No se pueden crear más grupos.
                                    </p>
                                    <p v-else-if="newGroup.grado && newGroup.carrera && gruposDisponibles.length > 0" :class="['mt-1 text-xs', darkMode ? 'text-green-400' : 'text-green-600']">
                                        ✓ Grupos disponibles: {{ gruposDisponibles.join(', ') }}
                                    </p>
                                    <!-- Debug temporal -->
                                    <p v-if="newGroup.carrera && newGroup.grado" :class="['mt-1 text-xs font-mono', darkMode ? 'text-gray-500' : 'text-gray-400']">
                                        Debug: {{ gruposDisponibles.length }} disponibles de {{ allGrupos.length }} ({{ gruposDisponibles.join(', ') || 'ninguno' }})
                                    </p>
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
                                    @click="addGroup"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-purple-600 hover:bg-purple-700 text-white' : 'bg-purple-500 hover:bg-purple-600 text-white']"
                                >
                                    Crear Grupo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Editar Grupo -->
                <div 
                    v-if="showEditModal" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showEditModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                    Editar Grupo
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
                                <!-- Área de Estudio -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Área de Estudio *
                                    </label>
                                    <select
                                        v-model="editGroup.area_id"
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
                                        v-model="editGroup.carrera"
                                        :disabled="!editGroup.area_id || careers.length === 0"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? (editGroup.area_id && careers.length > 0 ? 'bg-gray-700 border-gray-600 text-white' : 'bg-gray-800 border-gray-700 text-gray-500 cursor-not-allowed') : (editGroup.area_id && careers.length > 0 ? 'bg-white border-gray-300 text-gray-900' : 'bg-gray-100 border-gray-300 text-gray-500 cursor-not-allowed')]"
                                    >
                                        <option value="">{{ editGroup.area_id ? (careers.length === 0 ? 'No hay carreras disponibles' : 'Seleccione una carrera') : 'Primero seleccione un área' }}</option>
                                        <option v-for="career in careers" :key="career.id" :value="career.nombre">
                                            {{ career.nombre }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Grado -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Grado *
                                    </label>
                                    <select
                                        v-model="editGroup.grado"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
                                        <option value="">Seleccione un grado</option>
                                        <option v-for="grado in grados" :key="grado" :value="grado">
                                            {{ grado }}° Grado
                                        </option>
                                    </select>
                                </div>

                                <!-- Grupo -->
                                <div>
                                    <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        Grupo *
                                    </label>
                                    <select
                                        v-model="editGroup.grupo"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    >
                                        <option value="">Seleccione un grupo</option>
                                        <option v-for="grupo in gruposDisponibles" :key="grupo" :value="grupo">
                                            Grupo {{ grupo }}
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
                                    @click="updateGroup"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-purple-600 hover:bg-purple-700 text-white' : 'bg-purple-500 hover:bg-purple-600 text-white']"
                                >
                                    Actualizar Grupo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Detalle del Grupo -->
                <div 
                    v-if="showDetailModal && selectedGroup" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showDetailModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                        Detalle del Grupo
                                    </h2>
                                    <p :class="['text-sm mt-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                        {{ selectedGroup.carrera }} - {{ selectedGroup.grado }}° Grado - Grupo {{ selectedGroup.grupo }}
                                    </p>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <button 
                                        @click="openAssignModal(selectedGroup)"
                                        :class="['px-4 py-2 rounded-lg font-semibold transition-colors', darkMode ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'bg-indigo-500 hover:bg-indigo-600 text-white']"
                                    >
                                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Agregar Estudiantes
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
                            <div v-if="loadingStudents" class="text-center py-12">
                                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-indigo-400' : 'border-indigo-600'"></div>
                                <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando estudiantes...</p>
                            </div>

                            <!-- Empty state -->
                            <div v-else-if="groupStudents.length === 0" class="text-center py-12">
                                <svg class="w-24 h-24 mx-auto mb-4 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                <p :class="['text-lg font-semibold mb-4', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                    No hay estudiantes asignados a este grupo
                                </p>
                                <button 
                                    @click="openAssignModal(selectedGroup)"
                                    :class="['px-6 py-3 rounded-xl font-semibold transition-colors', darkMode ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'bg-indigo-500 hover:bg-indigo-600 text-white']"
                                >
                                    Agregar Primer Estudiante
                                </button>
                            </div>

                            <!-- Students list -->
                            <div v-else class="space-y-3">
                                <div 
                                    v-for="student in groupStudents" 
                                    :key="student.id"
                                    :class="['p-4 rounded-lg border flex items-center justify-between', darkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-200']"
                                >
                                    <div class="flex-1">
                                        <p :class="['font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                            {{ student.user?.name }}
                                        </p>
                                        <p :class="['text-sm mt-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                            Matrícula: {{ student.matricula }}
                                        </p>
                                    </div>
                                    <button
                                        @click="removeStudent(student)"
                                        :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-600 text-red-400' : 'hover:bg-red-50 text-red-600']"
                                        title="Remover del grupo"
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

                <!-- Modal Asignar Estudiantes -->
                <div 
                    v-if="showAssignModal && selectedGroup" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showAssignModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                        Asignar Estudiantes
                                    </h2>
                                    <p :class="['text-sm mt-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                        {{ selectedGroup.carrera }} - {{ selectedGroup.grado }}° Grado - Grupo {{ selectedGroup.grupo }}
                                    </p>
                                </div>
                                <button 
                                    @click="showAssignModal = false"
                                    :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-gray-400' : 'hover:bg-gray-100 text-gray-600']"
                                >
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="p-6">
                            <!-- Loading state -->
                            <div v-if="loadingStudents" class="text-center py-12">
                                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-indigo-400' : 'border-indigo-600'"></div>
                                <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando estudiantes disponibles...</p>
                            </div>

                            <!-- Empty state -->
                            <div v-else-if="availableStudents.length === 0" class="text-center py-12">
                                <svg class="w-24 h-24 mx-auto mb-4 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                <p :class="['text-lg font-semibold', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                    No hay estudiantes disponibles para asignar
                                </p>
                            </div>

                            <!-- Students list -->
                            <div v-else class="space-y-3 mb-6">
                                <div 
                                    v-for="student in availableStudents" 
                                    :key="student.id"
                                    :class="['p-4 rounded-lg border flex items-center justify-between cursor-pointer transition-colors', darkMode ? selectedStudentIds.includes(student.id) ? 'bg-indigo-600 border-indigo-500' : 'bg-gray-700 border-gray-600 hover:bg-gray-600' : selectedStudentIds.includes(student.id) ? 'bg-indigo-50 border-indigo-300' : 'bg-gray-50 border-gray-200 hover:bg-gray-100']"
                                    @click="toggleStudent(student.id)"
                                >
                                    <div class="flex items-center flex-1">
                                        <input
                                            type="checkbox"
                                            :checked="selectedStudentIds.includes(student.id)"
                                            @change="toggleStudent(student.id)"
                                            :class="['w-5 h-5 rounded border mr-4', darkMode ? 'border-gray-500' : 'border-gray-300']"
                                        >
                                        <div class="flex-1">
                                            <p :class="['font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                                {{ student.name }}
                                            </p>
                                            <p :class="['text-sm mt-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                                Matrícula: {{ student.student_detail?.matricula || 'N/A' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end space-x-4">
                                <button
                                    @click="showAssignModal = false"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                                >
                                    Cancelar
                                </button>
                                <button
                                    @click="assignStudents"
                                    :disabled="selectedStudentIds.length === 0"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? (selectedStudentIds.length === 0 ? 'bg-gray-600 text-gray-400 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700 text-white') : (selectedStudentIds.length === 0 ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-indigo-500 hover:bg-indigo-600 text-white')]"
                                >
                                    Asignar {{ selectedStudentIds.length > 0 ? `(${selectedStudentIds.length})` : '' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Asignar Tutor -->
                <div 
                    v-if="showAssignTutorModal" 
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                    @click.self="showAssignTutorModal = false"
                >
                    <div :class="['rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                        <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                                        Asignar Tutor
                                    </h2>
                                    <p :class="['text-sm mt-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                        {{ selectedGroup?.carrera }} - {{ selectedGroup?.grado }}° Grado - Grupo {{ selectedGroup?.grupo }}
                                    </p>
                                </div>
                                <button 
                                    @click="showAssignTutorModal = false; selectedTutorId = null"
                                    :class="['p-2 rounded-lg transition-colors', darkMode ? 'hover:bg-gray-700 text-gray-400' : 'hover:bg-gray-100 text-gray-600']"
                                >
                                    <XMarkIcon class="w-6 h-6" />
                                </button>
                            </div>
                        </div>

                        <div class="p-6">
                            <!-- Loading state -->
                            <div v-if="loadingTutors" class="text-center py-12">
                                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-blue-400' : 'border-blue-600'"></div>
                                <p :class="['mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando maestros...</p>
                            </div>

                            <!-- Tutors list -->
                            <div v-else class="space-y-3 mb-6">
                                <div 
                                    :class="['p-4 rounded-lg border cursor-pointer transition-colors', darkMode ? (selectedTutorId === null ? 'bg-blue-600 border-blue-500' : 'bg-gray-700 border-gray-600 hover:bg-gray-600') : (selectedTutorId === null ? 'bg-blue-50 border-blue-300' : 'bg-gray-50 border-gray-200 hover:bg-gray-100')]"
                                    @click="selectedTutorId = null"
                                >
                                    <div class="flex items-center">
                                        <input
                                            type="radio"
                                            :checked="selectedTutorId === null"
                                            @change="selectedTutorId = null"
                                            :class="['w-5 h-5 mr-4', darkMode ? 'text-blue-400' : 'text-blue-600']"
                                        >
                                        <div>
                                            <p :class="['font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                                Sin tutor (Remover tutor actual)
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div 
                                    v-for="tutor in availableTutors" 
                                    :key="tutor.id"
                                    :class="['p-4 rounded-lg border cursor-pointer transition-colors', darkMode ? (selectedTutorId === tutor.id ? 'bg-blue-600 border-blue-500' : 'bg-gray-700 border-gray-600 hover:bg-gray-600') : (selectedTutorId === tutor.id ? 'bg-blue-50 border-blue-300' : 'bg-gray-50 border-gray-200 hover:bg-gray-100')]"
                                    @click="selectedTutorId = tutor.id"
                                >
                                    <div class="flex items-center">
                                        <input
                                            type="radio"
                                            :checked="selectedTutorId === tutor.id"
                                            @change="selectedTutorId = tutor.id"
                                            :class="['w-5 h-5 mr-4', darkMode ? 'text-blue-400' : 'text-blue-600']"
                                        >
                                        <div>
                                            <p :class="['font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                                {{ tutor.name }}
                                            </p>
                                            <p :class="['text-sm mt-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                                {{ tutor.email }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end space-x-4">
                                <button
                                    @click="showAssignTutorModal = false; selectedTutorId = null"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                                >
                                    Cancelar
                                </button>
                                <button
                                    @click="assignTutor"
                                    :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                                >
                                    {{ selectedTutorId === null ? 'Remover Tutor' : 'Asignar Tutor' }}
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

