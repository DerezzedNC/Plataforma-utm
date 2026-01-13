<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Estados de filtros
const areas = ref([]);
const careers = ref([]);
const selectedArea = ref(null);
const selectedCareer = ref(null);
const selectedGrado = ref('');
const selectedGrupo = ref(null);
const availableGroups = ref([]);
const availableSubjects = ref([]);

// Estados del calendario
const schedules = ref([]);
const timeSlots = ref([]);
const daysOfWeek = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];

// Estados de drag & drop
const draggedSubject = ref(null);
const draggedBlock = ref(null);

// Estados del pop-over
const showPopover = ref(false);
const popoverBlock = ref(null);
const popoverPosition = ref({ x: 0, y: 0 });

// Formulario del pop-over
const popoverForm = ref({
    materia: '',
    profesor: '',
    aula: '',
    building_id: '',
    hora_inicio: '07:00',
    hora_fin: '08:30',
    tipo: 'Teoría'
});

// Estado para controlar si el maestro está asignado automáticamente
const teacherAutoAssigned = ref(false);
const checkingAssignment = ref(false);

// Datos adicionales
const teachers = ref([]);
const buildings = ref([]);
const rooms = ref([]);
const filteredRooms = ref([]);
const availableRooms = ref([]); // Salones disponibles según horario seleccionado
const loadingRooms = ref(false); // Estado de carga para salones disponibles
const conflicts = ref({ has_conflicts: false, conflicts: {} });
const loading = ref(false);

// Generar slots de tiempo (7:00 AM - 2:20 PM)
// Estructura: módulos de 50 minutos con descansos como filas completas
const generateTimeSlots = () => {
    const slots = [];
    
    // Definir los módulos con sus rangos de tiempo
    const timeSchedule = [
        { start: '07:00', end: '07:50', type: 'class' }, // Módulo 1
        { start: '07:50', end: '08:40', type: 'class' }, // Módulo 2
        { start: '08:40', end: '09:00', type: 'break', label: 'Primer Receso' },  // DESCANSO
        { start: '09:00', end: '09:50', type: 'class' }, // Módulo 3
        { start: '09:50', end: '10:40', type: 'class' }, // Módulo 4
        { start: '10:40', end: '11:00', type: 'break', label: 'Segundo Receso' },  // DESCANSO
        { start: '11:00', end: '11:50', type: 'class' }, // Módulo 5
        { start: '11:50', end: '12:40', type: 'class' }, // Módulo 6
        { start: '12:40', end: '13:30', type: 'class' }, // Módulo 7
        { start: '13:30', end: '14:20', type: 'class' }, // Módulo 8
    ];
    
    return timeSchedule;
};

timeSlots.value = generateTimeSlots();

// Cargar datos iniciales
const loadAreas = async () => {
    try {
        const response = await axios.get('/admin/areas');
        areas.value = response.data;
    } catch (error) {
        console.error('Error cargando áreas:', error);
    }
};

const loadCareers = async () => {
    if (selectedArea.value === null || selectedArea.value === '') {
        console.log('No hay área seleccionada, limpiando carreras');
        careers.value = [];
        return;
    }
    try {
        console.log('Cargando carreras para área:', selectedArea.value);
        const response = await axios.get('/admin/careers', { params: { area_id: selectedArea.value } });
        console.log('Carreras cargadas:', response.data);
        careers.value = response.data;
    } catch (error) {
        console.error('Error cargando carreras:', error);
        if (error.response) {
            console.error('Respuesta del servidor:', error.response.status, error.response.data);
        }
    }
};

const loadGroups = async () => {
    if (!selectedCareer.value || !selectedGrado.value) {
        console.log('Faltan datos para cargar grupos. Carrera:', selectedCareer.value, 'Grado:', selectedGrado.value);
        availableGroups.value = [];
        return;
    }
    try {
        const career = careers.value.find(c => c.id == selectedCareer.value);
        if (!career) {
            console.log('Carrera no encontrada con id:', selectedCareer.value);
            return;
        }
        
        console.log('Cargando grupos para carrera:', career.nombre, 'grado:', selectedGrado.value);
        const response = await axios.get('/admin/schedules/groups/list', {
            params: {
                carrera: career.nombre,
                grado: selectedGrado.value
            }
        });
        console.log('Grupos cargados:', response.data);
        availableGroups.value = response.data;
    } catch (error) {
        console.error('Error cargando grupos:', error);
        if (error.response) {
            console.error('Respuesta del servidor:', error.response.status, error.response.data);
        }
    }
};

const loadSubjects = async () => {
    if (!selectedCareer.value || !selectedGrado.value) {
        console.log('Faltan datos para cargar materias. Carrera:', selectedCareer.value, 'Grado:', selectedGrado.value);
        availableSubjects.value = [];
        return;
    }
    try {
        const career = careers.value.find(c => c.id == selectedCareer.value);
        if (!career) {
            console.log('Carrera no encontrada para cargar materias con id:', selectedCareer.value);
            return;
        }
        
        console.log('Cargando materias para carrera:', career.nombre, 'grado:', selectedGrado.value);
        const response = await axios.get('/admin/schedules/subjects/list', {
            params: {
                carrera: career.nombre,
                grado: selectedGrado.value
            }
        });
        console.log('Materias cargadas:', response.data);
        availableSubjects.value = response.data;
    } catch (error) {
        console.error('Error cargando materias:', error);
        if (error.response) {
            console.error('Respuesta del servidor:', error.response.status, error.response.data);
        }
    }
};

const loadSchedules = async () => {
    if (!selectedCareer.value || !selectedGrado.value || selectedGrupo.value === null || selectedGrupo.value === '') {
        schedules.value = [];
        return;
    }
    try {
        loading.value = true;
        const career = careers.value.find(c => c.id == selectedCareer.value);
        if (!career) {
            console.log('Carrera no encontrada para cargar horarios');
            schedules.value = [];
            return;
        }
        
        const group = availableGroups.value.find(g => g.id == selectedGrupo.value);
        if (!group) {
            console.log('Grupo no encontrado para cargar horarios');
            schedules.value = [];
            return;
        }
        
        console.log('Cargando horarios para:', { carrera: career.nombre, grado: selectedGrado.value, grupo: group.grupo });
        const response = await axios.get('/admin/schedules', {
            params: {
                carrera: career.nombre,
                grado: selectedGrado.value,
                grupo: group.grupo
            }
        });
        
        // Validar y limpiar los datos recibidos
        schedules.value = (response.data || []).filter(schedule => {
            // Validar que el schedule tiene todas las propiedades necesarias
            return schedule && 
                   schedule.dia_semana && 
                   schedule.hora_inicio && 
                   schedule.hora_fin &&
                   schedule.materia;
        });
        
        console.log('Horarios cargados:', schedules.value.length);
    } catch (error) {
        console.error('Error cargando horarios:', error);
        if (error.response) {
            console.error('Respuesta del servidor:', error.response.status, error.response.data);
        }
        schedules.value = [];
    } finally {
        loading.value = false;
    }
};

const loadTeachers = async () => {
    try {
        const response = await axios.get('/admin/teachers');
        teachers.value = response.data;
    } catch (error) {
        console.error('Error cargando maestros:', error);
    }
};

const loadBuildings = async () => {
    try {
        const response = await axios.get('/admin/buildings');
        buildings.value = response.data;
    } catch (error) {
        console.error('Error cargando edificios:', error);
    }
};

const loadRooms = async () => {
    try {
        const response = await axios.get('/admin/rooms');
        rooms.value = response.data;
    } catch (error) {
        console.error('Error cargando aulas:', error);
    }
};

// Watch para filtros
watch(selectedArea, (newValue) => {
    console.log('Área seleccionada:', newValue);
    selectedCareer.value = null;
    selectedGrado.value = '';
    selectedGrupo.value = null;
    if (newValue) {
        loadCareers();
    } else {
        careers.value = [];
    }
}, { immediate: false });

watch(selectedCareer, (newValue) => {
    console.log('Carrera seleccionada:', newValue);
    selectedGrado.value = '';
    selectedGrupo.value = null;
    if (newValue && selectedArea.value) {
        loadGroups();
        loadSubjects();
    } else {
        availableGroups.value = [];
        availableSubjects.value = [];
    }
}, { immediate: false });

watch(selectedGrado, (newValue) => {
    console.log('Grado seleccionado:', newValue);
    selectedGrupo.value = null;
    if (newValue && selectedCareer.value) {
        loadGroups();
        loadSubjects();
    } else {
        availableGroups.value = [];
        availableSubjects.value = [];
    }
}, { immediate: false });

watch(selectedGrupo, (newValue) => {
    console.log('Grupo seleccionado:', newValue);
    if (newValue !== null && newValue !== '' && selectedCareer.value && selectedGrado.value) {
        loadSchedules();
    } else {
        schedules.value = [];
    }
}, { immediate: false });

watch(() => popoverForm.value.building_id, (buildingId) => {
    // Recargar salones disponibles cuando cambia el edificio
    // Esto asegura que se filtren correctamente desde el servidor
    if (showPopover.value && popoverBlock.value?.day && popoverForm.value.hora_inicio && popoverForm.value.hora_fin) {
        loadAvailableRooms();
    }
    popoverForm.value.aula = '';
});

