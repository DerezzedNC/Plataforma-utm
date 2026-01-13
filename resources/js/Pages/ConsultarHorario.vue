<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Estados
const loading = ref(true);
const scheduleData = ref(null);
const hasSchedule = ref(false);
const carrera = ref('');
const grupo = ref('');
const grado = ref('');

// Días de la semana (mismo formato que admin)
const daysOfWeek = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];

// Horarios del estudiante
const schedules = ref([]);

// Generar slots de tiempo (igual que admin)
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

const timeSlots = ref(generateTimeSlots());

// Función helper para convertir tiempo a minutos
const timeToMinutes = (timeStr) => {
    if (!timeStr) return 0;
    const timeOnly = timeStr.substring(0, 5);
    const [hours, minutes] = timeOnly.split(':').map(Number);
    return (hours || 0) * 60 + (minutes || 0);
};

// Función para calcular cuántos slots ocupa un bloque
const calculateRowSpan = (startTime, endTime) => {
    if (!startTime || !endTime) return 1;
    
    try {
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
    } catch (error) {
        console.error('Error calculando rowspan:', error);
        return 1;
    }
};

// Cache de colores asignados a materias para mantener consistencia (igual que admin)
const subjectColorCache = new Map();

// Función para generar un color único para cada materia (igual que admin)
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
    
    // Lista de colores pastel fuertes (igual que admin)
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

// Función helper para obtener clases CSS del bloque (igual que admin)
const getBlockClasses = (block) => {
    const color = getSubjectColor(block.materia);
    // Usar siempre colores pastel claros (modo claro) para que el texto negro sea visible
    return `rounded-none shadow-none transition-all hover:opacity-80 z-10 border-0 ${color.bg} ${color.border}`;
};

// Función para obtener código de la materia
const getSubjectCodeFromName = (subjectName) => {
    if (!subjectName) return '';
    // Intentar extraer código del nombre (ej: "M-106 Matemáticas")
    const codeMatch = subjectName.match(/^([A-Z0-9-]+)\s/);
    if (codeMatch) {
        return codeMatch[1];
    }
    // Fallback: primeras 3 letras en mayúsculas
    return subjectName.substring(0, 3).toUpperCase();
};

// Computed: Mapeo de celdas con información de rowspan y skipped (igual que admin)
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
                
                row.cells[day] = {
                    hasClass: true,
                    block: block,
                    rowSpan: rowSpan,
                    isSkipped: false
                };
            } else {
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
                    
                    // Si el bloque empezó en un slot anterior y aún está activo en este slot
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

// Computed para obtener el título del horario
const scheduleTitle = computed(() => {
    if (!grupo.value || !carrera.value || !grado.value) {
        return 'Horario Escolar';
    }
    
    const careerCode = carrera.value.substring(0, 3).toUpperCase();
    return `${grado.value}${grupo.value}-${careerCode}`;
});

// Cargar horario del estudiante
const loadSchedule = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/student/schedule');
        
        if (response.data.has_schedule && response.data.horario) {
            hasSchedule.value = true;
            carrera.value = response.data.carrera || '';
            grupo.value = response.data.grupo || '';
            grado.value = response.data.grado || '';
            
            // Convertir el formato de horario organizado por día a array de schedules
            schedules.value = [];
            Object.keys(response.data.horario).forEach(dia => {
                const horariosDelDia = response.data.horario[dia];
                horariosDelDia.forEach(horario => {
                    schedules.value.push({
                        id: horario.id,
                        carrera: response.data.carrera,
                        grupo: response.data.grupo,
                        materia: horario.materia,
                        profesor: horario.profesor,
                        dia_semana: dia,
                        hora_inicio: horario.hora_inicio,
                        hora_fin: horario.hora_fin,
                        aula: horario.aula,
                        tipo: horario.tipo || 'Teoría'
                    });
                });
            });
            
            scheduleData.value = response.data;
        } else {
            hasSchedule.value = false;
            scheduleData.value = response.data;
        }
    } catch (error) {
        console.error('Error cargando horario:', error);
        hasSchedule.value = false;
        if (error.response?.status === 404) {
            scheduleData.value = {
                message: 'El estudiante no tiene carrera o grupo asignado'
            };
        }
    } finally {
        loading.value = false;
    }
};