// Watch para normalizar hora_inicio cuando cambia
watch(() => popoverForm.value.hora_inicio, (newValue) => {
    if (newValue && showPopover.value) {
        const normalized = normalizeTime(newValue);
        if (normalized !== newValue) {
            popoverForm.value.hora_inicio = normalized;
        }
    }
});

// Watch para normalizar hora_fin cuando cambia
// IMPORTANTE: Asegurar que las horas de 12:XX se mantengan como 12:XX (no se conviertan a 00:XX)
watch(() => popoverForm.value.hora_fin, (newValue) => {
    if (newValue && showPopover.value) {
        const normalized = normalizeTime(newValue);
        // Solo actualizar si el valor normalizado es diferente Y válido
        // Esto previene conversiones incorrectas de 12:XX a 00:XX
        if (normalized && normalized !== newValue) {
            // Verificar que la normalización no haya convertido incorrectamente
            const originalMinutes = timeToMinutes(newValue);
            const normalizedMinutes = timeToMinutes(normalized);
            
            // Si los minutos son diferentes, podría ser una conversión incorrecta
            if (Math.abs(originalMinutes - normalizedMinutes) > 60) {
                console.warn('Posible conversión incorrecta de hora:', { 
                    original: newValue, 
                    normalized, 
                    originalMinutes, 
                    normalizedMinutes 
                });
            } else {
                popoverForm.value.hora_fin = normalized;
            }
        }
    }
});

// Watch para detectar cambios en día, hora_inicio y hora_fin
watch([
    () => popoverBlock.value?.day,
    () => popoverForm.value.hora_inicio,
    () => popoverForm.value.hora_fin
], ([day, startTime, endTime]) => {
    // Solo cargar si todos los valores están presentes y el popover está abierto
    if (showPopover.value && day && startTime && endTime) {
        // Pequeño delay para asegurar que los valores estén normalizados
        setTimeout(() => {
            loadAvailableRooms();
        }, 50);
    }
}, { deep: true });

// Watch para detectar cambios en la materia (subject) y verificar asignación de maestro
watch(
    () => popoverBlock.value?.subject?.id,
    async (subjectId) => {
        // Solo verificar si hay grupo y materia seleccionados y el popover está abierto
        if (showPopover.value && subjectId && selectedGrupo.value) {
            await checkTeacherAssignment(selectedGrupo.value, subjectId);
        } else {
            // Si no hay materia o grupo, resetear el estado
            teacherAutoAssigned.value = false;
            if (!subjectId) {
                popoverForm.value.profesor = '';
            }
        }
    },
    { immediate: false }
);

// Función para verificar asignación de maestro
const checkTeacherAssignment = async (groupId = null, subjectId = null) => {
    const groupIdToUse = groupId || selectedGrupo.value;
    const subjectIdToUse = subjectId || popoverBlock.value?.subject?.id;
    
    if (!subjectIdToUse || !groupIdToUse) {
        return;
    }

    try {
        checkingAssignment.value = true;
        teacherAutoAssigned.value = false;

        const response = await axios.get(`/admin/schedules/check-assignment/${groupIdToUse}/${subjectIdToUse}`);
        
        if (response.data.has_assignment && response.data.teacher_name) {
            // Auto-seleccionar el maestro y deshabilitar el selector
            popoverForm.value.profesor = response.data.teacher_name;
            teacherAutoAssigned.value = true;
        } else {
            // No hay asignación, habilitar selección manual
            teacherAutoAssigned.value = false;
            // No limpiar el profesor si ya tiene un valor (permite corrección manual)
        }
    } catch (error) {
        console.error('Error verificando asignación de maestro:', error);
        teacherAutoAssigned.value = false;
    } finally {
        checkingAssignment.value = false;
    }
};


// Función helper para convertir hora a minutos totales
const timeToMinutes = (timeStr) => {
    if (!timeStr) return 0;
    // Remover segundos si existen
    const timeOnly = timeStr.substring(0, 5);
    const [hours, minutes] = timeOnly.split(':').map(Number);
    return (hours || 0) * 60 + (minutes || 0);
};

// Función helper para normalizar formato de hora (H:i) - Formato 24 horas
// IMPORTANTE: NO convierte 12:XX a 00:XX, mantiene formato 24 horas
const normalizeTime = (timeStr) => {
    if (!timeStr) return '';
    
    // Si ya está en formato correcto, retornarlo
    if (typeof timeStr !== 'string') {
        timeStr = String(timeStr);
    }
    
    // Remover espacios y segundos si existen
    let timeOnly = timeStr.trim().substring(0, 5);
    
    // Validar formato básico
    if (!/^\d{1,2}:\d{2}$/.test(timeOnly)) {
        console.warn('Formato de hora inválido:', timeStr);
        return '';
    }
    
    const [hours, minutes] = timeOnly.split(':').map(Number);
    
    // Validar rangos - Formato 24 horas (0-23 horas, 0-59 minutos)
    // CRÍTICO: NO convertir horas >= 12 a 0, mantener formato 24 horas
    if (isNaN(hours) || isNaN(minutes) || hours < 0 || hours > 23 || minutes < 0 || minutes > 59) {
        console.warn('Valores de hora fuera de rango:', { hours, minutes, original: timeStr });
        return '';
    }
    
    // Formatear en formato 24 horas (HH:MM)
    // Ejemplos: 00:00, 11:00, 12:40, 13:00, 23:59
    // NUNCA convertir 12 a 0
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
};

// Función para cargar salones disponibles
const loadAvailableRooms = async () => {
    if (!popoverBlock.value?.day || !popoverForm.value.hora_inicio || !popoverForm.value.hora_fin) {
        availableRooms.value = [];
        return;
    }

    // Normalizar formato de hora (asegurar formato H:i)
    let horaInicio = normalizeTime(popoverForm.value.hora_inicio);
    let horaFin = normalizeTime(popoverForm.value.hora_fin);
    
    // Validar que hora_fin sea mayor que hora_inicio usando minutos totales
    const minutosInicio = timeToMinutes(horaInicio);
    const minutosFin = timeToMinutes(horaFin);
    
    if (minutosFin <= minutosInicio) {
        console.warn('Hora fin debe ser mayor que hora inicio:', { 
            horaInicio, 
            horaFin, 
            minutosInicio, 
            minutosFin,
            rawInicio: popoverForm.value.hora_inicio,
            rawFin: popoverForm.value.hora_fin
        });
        availableRooms.value = [];
        return;
    }

    // Normalizar el día (primera letra mayúscula, resto minúsculas)
    const day = popoverBlock.value.day;
    const normalizedDay = day.charAt(0).toUpperCase() + day.slice(1).toLowerCase();

    try {
        loadingRooms.value = true;
        
        const params = {
            day: normalizedDay,
            start_time: horaInicio,
            end_time: horaFin,
        };

        // Incluir building_id si está seleccionado
        if (popoverForm.value.building_id) {
            params.building_id = popoverForm.value.building_id;
        }

        // Incluir exclude_schedule_id si estamos editando
        if (popoverBlock.value.isEdit && popoverBlock.value.id) {
            params.exclude_schedule_id = popoverBlock.value.id;
        }

        console.log('Cargando salones disponibles con parámetros:', {
            ...params,
            horaInicioOriginal: popoverForm.value.hora_inicio,
            horaFinOriginal: popoverForm.value.hora_fin,
            minutosInicio,
            minutosFin,
            duracionMinutos: minutosFin - minutosInicio
        });

        const response = await axios.get('/admin/rooms/available', { params });
        const allRooms = response.data || [];
        
        console.log('Salones recibidos del backend (antes de filtrar):', allRooms.length);

        // Si hay un building_id seleccionado, filtrar por edificio (ya viene filtrado del backend, pero por si acaso)
        if (popoverForm.value.building_id) {
            availableRooms.value = allRooms.filter(r => r.building_id == popoverForm.value.building_id);
            console.log('Salones después de filtrar por edificio:', availableRooms.value.length, 'Edificio:', popoverForm.value.building_id);
        } else {
            availableRooms.value = allRooms;
        }

        console.log('Salones disponibles finales:', availableRooms.value.length, {
            salones: availableRooms.value.map(r => ({ codigo: r.codigo, nombre: r.nombre, edificio: r.building?.nombre }))
        });

        // Limpiar selección de aula si el aula actual no está disponible
        if (popoverForm.value.aula) {
            const isAvailable = availableRooms.value.some(r => r.codigo === popoverForm.value.aula);
            if (!isAvailable) {
                popoverForm.value.aula = '';
            }
        }
    } catch (error) {
        console.error('Error cargando salones disponibles:', error);
        if (error.response) {
            console.error('Respuesta del servidor:', error.response.status, error.response.data);
            if (error.response.status === 422) {
                console.error('Errores de validación:', error.response.data.errors);
            }
        }
        availableRooms.value = [];
    } finally {
        loadingRooms.value = false;
    }
};

// Drag & Drop handlers
const onDragStart = (event, subject) => {
    draggedSubject.value = subject;
    event.dataTransfer.effectAllowed = 'move';
};

const onDragOver = (event) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
};

const onDrop = (event, day, timeSlot) => {
    event.preventDefault();
    if (!draggedSubject.value || selectedGrupo.value === null || selectedGrupo.value === '') return;
    
    const career = careers.value.find(c => c.id == selectedCareer.value);
    const group = availableGroups.value.find(g => g.id == selectedGrupo.value);
    
    if (!career || !group) return;
    
    // Calcular posición en la cuadrícula para el pop-over
    const rect = event.target.getBoundingClientRect();
    popoverPosition.value = {
        x: rect.left + rect.width / 2,
        y: rect.top + rect.height / 2
    };
    
    // Configurar formulario inicial
    // Asegurar que timeSlot esté en formato H:i
    const normalizedTimeSlot = typeof timeSlot === 'string' ? timeSlot : timeSlot.start;
    const [hours, minutes] = normalizedTimeSlot.split(':').map(Number);
    const formattedStartTime = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
    
    popoverForm.value = {
        materia: draggedSubject.value.nombre,
        profesor: '',
        aula: '',
        building_id: '',
        hora_inicio: formattedStartTime,
        hora_fin: calculateEndTime(formattedStartTime),
        tipo: 'Teoría'
    };
    
    popoverBlock.value = {
        day,
        timeSlot,
        career: career.nombre,
        grupo: group.grupo,
        subject: draggedSubject.value,
        isEdit: false
    };
    
    showPopover.value = true;
    draggedSubject.value = null;
    
    // Resetear estado de asignación automática
    teacherAutoAssigned.value = false;
    popoverForm.value.profesor = '';
    
    // Cargar salones disponibles después de abrir el popover
    // Usar nextTick para asegurar que los valores estén establecidos
    setTimeout(() => {
        // Verificar que todos los valores necesarios estén presentes antes de cargar
        if (popoverBlock.value?.day && popoverForm.value.hora_inicio && popoverForm.value.hora_fin) {
            loadAvailableRooms();
        } else {
            console.warn('No se pueden cargar salones: faltan datos', {
                day: popoverBlock.value?.day,
                hora_inicio: popoverForm.value.hora_inicio,
                hora_fin: popoverForm.value.hora_fin
            });
        }
        // Verificar asignación de maestro si hay grupo y materia
        if (selectedGrupo.value && popoverBlock.value.subject?.id) {
            checkTeacherAssignment(selectedGrupo.value, popoverBlock.value.subject.id);
        }
    }, 100);
};

// Calcular hora fin usando minutos absolutos desde la medianoche
// Duración por defecto: 50 minutos (1 módulo)
// Formato de salida: 24 horas (HH:MM) - ej: 12:40, 13:00, 14:20
const calculateEndTime = (startTime, durationMinutes = 50) => {
    if (!startTime) return '';
    
    // Normalizar el tiempo de inicio
    const normalizedStart = normalizeTime(startTime);
    if (!normalizedStart) return '';
    
    // Convertir hora de inicio a minutos absolutos desde la medianoche
    const [startHours, startMinutes] = normalizedStart.split(':').map(Number);
    const startTotalMinutes = (startHours * 60) + startMinutes;
    
    // Sumar la duración en minutos
    const endTotalMinutes = startTotalMinutes + durationMinutes;
    
    // Convertir de vuelta a horas y minutos (formato 24 horas)
    // Math.floor asegura que las horas sean enteras
    const endHours = Math.floor(endTotalMinutes / 60);
    const endMinutes = endTotalMinutes % 60;
    
    // CRÍTICO: NO usar % 24 aquí porque convierte 12 a 0 incorrectamente
    // Solo usar % 24 si realmente excede 24 horas (caso extremo)
    // Para horarios normales (0-23 horas), usar el valor directo
    // EJEMPLO: 11:00 + 100 min = 660 + 100 = 760 min = 12 horas 40 min
    // endHours = 12, NO debe convertirse a 0
    let finalHours = endHours;
    if (endHours >= 24) {
        finalHours = endHours % 24;
    }
    // NUNCA hacer: if (endHours === 12) endHours = 0; // ESTO ES INCORRECTO
    
    // Formatear en formato 24 horas (HH:MM)
    // IMPORTANTE: Mantener formato 24 horas - 12:50 debe ser 12:50, NO 00:50
    const result = `${String(finalHours).padStart(2, '0')}:${String(endMinutes).padStart(2, '0')}`;
    
    // Debug logging para verificar el cálculo
    console.log('calculateEndTime:', {
        startTime,
        normalizedStart,
        startTotalMinutes,
        durationMinutes,
        endTotalMinutes,
        endHours,
        endMinutes,
        finalHours,
        result
    });
    
    return result;
};

// Guardar bloque de horario
const saveBlock = async () => {
    if (!popoverBlock.value) return;
    
    try {
        loading.value = true;
        
        // Normalizar el día (primera letra mayúscula, resto minúsculas)
        const day = popoverBlock.value.day;
        const normalizedDay = day.charAt(0).toUpperCase() + day.slice(1).toLowerCase();
        
        // Normalizar formato de hora (asegurar formato H:i)
        let horaInicio = normalizeTime(popoverForm.value.hora_inicio);
        let horaFin = normalizeTime(popoverForm.value.hora_fin);
        
        // Validar que hora_fin sea mayor que hora_inicio
        const minutosInicio = timeToMinutes(horaInicio);
        const minutosFin = timeToMinutes(horaFin);
        
        if (minutosFin <= minutosInicio) {
            alert('La hora de fin debe ser mayor que la hora de inicio.');
            loading.value = false;
            return;
        }
        
        // Verificar conflictos primero
        const conflictData = {
            profesor: popoverForm.value.profesor,
            aula: popoverForm.value.aula,
            dia_semana: normalizedDay,
            hora_inicio: horaInicio,
            hora_fin: horaFin
        };
        
        if (popoverBlock.value.isEdit) {
            conflictData.exclude_id = popoverBlock.value.id;
        }
        
        const conflictCheck = await axios.post('/admin/schedules/check-conflicts', conflictData);
        
        if (conflictCheck.data.has_conflicts) {
            conflicts.value = conflictCheck.data;
            alert('¡Advertencia! Hay conflictos detectados (maestro o aula ocupados). Revisa los detalles antes de guardar.');
            return;
        }
        
        conflicts.value = { has_conflicts: false, conflicts: {} };
        
        // Crear o actualizar el horario
        if (popoverBlock.value.isEdit) {
            // Actualizar horario existente
            const updateData = {
                carrera: popoverBlock.value.career,
                grupo: popoverBlock.value.grupo,
                materia: popoverForm.value.materia,
                profesor: popoverForm.value.profesor,
                dia_semana: normalizedDay,
                hora_inicio: horaInicio,
                hora_fin: horaFin,
                aula: popoverForm.value.aula,
                tipo: popoverForm.value.tipo || 'Teoría'
            };

            // Incluir group_id y subject_id para actualizar academic_loads
            // Buscar el grupo si no está en popoverBlock
            let groupIdToUse = selectedGrupo.value;
            if (!groupIdToUse && popoverBlock.value.grupo && popoverBlock.value.career) {
                const group = availableGroups.value.find(g => 
                    g.grupo === popoverBlock.value.grupo && 
                    g.carrera === popoverBlock.value.career
                );
                if (group) {
                    groupIdToUse = group.id;
                }
            }
            
            if (groupIdToUse && popoverBlock.value.subject?.id) {
                updateData.group_id = groupIdToUse;
                updateData.subject_id = popoverBlock.value.subject.id;
                console.log('📝 Editando horario - Enviando:', { group_id: groupIdToUse, subject_id: popoverBlock.value.subject.id });
            } else {
                console.warn('⚠️ No se enviaron group_id o subject_id al editar:', {
                    has_group: !!groupIdToUse,
                    has_subject: !!popoverBlock.value.subject?.id,
                    popoverBlock: popoverBlock.value
                });
            }

            await axios.put(`/admin/schedules/${popoverBlock.value.id}`, updateData);
        } else {
            // Crear nuevo horario
            const scheduleData = {
                carrera: popoverBlock.value.career,
                grupo: popoverBlock.value.grupo,
                materia: popoverForm.value.materia,
                profesor: popoverForm.value.profesor,
                dia_semana: normalizedDay,
                hora_inicio: horaInicio,
                hora_fin: horaFin,
                aula: popoverForm.value.aula,
                tipo: popoverForm.value.tipo || 'Teoría'
            };

            // Incluir group_id y subject_id para guardar en academic_loads
            if (selectedGrupo.value && popoverBlock.value.subject?.id) {
                scheduleData.group_id = selectedGrupo.value;
                scheduleData.subject_id = popoverBlock.value.subject.id;
                console.log('📝 Creando horario - Enviando:', { group_id: selectedGrupo.value, subject_id: popoverBlock.value.subject.id });
            } else {
                console.warn('⚠️ No se enviaron group_id o subject_id al crear:', {
                    has_group: !!selectedGrupo.value,
                    has_subject: !!popoverBlock.value.subject?.id,
                    selectedGrupo: selectedGrupo.value,
                    subject: popoverBlock.value.subject
                });
            }

            await axios.post('/admin/schedules', scheduleData);
        }
        
        showPopover.value = false;
        availableRooms.value = []; // Limpiar salones disponibles al cerrar
        teacherAutoAssigned.value = false; // Resetear estado de asignación automática
        await loadSchedules();
    } catch (error) {
        console.error('Error guardando horario:', error);
        if (error.response) {
            console.error('Respuesta del servidor:', error.response.status, error.response.data);
            if (error.response.status === 422) {
                const errors = error.response.data.errors || {};
                const errorMessages = Object.values(errors).flat().join(', ');
                alert('Error de validación al guardar el horario: ' + (errorMessages || error.response.data.message || 'Datos inválidos'));
            } else {
                alert('Error al guardar el horario: ' + (error.response.data?.message || `Error ${error.response.status}`));
            }
        } else {
            alert('Error al guardar el horario: ' + (error.message || 'Error desconocido'));
        }
    } finally {
        loading.value = false;
    }
};