// Cargar horario al montar
onMounted(() => {
    loadSchedule();
});
</script>

<template>
    <Head title="Consultar Horario - UTM" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-[98vw] px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="text-center mb-8">
                    <h1 :class="['font-heading text-4xl font-bold mb-4', darkMode ? 'text-white' : 'text-gray-900']">
                        Consultar Horario
                    </h1>
                    <p :class="['font-body text-xl', darkMode ? 'text-gray-400' : 'text-gray-600']">
                        Tu horario de clases del semestre actual
                    </p>
                </div>

                <!-- Loading State -->
                <div v-if="loading" :class="['max-w-4xl mx-auto rounded-2xl shadow-xl border p-12 text-center', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2" :class="darkMode ? 'border-green-400' : 'border-green-600'"></div>
                    <p :class="['font-body mt-4', darkMode ? 'text-gray-400' : 'text-gray-600']">Cargando horario...</p>
                </div>

                <!-- Mensaje cuando no hay horario -->
                <div v-else-if="!hasSchedule" :class="['max-w-4xl mx-auto rounded-2xl shadow-xl border p-12 text-center', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                    <svg class="w-24 h-24 mx-auto mb-6 opacity-50" :class="darkMode ? 'text-gray-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h2 :class="['font-heading text-2xl font-bold mb-4', darkMode ? 'text-white' : 'text-gray-900']">
                        Horario No Disponible
                    </h2>
                    <p :class="['font-body text-lg', darkMode ? 'text-gray-400' : 'text-gray-600']">
                        La institución sigue trabajando en los horarios académicos, regresa más tarde
                    </p>
                    <div class="mt-8">
                        <Link :href="route('dashboard')" :class="['font-body inline-flex items-center px-6 py-3 rounded-lg font-medium transition-colors gap-2', darkMode ? 'bg-gray-600 hover:bg-gray-700 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Regresar al Dashboard
                        </Link>
                    </div>
                </div>

                <!-- Horario mostrado (mismo formato que admin) -->
                <div v-else-if="hasSchedule" class="w-full">
                    <div class="rounded-xl border-2 border-gray-800 shadow-lg overflow-hidden bg-white">
                        <!-- Cabecera del Horario (igual que admin) -->
                        <div class="bg-white border-b-2 border-gray-800 p-4">
                            <h2 class="text-2xl font-bold text-center text-black mb-2">
                                {{ scheduleTitle }}
                            </h2>
                            <p class="text-sm text-center text-gray-700 font-semibold">Universidad Tecnológica Metropolitana</p>
                            <p class="text-xs text-center text-gray-600">Calle 115 (Circuito Colonias Sur) No. 404, Mérida, Yucatán</p>
                        </div>

                        <!-- Tabla de horarios (igual que admin) -->
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
                                                    :class="[
                                                        'border-2 border-gray-800 align-top w-1/5',
                                                        row.cells[day]?.hasClass ? 'p-0 relative' : 'p-2 min-h-[80px]'
                                                    ]"
                                                >
                                                    <!-- Bloque de clase (sin funcionalidades de edición) -->
                                                    <div
                                                        v-if="row.cells[day]?.hasClass && row.cells[day].block"
                                                        :class="getBlockClasses(row.cells[day].block)"
                                                        class="absolute inset-0 w-full flex flex-col justify-between"
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

                <!-- Botón para regresar -->
                <div class="mt-8 text-center">
                    <Link :href="route('dashboard')" :class="['inline-flex items-center px-6 py-3 rounded-lg font-medium transition-colors', darkMode ? 'bg-gray-700 hover:bg-gray-600 text-gray-300' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']">
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