// Obtener bloques para un día y slot específicos
const getBlocksAtTime = (day, slot) => {
    const blocks = schedules.value.filter(schedule => {
        // Validar que el schedule existe y tiene las propiedades necesarias
        if (!schedule || !schedule.dia_semana || !schedule.hora_inicio || !schedule.hora_fin) {
            return false;
        }
        
        // Filtrar por día (comparación exacta)
        if (schedule.dia_semana !== day) {
            return false;
        }
        
        // Validar formato de hora
        try {
            const scheduleStart = schedule.hora_inicio.substring(0, 5);
            const scheduleEnd = schedule.hora_fin.substring(0, 5);
            
            const slotStart = typeof slot === 'string' ? slot : slot.start;
            const slotEnd = typeof slot === 'string' ? slot : slot.end;
            
            // Verificar si el slot se solapa con el horario del schedule
            // El bloque se muestra si el schedule empieza en este slot o si el slot está dentro del rango del schedule
            const scheduleStartsInSlot = scheduleStart === slotStart;
            const scheduleOverlapsSlot = scheduleStart < slotEnd && scheduleEnd > slotStart;
            
            return scheduleStartsInSlot || scheduleOverlapsSlot;
        } catch (error) {
            console.error('Error procesando horario:', schedule, error);
            return false;
        }
    });
    
    return blocks;
};

// Calcular altura del bloque en píxeles basándose en los slots exactos que ocupa
const getBlockHeight = (startTime, endTime) => {
    if (!startTime || !endTime) return 75;
    
    try {
        // Convertir tiempos a minutos totales
        const [startHours, startMinutes] = startTime.substring(0, 5).split(':').map(Number);
        const [endHours, endMinutes] = endTime.substring(0, 5).split(':').map(Number);
        const startTotal = startHours * 60 + startMinutes;
        const endTotal = endHours * 60 + endMinutes;
        const diffMinutes = endTotal - startTotal;
        
        // Encontrar los slots que abarca el bloque
        let startSlotIndex = -1;
        let endSlotIndex = -1;
        
        for (let i = 0; i < timeSlots.value.length; i++) {
            const slot = timeSlots.value[i];
            const slotStart = slot.start.split(':').map(Number);
            const slotEnd = slot.end.split(':').map(Number);
            const slotStartTotal = slotStart[0] * 60 + slotStart[1];
            const slotEndTotal = slotEnd[0] * 60 + slotEnd[1];
            
            // Si el bloque empieza en este slot o dentro de él
            if (startSlotIndex === -1 && startTotal >= slotStartTotal && startTotal < slotEndTotal) {
                startSlotIndex = i;
            }
            
            // Si el bloque termina en este slot o dentro de él
            if (endTotal > slotStartTotal && endTotal <= slotEndTotal) {
                endSlotIndex = i;
            }
        }
        
        // Si no encontramos los slots, calcular basándose en minutos
        if (startSlotIndex === -1 || endSlotIndex === -1) {
            const baseHeightPerSlot = 75;
            const minutesPerSlot = 50;
            const fullSlots = Math.floor(diffMinutes / minutesPerSlot);
            const remainingMinutes = diffMinutes % minutesPerSlot;
            const fractionHeight = (remainingMinutes / minutesPerSlot) * baseHeightPerSlot;
            return Math.max(75, Math.ceil((fullSlots * baseHeightPerSlot) + fractionHeight));
        }
        
        // Calcular altura basándose en los slots exactos
        // Cada slot tiene 75px de altura (min-h-[75px])
        const slotHeight = 75; // Altura de cada slot
        const numSlots = endSlotIndex - startSlotIndex + 1;
        const totalHeight = numSlots * slotHeight;
        
        // Ajustar para que termine exactamente en el borde del último slot
        // Restar el padding superior e inferior de las celdas (p-1 = 4px cada uno = 8px total)
        return totalHeight - 8; // Restar padding para que termine exactamente en el borde
    } catch (error) {
        console.error('Error calculando altura del bloque:', error, { startTime, endTime });
        return 75;
    }
};

// Calcular offset vertical para bloques que no empiezan exactamente al inicio de un slot
const getBlockTopOffset = (startTime, currentSlot) => {
    if (!startTime || !currentSlot) return 0;
    
    try {
        const timeStr = startTime.substring(0, 5);
        const currentSlotStart = typeof currentSlot === 'string' ? currentSlot : currentSlot.start;
        
        // Si el inicio coincide exactamente con el slot actual, empezar desde el padding
        if (timeStr === currentSlotStart) {
            return 4; // Padding superior de la celda (p-1 = 4px)
        }
        
        // Convertir tiempos a minutos
        const [startHours, startMinutes] = timeStr.split(':').map(Number);
        const [slotHours, slotMinutes] = currentSlotStart.split(':').map(Number);
        const startTotal = startHours * 60 + startMinutes;
        const slotTotal = slotHours * 60 + slotMinutes;
        
        // Si el slot actual es después del inicio, calcular offset desde el inicio del slot
        if (slotTotal <= startTotal) {
            const diffMinutes = startTotal - slotTotal;
            // Cada minuto = 1.5px (75px / 50 minutos)
            // Agregar el padding superior de la celda
            return 4 + (diffMinutes / 50) * 75;
        }
        
        return 4; // Padding superior por defecto
    } catch (error) {
        console.error('Error calculando offset del bloque:', error);
        return 6;
    }
};

// Cache de colores asignados a materias para mantener consistencia
const subjectColorCache = new Map();

// Función para generar un color único para cada materia
const getSubjectColor = (materia) => {
    if (!materia || !materia.trim()) {
        return { bg: 'bg-blue-300', bgDark: 'bg-blue-400', border: 'border-blue-500', borderDark: 'border-blue-600' };
    }
    
    // Normalizar el nombre de la materia (trim y lowercase para comparación)
    const normalizedMateria = materia.trim().toLowerCase();
    
    // Si ya tenemos el color en cache, retornarlo
    if (subjectColorCache.has(normalizedMateria)) {
        return subjectColorCache.get(normalizedMateria);
    }
    
    // Lista de colores pastel fuertes (como en la imagen)
    const colors = [
        { bg: 'bg-green-300', bgDark: 'bg-green-400', border: 'border-green-500', borderDark: 'border-green-600' },
        { bg: 'bg-orange-300', bgDark: 'bg-orange-400', border: 'border-orange-500', borderDark: 'border-orange-600' },
        { bg: 'bg-pink-300', bgDark: 'bg-pink-400', border: 'border-pink-500', borderDark: 'border-pink-600' },
        { bg: 'bg-blue-300', bgDark: 'bg-blue-400', border: 'border-blue-500', borderDark: 'border-blue-600' },
        { bg: 'bg-purple-300', bgDark: 'bg-purple-400', border: 'border-purple-500', borderDark: 'border-purple-600' },
        { bg: 'bg-yellow-300', bgDark: 'bg-yellow-400', border: 'border-yellow-500', borderDark: 'border-yellow-600' },
        { bg: 'bg-cyan-300', bgDark: 'bg-cyan-400', border: 'border-cyan-500', borderDark: 'border-cyan-600' },
        { bg: 'bg-lime-300', bgDark: 'bg-lime-400', border: 'border-lime-500', borderDark: 'border-lime-600' },
        { bg: 'bg-indigo-300', bgDark: 'bg-indigo-400', border: 'border-indigo-500', borderDark: 'border-indigo-600' },
        { bg: 'bg-rose-300', bgDark: 'bg-rose-400', border: 'border-rose-500', borderDark: 'border-rose-600' },
        { bg: 'bg-teal-300', bgDark: 'bg-teal-400', border: 'border-teal-500', borderDark: 'border-teal-600' },
        { bg: 'bg-amber-300', bgDark: 'bg-amber-400', border: 'border-amber-500', borderDark: 'border-amber-600' },
        { bg: 'bg-violet-300', bgDark: 'bg-violet-400', border: 'border-violet-500', borderDark: 'border-violet-600' },
        { bg: 'bg-emerald-300', bgDark: 'bg-emerald-400', border: 'border-emerald-500', borderDark: 'border-emerald-600' },
        { bg: 'bg-fuchsia-300', bgDark: 'bg-fuchsia-400', border: 'border-fuchsia-500', borderDark: 'border-fuchsia-600' },
    ];
    
    // Generar un hash mejorado basado en el nombre de la materia
    let hash = 0;
    for (let i = 0; i < normalizedMateria.length; i++) {
        const char = normalizedMateria.charCodeAt(i);
        hash = ((hash << 5) - hash) + char;
        hash = hash & hash; // Convertir a entero de 32 bits
    }
    // Agregar la longitud del string al hash para más variación
    hash = hash + (normalizedMateria.length * 31);
    
    const index = Math.abs(hash) % colors.length;
    const selectedColor = colors[index];
    
    // Guardar en cache
    subjectColorCache.set(normalizedMateria, selectedColor);
    
    return selectedColor;
};

// Obtener índice del slot de inicio (busca el slot más cercano si no coincide exactamente)
const getStartSlotIndex = (startTime) => {
    if (!startTime) return -1;
    
    try {
        const timeStr = startTime.substring(0, 5);
        
        // Buscar el slot que contiene este tiempo
        let index = timeSlots.value.findIndex(slot => slot.start === timeStr);
        
        // Si no se encuentra exactamente, buscar el slot más cercano anterior
        if (index === -1) {
            // Convertir tiempo a minutos para comparar
            const [hours, minutes] = timeStr.split(':').map(Number);
            const timeMinutes = hours * 60 + minutes;
            
            // Buscar el slot más cercano anterior
            let closestIndex = -1;
            let closestDiff = Infinity;
            
            timeSlots.value.forEach((slot, idx) => {
                const [slotHours, slotMinutes] = slot.start.split(':').map(Number);
                const slotTimeMinutes = slotHours * 60 + slotMinutes;
                
                // Solo considerar slots anteriores o iguales
                if (slotTimeMinutes <= timeMinutes) {
                    const diff = timeMinutes - slotTimeMinutes;
                    if (diff < closestDiff) {
                        closestDiff = diff;
                        closestIndex = idx;
                    }
                }
            });
            
            index = closestIndex;
            
            // Si aún no se encuentra, usar el primer slot
            if (index === -1) {
                index = 0;
            }
        }
        
        return index;
    } catch (error) {
        console.error('Error obteniendo índice del slot:', error, { startTime });
        return -1;
    }
};

// Abrir pop-over para editar bloque existente
const openEditPopover = (block) => {
    if (!block || !block.hora_inicio || !block.hora_fin) {
        console.error('Bloque inválido para editar:', block);
        return;
    }
    
    try {
        popoverForm.value = {
            materia: block.materia || '',
            profesor: block.profesor || '',
            aula: block.aula || '',
            building_id: '', // Se buscará por el código del aula
            hora_inicio: block.hora_inicio.substring(0, 5),
            hora_fin: block.hora_fin.substring(0, 5),
            tipo: block.tipo || 'Teoría'
        };
        
        // Encontrar el edificio del aula
        if (block.aula) {
            const room = rooms.value.find(r => r.codigo === block.aula);
            if (room) {
                popoverForm.value.building_id = room.building_id;
                filteredRooms.value = rooms.value.filter(r => r.building_id == room.building_id);
            }
        }
        
        // Buscar el grupo y materia correspondientes para poder verificar asignación
        const group = availableGroups.value.find(g => g.grupo === block.grupo && g.carrera === block.carrera);
        const subject = availableSubjects.value.find(s => s.nombre === block.materia);
        
        popoverBlock.value = {
            id: block.id,
            day: block.dia_semana,
            career: block.carrera,
            grupo: block.grupo,
            isEdit: true,
            subject: subject || null
        };
        
        showPopover.value = true;
        
        // Resetear estado de asignación automática
        teacherAutoAssigned.value = false;
        
        // Cargar salones disponibles y verificar asignación después de abrir el popover
        setTimeout(() => {
            // Verificar que todos los valores necesarios estén presentes antes de cargar
            if (popoverBlock.value?.day && popoverForm.value.hora_inicio && popoverForm.value.hora_fin) {
                loadAvailableRooms();
            } else {
                console.warn('No se pueden cargar salones al editar: faltan datos', {
                    day: popoverBlock.value?.day,
                    hora_inicio: popoverForm.value.hora_inicio,
                    hora_fin: popoverForm.value.hora_fin
                });
            }
            // Verificar asignación de maestro si hay grupo y materia
            // Usar el grupo encontrado o selectedGrupo como fallback
            if (group && subject) {
                // Usar el ID del grupo encontrado para verificar la asignación
                checkTeacherAssignment(group.id, subject.id);
            } else if (selectedGrupo.value && subject) {
                // Si no se encontró el grupo pero hay selectedGrupo, usar ese
                checkTeacherAssignment(selectedGrupo.value, subject.id);
            }
        }, 200);
    } catch (error) {
        console.error('Error abriendo popover para editar:', error, block);
    }
};

// Eliminar horario
const deleteSchedule = async (scheduleId) => {
    if (!confirm('¿Estás seguro de eliminar este horario?')) return;
    
    try {
        await axios.delete(`/admin/schedules/${scheduleId}`);
        await loadSchedules();
    } catch (error) {
        console.error('Error eliminando horario:', error);
        alert('Error al eliminar el horario');
    }
};

// Computed para verificar si hay grupo seleccionado
const hasSelectedGroup = computed(() => {
    const value = selectedGrupo.value;
    return value !== null && value !== undefined && value !== '' && value !== 0;
});

// Computed para obtener el título del horario
const scheduleTitle = computed(() => {
    if (!selectedGrupo.value || !selectedCareer.value || !selectedGrado.value) {
        return 'Horario Escolar';
    }
    
    // Verificar que los arrays existan y tengan elementos
    if (!availableGroups.value || availableGroups.value.length === 0) {
        return 'Horario Escolar';
    }
    
    if (!careers.value || careers.value.length === 0) {
        return 'Horario Escolar';
    }
    
    const group = availableGroups.value.find(g => g.id == selectedGrupo.value);
    const career = careers.value.find(c => c.id == selectedCareer.value);
    
    if (group && career && group.grupo && career.nombre) {
        const careerCode = career.nombre.substring(0, 3).toUpperCase();
        return `${selectedGrado.value}${group.grupo}-${careerCode}`;
    }
    
    return 'Horario Escolar';
});

// Función helper para convertir tiempo a minutos (ya definida arriba)

// Función para encontrar el índice del slot donde empieza un horario
const findSlotIndexForTime = (timeStr) => {
    const timeMinutes = timeToMinutes(timeStr);
    return timeSlots.value.findIndex(slot => {
        const slotStart = timeToMinutes(slot.start);
        const slotEnd = timeToMinutes(slot.end);
        return timeMinutes >= slotStart && timeMinutes < slotEnd;
    });
};

// Función para calcular cuántos slots ocupa un bloque
const calculateRowSpan = (startTime, endTime) => {
    const startMinutes = timeToMinutes(startTime.substring(0, 5));
    const endMinutes = timeToMinutes(endTime.substring(0, 5));
    
    // Contar cuántos slots NO-break ocupa el bloque
    let count = 0;
    timeSlots.value.forEach((slot) => {
        // IMPORTANTE: Saltar breaks - no contarlos en el rowspan
        if (slot.type === 'break') {
            return;
        }
        
        const slotStart = timeToMinutes(slot.start);
        const slotEnd = timeToMinutes(slot.end);
        
        // Si el bloque se superpone con este slot (aunque sea parcialmente), contarlo
        if (startMinutes < slotEnd && endMinutes > slotStart) {
            count++;
        }
    });
    
    return Math.max(1, count);
};

// Función para obtener iniciales del profesor
const getTeacherInitials = (fullName) => {
    if (!fullName) return '';
    const parts = fullName.trim().split(' ');
    if (parts.length >= 2) {
        return (parts[0][0] || '') + (parts[1][0] || '') + (parts[2]?.[0] || '');
    }
    return fullName.substring(0, 3).toUpperCase();
};

// Función para obtener código de la materia desde availableSubjects
const getSubjectCodeFromName = (subjectName) => {
    if (!subjectName) return '';
    // Buscar la materia en availableSubjects por nombre
    const subject = availableSubjects.value.find(s => s.nombre === subjectName);
    if (subject && subject.codigo) {
        return subject.codigo;
    }
    // Si no se encuentra, intentar extraer código del nombre (ej: "M-106 Matemáticas")
    const codeMatch = subjectName.match(/^([A-Z0-9-]+)\s/);
    if (codeMatch) {
        return codeMatch[1];
    }
    // Fallback: primeras 3 letras en mayúsculas
    return subjectName.substring(0, 3).toUpperCase();
};

// Computed: Mapeo de celdas con información de rowspan y skipped
const scheduleGrid = computed(() => {
    const grid = [];
    
    timeSlots.value.forEach((slot, slotIndex) => {
        // Si es un break, crear una fila especial
        if (slot.type === 'break') {
            grid.push({
                slot: slot,
                slotIndex: slotIndex,
                isBreak: true,
                cells: {}
            });
            return;
        }
        
        const row = {
            slot: slot,
            slotIndex: slotIndex,
            isBreak: false,
            cells: {}
        };
        
        daysOfWeek.forEach(day => {
            // Buscar bloques que empiecen en este slot y día
            const blocksInSlot = schedules.value.filter(schedule => {
                if (!schedule || schedule.dia_semana !== day) return false;
                const scheduleStart = schedule.hora_inicio?.substring(0, 5);
                return scheduleStart === slot.start;
            });
            
            if (blocksInSlot.length > 0) {
                // Tomar el primer bloque (no debería haber múltiples en el mismo slot)
                const block = blocksInSlot[0];
                const rowSpan = calculateRowSpan(block.hora_inicio, block.hora_fin);
                
                // Debug: verificar cálculo de rowspan
                if (rowSpan > 1) {
                    console.log(`Materia: ${block.materia}, Inicio: ${block.hora_inicio}, Fin: ${block.hora_fin}, RowSpan: ${rowSpan}`);
                }
                
                row.cells[day] = {
                    hasClass: true,
                    block: block,
                    rowSpan: rowSpan,
                    isSkipped: false
                };
            } else {
                // Verificar si esta celda está ocupada por un bloque que empezó antes
                // PERO SOLO si este slot NO es un break
                if (slot.type === 'break') {
                    // Los breaks siempre están vacíos
                    row.cells[day] = {
                        hasClass: false,
                        block: null,
                        rowSpan: 1,
                        isSkipped: false
                    };
                    return;
                }
                // Verificar si esta celda está ocupada por un bloque que empezó antes
                let isSkipped = false;
                let coveringBlock = null;
                
                schedules.value.forEach(schedule => {
                    if (!schedule || schedule.dia_semana !== day) return;
                    
                    const scheduleStart = schedule.hora_inicio?.substring(0, 5);
                    const scheduleEnd = schedule.hora_fin?.substring(0, 5);
                    
                    if (!scheduleStart || !scheduleEnd) return;
                    
                    const scheduleStartMinutes = timeToMinutes(scheduleStart);
                    const scheduleEndMinutes = timeToMinutes(scheduleEnd);
                    const slotStartMinutes = timeToMinutes(slot.start);
                    const slotEndMinutes = timeToMinutes(slot.end);
                    
                    // IMPORTANTE: No marcar como skipped si este slot es un break
                    // Los breaks deben permanecer vacíos
                    if (slot.type === 'break') {
                        return;
                    }
                    
                    // Si el bloque empezó en un slot anterior y aún está activo en este slot
                    // Verificar que el bloque empieza ANTES de este slot pero continúa HASTA o DESPUÉS de este slot
                    // Esto significa que este slot está "ocupado" por el rowspan del bloque que empezó antes
                    if (scheduleStartMinutes < slotStartMinutes && scheduleEndMinutes > slotStartMinutes) {
                        isSkipped = true;
                        coveringBlock = schedule;
                    }
                });
                
                row.cells[day] = {
                    hasClass: false,
                    block: coveringBlock,
                    rowSpan: 1,
                    isSkipped: isSkipped
                };
            }
        });
        
        grid.push(row);
    });
    
    return grid;
});

// Computed para obtener colores de materias de forma optimizada
const getBlockColor = (materia) => {
    return getSubjectColor(materia);
};

// Función helper para obtener clases CSS del bloque (colores pastel fuertes)
const getBlockClasses = (block, darkMode, includeAbsolute = false) => {
    const color = getSubjectColor(block.materia);
    const hasConflict = conflicts.value.has_conflicts && 
        (conflicts.value.conflicts.profesor?.some(c => c.id === block.id) || 
         conflicts.value.conflicts.aula?.some(c => c.id === block.id));
    
    const positionClass = includeAbsolute ? 'absolute left-0 right-0' : '';
    
    if (hasConflict) {
        return `${positionClass} rounded-none shadow-none cursor-pointer transition-all hover:opacity-80 z-10 border-0 bg-red-300 animate-pulse`.trim();
    }
    
    // Usar siempre colores pastel claros (modo claro) para que el texto negro sea visible
    const baseClasses = `${positionClass} rounded-none shadow-none cursor-pointer transition-all hover:opacity-80 z-10 border-0`.trim();
    return `${baseClasses} ${color.bg} ${color.border}`;
};

// Cargar datos al montar
onMounted(() => {
    loadAreas();
    loadTeachers();
    loadBuildings();
    loadRooms();
});
</script>

<template>
    <Head title="Gestión de Horarios - Administrador UTM" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-[98vw] px-4 sm:px-6 lg:px-8">
                
                <!-- Filtros -->
                <div :class="['mb-6 rounded-xl border p-6', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                          <!-- Área -->
                          <div>
                              <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                  Área de Estudio *
                              </label>
                              <select
                                v-model.number="selectedArea"
                                :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                            >
                                <option :value="null">Seleccione un área</option>
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
                                v-model.number="selectedCareer"
                                :disabled="selectedArea === null || careers.length === 0"
                                :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900', (selectedArea === null || careers.length === 0) && 'opacity-50 cursor-not-allowed']"
                            >
                                <option :value="null">{{ careers.length === 0 ? 'No hay carreras disponibles' : 'Seleccione una carrera' }}</option>
                                <option v-for="career in careers" :key="career.id" :value="career.id">
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
                                v-model="selectedGrado"
                                :disabled="!selectedCareer"
                                :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900', !selectedCareer && 'opacity-50 cursor-not-allowed']"
                            >
                                <option value="">Seleccione un grado</option>
                                <option value="1">1er Grado</option>
                                <option value="2">2do Grado</option>
                                <option value="3">3er Grado</option>
                                <option value="4">4to Grado</option>
                                <option value="5">5to Grado</option>
                            </select>
                        </div>

                        <!-- Grupo -->
                        <div>
                            <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Grupo *
                            </label>
                              <select
                                v-model.number="selectedGrupo"
                                :disabled="!selectedGrado || availableGroups.length === 0"
                                :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900', (!selectedGrado || availableGroups.length === 0) && 'opacity-50 cursor-not-allowed']"
                            >
                                <option :value="null">{{ availableGroups.length === 0 ? 'No hay grupos disponibles' : 'Seleccione un grupo' }}</option>
                                <option v-for="group in availableGroups" :key="group.id" :value="group.id">
                                    Grupo {{ group.grupo }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Contenido Principal: Paleta + Calendario -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <!-- Paleta de Materias (Sidebar) -->
                    <div class="lg:col-span-3">
                        <div :class="['rounded-xl border shadow-lg p-6 sticky top-4', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                            <h2 :class="['text-xl font-bold mb-4', darkMode ? 'text-white' : 'text-gray-900']">
                                Materias Disponibles
                            </h2>
                            <div v-if="!hasSelectedGroup" :class="['text-sm p-4 rounded-lg', darkMode ? 'bg-gray-700 text-gray-400' : 'bg-gray-100 text-gray-600']">
                                Selecciona un grupo para ver las materias
                            </div>
                            <div v-else class="space-y-2 max-h-[600px] overflow-y-auto">
                                <div
                                    v-for="subject in availableSubjects"
                                    :key="subject.id"
                                    draggable="true"
                                    @dragstart="onDragStart($event, subject)"
                                    :class="['p-3 rounded-lg border cursor-move transition-all hover:shadow-md', darkMode ? 'bg-gray-700 border-gray-600 hover:border-blue-500' : 'bg-gray-50 border-gray-200 hover:border-blue-500']"
                                >
                                    <p :class="['font-semibold text-sm', darkMode ? 'text-white' : 'text-gray-900']">
                                        {{ subject.nombre }}
                                    </p>
                                    <p :class="['text-xs mt-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                        {{ subject.codigo }}
                                    </p>
                                </div>
                                <div v-if="availableSubjects.length === 0" :class="['text-sm p-4 rounded-lg text-center', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                    No hay materias disponibles
                                </div>
                            </div>
                        </div>
                    </div>

                        <!-- Calendario Visual -->
                        <div class="lg:col-span-9">
                            <div v-if="!hasSelectedGroup" :class="['rounded-xl border p-12', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                                <div class="text-center mb-8">
                                    <svg class="w-24 h-24 mx-auto mb-4 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <h3 :class="['text-2xl font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                        ¿Cómo empezar a crear horarios?
                                    </h3>
                                    <p :class="['text-lg mb-6', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                        Sigue estos pasos para comenzar a crear horarios
                                    </p>
                                </div>
                                
                                <div class="max-w-2xl mx-auto space-y-4">
                                    <div :class="['p-4 rounded-lg border', darkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-200']">
                                        <div class="flex items-start">
                                            <span :class="['flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm mr-4', darkMode ? 'bg-purple-600 text-white' : 'bg-purple-500 text-white']">
                                                1
                                            </span>
                                            <div class="flex-1">
                                                <p :class="['font-semibold mb-1', darkMode ? 'text-white' : 'text-gray-900']">
                                                    Selecciona un Área de Estudio
                                                </p>
                                                <p :class="['text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                                    Elige el área de estudio en los filtros superiores
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div :class="['p-4 rounded-lg border', darkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-200']">
                                        <div class="flex items-start">
                                            <span :class="['flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm mr-4', darkMode ? 'bg-purple-600 text-white' : 'bg-purple-500 text-white']">
                                                2
                                            </span>
                                            <div class="flex-1">
                                                <p :class="['font-semibold mb-1', darkMode ? 'text-white' : 'text-gray-900']">
                                                    Selecciona una Carrera
                                                </p>
                                                <p :class="['text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                                    Elige la carrera del área seleccionada
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div :class="['p-4 rounded-lg border', darkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-200']">
                                        <div class="flex items-start">
                                            <span :class="['flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm mr-4', darkMode ? 'bg-purple-600 text-white' : 'bg-purple-500 text-white']">
                                                3
                                            </span>
                                            <div class="flex-1">
                                                <p :class="['font-semibold mb-1', darkMode ? 'text-white' : 'text-gray-900']">
                                                    Selecciona el Grado
                                                </p>
                                                <p :class="['text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                                    Elige el grado (1-5) para el horario
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div :class="['p-4 rounded-lg border', darkMode ? 'bg-purple-500/20 border-purple-500' : 'bg-purple-50 border-purple-200']">
                                        <div class="flex items-start">
                                            <span :class="['flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm mr-4', darkMode ? 'bg-purple-600 text-white' : 'bg-purple-500 text-white']">
                                                4
                                            </span>
                                            <div class="flex-1">
                                                <p :class="['font-semibold mb-1', darkMode ? 'text-purple-300' : 'text-purple-900']">
                                                    Selecciona el Grupo
                                                </p>
                                                <p :class="['text-sm', darkMode ? 'text-purple-400' : 'text-purple-700']">
                                                    Elige el grupo (A, B, C, D, E) y el calendario se mostrará
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div :class="['p-4 rounded-lg border', darkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-200']">
                                        <div class="flex items-start">
                                            <span :class="['flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm mr-4', darkMode ? 'bg-purple-600 text-white' : 'bg-purple-500 text-white']">
                                                5
                                            </span>
                                            <div class="flex-1">
                                                <p :class="['font-semibold mb-1', darkMode ? 'text-white' : 'text-gray-900']">
                                                    Arrastra materias al calendario
                                                </p>
                                                <p :class="['text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                                    Una vez seleccionado el grupo, arrastra las materias del panel lateral al calendario para crear horarios
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        
                        <div v-else :class="['rounded-xl border-2 border-gray-800 shadow-lg overflow-hidden bg-white']">
                            <!-- Cabecera del Horario -->
                            <div class="bg-white border-b-2 border-gray-800 p-4">
                                <h2 class="text-2xl font-bold text-center text-black mb-2">
                                    {{ scheduleTitle }}
                                </h2>
                                <p class="text-sm text-center text-gray-700 font-semibold">Universidad Tecnológica Metropolitana</p>
                                <p class="text-xs text-center text-gray-600">Calle 115 (Circuito Colonias Sur) No. 404, Mérida, Yucatán</p>
                            </div>

                            <!-- Tabla de horarios -->
                            <div class="max-h-[800px] overflow-y-auto bg-white">
                                <table class="min-w-full table-fixed border-collapse border-2 border-gray-800">
                                    <!-- Header con días -->
                                    <thead>
                                        <tr class="border-2 border-gray-800">
                                            <th class="border-2 border-gray-800 bg-blue-200 p-3 text-sm font-bold text-center text-black w-28">
                                                Hora
                                            </th>
                                            <th 
                                                v-for="(day, dayIndex) in daysOfWeek" 
                                                :key="day"
                                                class="border-2 border-gray-800 bg-white p-3 text-sm font-bold text-center text-black w-1/5"
                                            >
                                                {{ day === 'Lunes' ? 'Lu' : day === 'Martes' ? 'Ma' : day === 'Miércoles' ? 'Mi' : day === 'Jueves' ? 'Ju' : 'Vi' }}
                                            </th>
                                        </tr>
                                    </thead>
                                    
                                    <tbody>
                                        <tr
                                            v-for="(row, rowIndex) in scheduleGrid"
                                            :key="`row-${rowIndex}`"
                                            class="border-2 border-gray-800"
                                        >
                                            <!-- CASO A: Fila de Receso -->
                                            <template v-if="row.isBreak">
                                                <td 
                                                    colspan="6" 
                                                    class="border-2 border-gray-800 bg-white p-4 text-center text-base font-bold text-black"
                                                >
                                                    {{ row.slot.label || 'DESCANSO' }}
                                                </td>
                                            </template>

                                            <!-- CASO B: Fila de Clase Normal -->
                                            <template v-else>
                                                <!-- Columna 1: Hora -->
                                                <td 
                                                    class="border-2 border-gray-800 bg-blue-200 p-2 text-xs font-semibold text-center text-black align-top w-28"
                                                >
                                                    {{ row.slot.start }} - {{ row.slot.end }}
                                                </td>

                                                <!-- Columnas 2-6: Días -->
                                                <template v-for="day in daysOfWeek" :key="day">
                                                    <!-- NO RENDERIZAR NADA si la celda está skipped (ocupada por rowspan) -->
                                                    <template v-if="row.cells[day]?.isSkipped">
                                                        <!-- Celda vacía - no renderizar nada -->
                                                    </template>
                                                    <!-- Renderizar celda normal o con clase -->
                                                    <td
                                                        v-else
                                                        :rowspan="row.cells[day]?.hasClass ? row.cells[day].rowSpan : 1"
                                                        @dragover="onDragOver"
                                                        @drop="onDrop($event, day, row.slot.start)"
                                                        :class="[
                                                            'border-2 border-gray-800 align-top w-1/5',
                                                            row.cells[day]?.hasClass ? 'p-0 relative hover:opacity-90 cursor-pointer' : 'p-2 hover:bg-gray-50 min-h-[80px]'
                                                        ]"
                                                    >
                                                        <!-- Bloque de clase -->
                                                        <div
                                                            v-if="row.cells[day]?.hasClass && row.cells[day].block"
                                                            :class="getBlockClasses(row.cells[day].block, false, false)"
                                                            class="absolute inset-0 w-full flex flex-col justify-between"
                                                            @click="openEditPopover(row.cells[day].block)"
                                                        >
                                                            <!-- Contenido: Nombre de materia arriba, Maestro en medio, Aula y Código abajo -->
                                                            <div class="h-full w-full flex flex-col justify-between p-2">
                                                                <!-- Parte Superior: Nombre completo de la Materia (centrado, negrita, texto negro) -->
                                                                <p class="font-bold text-center text-black text-xs leading-tight break-words whitespace-normal flex-shrink-0">
                                                                    {{ row.cells[day].block.materia || 'Sin materia' }}
                                                                </p>
                                                                
                                                                <!-- Parte Media: Nombre del Maestro (centrado, texto pequeño) -->
                                                                <p 
                                                                    v-if="row.cells[day].block.profesor"
                                                                    class="text-center text-black text-[10px] leading-tight font-medium flex-shrink-0"
                                                                >
                                                                    {{ row.cells[day].block.profesor }}
                                                                </p>
                                                                
                                                                <!-- Parte Inferior: Aula (izq) y Código de Materia (der) -->
                                                                <div class="flex justify-between items-end mt-auto pt-1 flex-shrink-0">
                                                                    <!-- Aula a la izquierda -->
                                                                    <span 
                                                                        v-if="row.cells[day].block.aula"
                                                                        class="text-xs text-black leading-tight font-medium"
                                                                    >
                                                                        {{ row.cells[day].block.aula }}
                                                                    </span>
                                                                    <span v-else class="text-xs text-transparent leading-tight">.</span>
                                                                    
                                                                    <!-- Código de la materia a la derecha -->
                                                                    <span 
                                                                        v-if="row.cells[day].block.materia"
                                                                        class="text-xs text-black font-semibold leading-tight"
                                                                    >
                                                                        {{ getSubjectCodeFromName(row.cells[day].block.materia) }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Botón de eliminar -->
                                                            <button
                                                                @click.stop="deleteSchedule(row.cells[day].block.id)"
                                                                class="absolute top-1 right-1 w-5 h-5 rounded-full bg-red-500 hover:bg-red-600 text-white text-xs font-bold flex items-center justify-center shadow-md transition-all hover:scale-110 z-20"
                                                                title="Eliminar horario"
                                                            >
                                                                ×
                                                            </button>
                                                        </div>
                                                    </td>
                                                </template>
                                            </template>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pop-over para configurar bloque -->
        <div
            v-if="showPopover"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
            @click.self="showPopover = false"
        >
            <div :class="['rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800' : 'bg-white']">
                <div class="p-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                    <div class="flex items-center justify-between">
                        <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                            Configurar Horario
                        </h2>
                        <button
                            @click="showPopover = false"
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
                        <!-- Materia (readonly) -->
                        <div>
                            <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Materia
                            </label>
                            <input
                                v-model="popoverForm.materia"
                                type="text"
                                readonly
                                :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-gray-400' : 'bg-gray-100 border-gray-300 text-gray-600']"
                            >
                        </div>

                        <!-- Hora Inicio y Fin -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                    Hora Inicio *
                                </label>
                                <input
                                    v-model="popoverForm.hora_inicio"
                                    type="time"
                                    @input="popoverForm.hora_inicio = normalizeTime($event.target.value)"
                                    :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                >
                            </div>
                            <div>
                                <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                    Hora Fin *
                                </label>
                                <input
                                    v-model="popoverForm.hora_fin"
                                    type="time"
                                    @input="(e) => { 
                                        const inputValue = e.target.value;
                                        console.log('Input hora_fin cambiado:', inputValue);
                                        
                                        // Si el input está vacío, no hacer nada
                                        if (!inputValue) return;
                                        
                                        // Normalizar el valor
                                        let normalized = normalizeTime(inputValue);
                                        
                                        // CRÍTICO: Detectar si el navegador convirtió 12:XX a 00:XX
                                        // Si el usuario escribió algo que parece 12:XX pero el input devuelve 00:XX,
                                        // necesitamos corregirlo
                                        const inputMinutes = timeToMinutes(inputValue);
                                        const normalizedMinutes = timeToMinutes(normalized);
                                        
                                        // Si el valor normalizado es 00:XX pero los minutos sugieren que debería ser 12:XX
                                        // (por ejemplo, si el usuario acaba de cambiar de 11:XX a 12:XX)
                                        if (normalized && normalized.startsWith('00:') && inputMinutes >= 720) {
                                            // Probablemente el navegador convirtió 12:XX a 00:XX
                                            // Intentar reconstruir la hora correcta
                                            const [hours, minutes] = normalized.split(':').map(Number);
                                            if (hours === 0 && minutes >= 0 && minutes <= 59) {
                                                // Si tenemos una hora de inicio, podemos inferir si debería ser 12:XX
                                                const startMinutes = timeToMinutes(popoverForm.value.hora_inicio);
                                                const diff = inputMinutes - startMinutes;
                                                
                                                // Si la diferencia sugiere que debería ser alrededor de 12:XX (720-840 minutos)
                                                if (diff >= 50 && diff <= 200 && startMinutes >= 660) {
                                                    // Probablemente debería ser 12:XX
                                                    normalized = `12:${String(minutes).padStart(2, '0')}`;
                                                    console.log('Corrigiendo conversión incorrecta de 00:XX a 12:XX:', {
                                                        original: inputValue,
                                                        corrected: normalized
                                                    });
                                                }
                                            }
                                        }
                                        
                                        if (normalized) {
                                            popoverForm.hora_fin = normalized;
                                            console.log('Hora fin actualizada:', normalized, 'Minutos:', timeToMinutes(normalized));
                                        }
                                    }"
                                    :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                >
                            </div>
                        </div>

                        <!-- Maestro -->
                        <div>
                            <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                Maestro *
                                <span v-if="teacherAutoAssigned" :class="['text-xs font-normal ml-2', darkMode ? 'text-green-400' : 'text-green-600']">
                                    (Asignado automáticamente)
                                </span>
                                <span v-if="checkingAssignment" :class="['text-xs font-normal ml-2', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                    Verificando...
                                </span>
                            </label>
                            <div class="relative">
                                <select
                                    v-model="popoverForm.profesor"
                                    :disabled="checkingAssignment"
                                    :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900', checkingAssignment && 'opacity-50 cursor-not-allowed', teacherAutoAssigned && (darkMode ? 'border-green-500' : 'border-green-500')]"
                                >
                                    <option value="">
                                        {{ checkingAssignment ? 'Verificando asignación...' : 'Seleccione un maestro' }}
                                    </option>
                                    <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.name">
                                        {{ teacher.name }}
                                    </option>
                                </select>
                                <div v-if="checkingAssignment" class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                    <div class="animate-spin rounded-full h-4 w-4 border-2 border-gray-300 border-t-gray-600" :class="darkMode ? 'border-gray-600 border-t-gray-400' : ''"></div>
                                </div>
                            </div>
                            <p v-if="teacherAutoAssigned" :class="['text-xs mt-1', darkMode ? 'text-green-400' : 'text-green-600']">
                                ✓ Este maestro ya está asignado a esta materia en este grupo (en otro día). Puedes cambiarlo si es necesario, pero se actualizarán todos los horarios de esta materia para mantener la consistencia.
                            </p>
                        </div>

                        <!-- Edificio y Aula -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                    Edificio
                                    <span class="text-xs font-normal text-gray-500">(Opcional - dejar vacío para ver todos)</span>
                                </label>
                                <select
                                    v-model="popoverForm.building_id"
                                    :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                >
                                    <option value="">Todos los edificios</option>
                                    <option v-for="building in buildings" :key="building.id" :value="building.id">
                                        {{ building.nombre }}
                                    </option>
                                </select>
                                <p v-if="!popoverForm.building_id && availableRooms.length > 0" :class="['text-xs mt-1', darkMode ? 'text-blue-400' : 'text-blue-600']">
                                    ℹ️ Mostrando salones de todos los edificios
                                </p>
                            </div>
                            <div>
                                <label :class="['block text-sm font-bold mb-2', darkMode ? 'text-white' : 'text-gray-900']">
                                    Aula/Laboratorio *
                                </label>
                                <div class="relative">
                                    <select
                                        v-model="popoverForm.aula"
                                        :disabled="loadingRooms || !popoverBlock?.day || !popoverForm.hora_inicio || !popoverForm.hora_fin"
                                        :class="['w-full px-4 py-3 rounded-lg border transition-colors', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900', (loadingRooms || !popoverBlock?.day || !popoverForm.hora_inicio || !popoverForm.hora_fin) && 'opacity-50 cursor-not-allowed']"
                                    >
                                        <option value="">
                                            {{ loadingRooms ? 'Buscando salones disponibles...' : (availableRooms.length === 0 ? 'No hay salones disponibles' : 'Seleccione un aula') }}
                                        </option>
                                        <option v-for="room in availableRooms" :key="room.id" :value="room.codigo">
                                            {{ room.nombre }} ({{ room.codigo }}) - {{ room.building?.nombre || 'Sin edificio' }}
                                        </option>
                                    </select>
                                    <div v-if="loadingRooms" class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                        <div class="animate-spin rounded-full h-5 w-5 border-2 border-gray-300 border-t-gray-600" :class="darkMode ? 'border-gray-600 border-t-gray-400' : ''"></div>
                                    </div>
                                </div>
                                <p v-if="!loadingRooms && availableRooms.length > 0" :class="['text-xs mt-1', darkMode ? 'text-green-400' : 'text-green-600']">
                                    ✓ {{ availableRooms.length }} salón{{ availableRooms.length !== 1 ? 'es' : '' }} disponible{{ availableRooms.length !== 1 ? 's' : '' }}
                                    <span v-if="popoverForm.building_id" class="block mt-1 opacity-75">
                                        (Filtrado por edificio seleccionado)
                                    </span>
                                </p>
                                <p v-else-if="!loadingRooms && availableRooms.length === 0 && popoverBlock?.day && popoverForm.hora_inicio && popoverForm.hora_fin" :class="['text-xs mt-1', darkMode ? 'text-yellow-400' : 'text-yellow-600']">
                                    ⚠️ No hay salones disponibles en este horario
                                    <span v-if="popoverForm.building_id" class="block mt-1">
                                        Intenta seleccionar otro edificio o dejar el campo de edificio vacío para ver todos los salones disponibles.
                                    </span>
                                    <span v-else class="block mt-1">
                                        Todos los salones están ocupados en este horario ({{ popoverForm.hora_inicio }} - {{ popoverForm.hora_fin }}) para el día {{ popoverBlock.day }}.
                                    </span>
                                </p>
                            </div>
                        </div>

                        <!-- Mensaje de conflictos -->
                        <div v-if="conflicts.has_conflicts" :class="['p-4 rounded-lg border-2', darkMode ? 'bg-red-900/30 border-red-600' : 'bg-red-50 border-red-500']">
                            <p class="font-bold text-red-600 dark:text-red-400 mb-2">⚠️ Conflictos Detectados:</p>
                            <ul class="list-disc list-inside text-sm space-y-1" :class="darkMode ? 'text-red-300' : 'text-red-700'">
                                <li v-if="conflicts.conflicts.profesor">
                                    El maestro tiene clases en el mismo horario
                                </li>
                                <li v-if="conflicts.conflicts.aula">
                                    El aula está ocupada en el mismo horario
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end space-x-4">
                        <button
                            @click="showPopover = false"
                            :class="['px-6 py-3 rounded-lg font-semibold transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                        >
                            Cancelar
                        </button>
                        <button
                            @click="saveBlock"
                            :disabled="loading || !popoverForm.profesor || !popoverForm.aula"
                            :class="['px-6 py-3 rounded-lg font-semibold transition-colors', loading || !popoverForm.profesor || !popoverForm.aula ? 'bg-gray-400 cursor-not-allowed text-white' : darkMode ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                        >
                            {{ loading ? 'Guardando...' : 'Guardar Horario' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
