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
const unidad = ref(1);
const calificaciones = ref([]);
const errors = ref({});
const showGradesTable = ref(false);

// Estados para actividades
const showActivitiesModal = ref(false);
const loadingActivities = ref(false);
const actividades = ref([]);

// Estados para confirmación
const loadingConfirmacion = ref(false);
const actividadForm = ref({
    id: null,
    titulo: '',
    descripcion: '',
    valor_maximo: 0,
    categoria: 'TAREA',
    fecha_limite: null,
    activa: true
});
const editingActividad = ref(false);

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
        return;
    }

    try {
        loadingAcademicLoad.value = true;
        // Limpiar calificaciones anteriores al cambiar de grupo/materia
        calificaciones.value = [];
        
        const response = await axios.get('/teacher/grades/academic-loads', {
            params: {
                carrera: selectedGroup.value.carrera,
                grupo: selectedGroup.value.grupo,
                materia: selectedSubject.value.materia,
            }
        });
        academicLoad.value = response.data;
        showGradesTable.value = true;
        loadGrades();
    } catch (error) {
        console.error('Error cargando carga académica:', error);
        alert(error.response?.data?.message || 'Error al cargar la carga académica');
        academicLoad.value = null;
        showGradesTable.value = false;
        calificaciones.value = []; // Limpiar calificaciones en caso de error
    } finally {
        loadingAcademicLoad.value = false;
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
const loadGrades = async (preservarValoresLocales = false) => {
    if (!academicLoad.value) return;

    try {
        loading.value = true;
        // Cargar actividades primero para que aparezcan las columnas
        await loadActivities();
        
        // Preservar valores locales de examen y conducta si se solicita
        const valoresLocales = {};
        if (preservarValoresLocales) {
            calificaciones.value.forEach(cal => {
                if (cal.calificacion) {
                    valoresLocales[cal.student_id] = {
                        score_examen: cal.calificacion.score_examen,
                        score_conducta: cal.calificacion.score_conducta
                    };
                }
            });
        }
        
        const response = await axios.get('/teacher/grades', {
            params: {
                academic_load_id: academicLoad.value.id,
                unidad: unidad.value
            }
        });
        
        // Si hay valores locales preservados, hacer merge inteligente
        if (preservarValoresLocales && Object.keys(valoresLocales).length > 0) {
            response.data.forEach(calNueva => {
                const valoresLocalesEstudiante = valoresLocales[calNueva.student_id];
                if (valoresLocalesEstudiante && calNueva.calificacion) {
                    // Preservar score_examen: Si hay un valor local válido (no null/undefined/'')
                    // Y el servidor devolvió null/undefined/0, preservar el valor local
                    // (El 0 del servidor podría ser porque no está guardado aún)
                    const examenLocal = valoresLocalesEstudiante.score_examen;
                    let examenServidor = calNueva.calificacion.score_examen;
                    // Convertir a número para comparación correcta
                    if (typeof examenServidor === 'string' && examenServidor !== '') {
                        examenServidor = parseFloat(examenServidor);
                    }
                    
                    const examenLocalValido = examenLocal !== null && examenLocal !== undefined && examenLocal !== '' && parseFloat(examenLocal) > 0;
                    const examenServidorInvalido = examenServidor === null || examenServidor === undefined || examenServidor === 0 || examenServidor === '0' || examenServidor === '';
                    
                    if (examenLocalValido && examenServidorInvalido) {
                        // Preservar el valor local si es válido y el servidor no tiene un valor guardado
                        calNueva.calificacion.score_examen = parseFloat(examenLocal);
                    }
                    
                    // Preservar score_conducta: Similar a examen pero conducta puede ser 0 válidamente
                    // Solo preservar si el local NO es 0 y el servidor devolvió 0/null/undefined
                    const conductaLocal = valoresLocalesEstudiante.score_conducta;
                    let conductaServidor = calNueva.calificacion.score_conducta;
                    // Convertir a número para comparación correcta
                    if (typeof conductaServidor === 'string' && conductaServidor !== '') {
                        conductaServidor = parseFloat(conductaServidor);
                    }
                    
                    const conductaLocalValido = conductaLocal !== null && conductaLocal !== undefined && conductaLocal !== '' && parseFloat(conductaLocal) > 0;
                    const conductaServidorInvalido = conductaServidor === null || conductaServidor === undefined || conductaServidor === 0 || conductaServidor === '0' || conductaServidor === '';
                    
                    if (conductaLocalValido && conductaServidorInvalido) {
                        // Preservar el valor local si es > 0 y el servidor devolvió 0/null/undefined
                        calNueva.calificacion.score_conducta = parseFloat(conductaLocal);
                    }
                }
            });
        }
        
        // Asegurar que todas las calificaciones tengan un objeto calificacion válido
        calificaciones.value = response.data.map(item => {
            if (!item.calificacion) {
                item.calificacion = {
                    id: null,
                    score_tareas: 0,
                    score_examen: null,
                    score_conducta: 0,
                    promedio_unidad: null,
                    derecho_examen: false,
                };
            }
            return item;
        });
    } catch (error) {
        console.error('Error cargando calificaciones:', error);
        alert('Error al cargar las calificaciones');
    } finally {
        loading.value = false;
    }
};

// Cargar actividades (solo TAREAS)
const loadActivities = async () => {
    if (!academicLoad.value) return;

    try {
        loadingActivities.value = true;
        const response = await axios.get('/teacher/activities', {
            params: {
                academic_load_id: academicLoad.value.id,
                unidad: unidad.value
            }
        });
        // Filtrar solo actividades de tipo TAREA
        actividades.value = response.data.filter(a => a.categoria === 'TAREA');
    } catch (error) {
        console.error('Error cargando actividades:', error);
        alert('Error al cargar las actividades');
    } finally {
        loadingActivities.value = false;
    }
};

// Abrir modal de actividades
const openActivitiesModal = async () => {
    showActivitiesModal.value = true;
    await loadActivities();
};

// Cerrar modal de actividades
const closeActivitiesModal = () => {
    showActivitiesModal.value = false;
    actividadForm.value = {
        id: null,
        titulo: '',
        descripcion: '',
        valor_maximo: 0,
        categoria: 'TAREA',
        fecha_limite: null,
        activa: true
    };
    editingActividad.value = false;
};

// Crear/Editar actividad
const saveActividad = async () => {
    if (!actividadForm.value.titulo || !actividadForm.value.valor_maximo) {
        alert('Por favor completa todos los campos requeridos');
        return;
    }

    try {
        const data = {
            academic_load_id: academicLoad.value.id,
            titulo: actividadForm.value.titulo,
            descripcion: actividadForm.value.descripcion,
            unidad: unidad.value,
            valor_maximo: parseFloat(actividadForm.value.valor_maximo),
            categoria: actividadForm.value.categoria,
            fecha_limite: actividadForm.value.fecha_limite || null,
        };

        if (editingActividad.value) {
            await axios.put(`/teacher/activities/${actividadForm.value.id}`, data);
        } else {
            await axios.post('/teacher/activities', data);
        }

        await loadActivities();
        await loadGrades(); // Recargar calificaciones para actualizar columnas
        closeActivitiesModal();
        alert('Actividad guardada exitosamente');
    } catch (error) {
        console.error('Error guardando actividad:', error);
        alert(error.response?.data?.message || 'Error al guardar la actividad');
    }
};

// Editar actividad
const editActividad = (actividad) => {
    actividadForm.value = {
        id: actividad.id,
        titulo: actividad.titulo,
        descripcion: actividad.descripcion || '',
        valor_maximo: actividad.valor_maximo,
        categoria: actividad.categoria,
        fecha_limite: actividad.fecha_limite || null,
        activa: actividad.activa
    };
    editingActividad.value = true;
};

// Eliminar actividad
const deleteActividad = async (id) => {
    if (!confirm('¿Estás seguro de eliminar esta actividad? Esto también eliminará todas las calificaciones asociadas.')) {
        return;
    }

    try {
        await axios.delete(`/teacher/activities/${id}`);
        await loadActivities();
        await loadGrades(); // Recargar calificaciones
        alert('Actividad eliminada exitosamente');
    } catch (error) {
        console.error('Error eliminando actividad:', error);
        alert('Error al eliminar la actividad');
    }
};

// Guardar calificación de actividad
const saveActivityGrade = async (actividadId, studentId, calificacion) => {
    // Si la calificación está vacía, no hacer nada
    if (calificacion === '' || calificacion === null || calificacion === undefined) {
        return;
    }

    const actividad = actividades.value.find(a => a.id === actividadId);
    if (!actividad) {
        console.error('Actividad no encontrada:', actividadId);
        return;
    }

    // Convertir a números para comparación correcta
    const calificacionNum = parseFloat(calificacion);
    const valorMaximo = parseFloat(actividad.valor_maximo);

    // Validar que sea un número válido
    if (isNaN(calificacionNum)) {
        return; // No mostrar alerta, solo salir silenciosamente
    }

    // Validar que no sea negativa
    if (calificacionNum < 0) {
        alert('La calificación no puede ser negativa');
        return;
    }

    // Validar que no supere el valor máximo (con un pequeño margen para decimales)
    if (calificacionNum > valorMaximo + 0.01) {
        alert(`La calificación (${calificacionNum}) no puede superar el valor máximo de ${valorMaximo} puntos`);
        return;
    }

    // Obtener referencia al estudiante local (NO recargar desde servidor)
    const calificacionLocal = calificaciones.value.find(c => c.student_id === studentId);
    if (!calificacionLocal) {
        console.error('Estudiante no encontrado:', studentId);
        return;
    }

    try {
        // Enviar petición al servidor
        await axios.post('/teacher/activities/calificar', {
            actividad_id: actividadId,
            student_id: studentId,
            calificacion_obtenida: calificacionNum,
        });

        // Actualizar localmente el valor de la actividad en el resumen
        // El estado local ya está actualizado por handleActivityInput, solo confirmamos
        if (calificacionLocal.resumen && calificacionLocal.resumen.TAREA) {
            const actividadData = calificacionLocal.resumen.TAREA.actividades?.find(a => a.id === actividadId);
            if (actividadData) {
                // Asegurar que el valor esté sincronizado
                actividadData.calificacion_obtenida = calificacionNum;
                
                // Recalcular porcentaje localmente
                const actividadesResumen = calificacionLocal.resumen.TAREA.actividades || [];
                const totalObtenido = actividadesResumen.reduce((sum, a) => sum + (parseFloat(a.calificacion_obtenida) || 0), 0);
                const totalMaximo = actividadesResumen.reduce((sum, a) => {
                    const act = actividades.value.find(ac => ac.id === a.id);
                    return sum + (parseFloat(act?.valor_maximo || a.valor_maximo || 0));
                }, 0);
                calificacionLocal.resumen.TAREA.porcentaje = totalMaximo > 0 ? (totalObtenido / totalMaximo) * 100 : 0;
                
                // Actualizar score_tareas en calificacion
                if (calificacionLocal.calificacion) {
                    calificacionLocal.calificacion.score_tareas = calificacionLocal.resumen.TAREA.porcentaje;
                }
            }
        }

        // Recalcular promedio localmente (preserva valores de examen/conducta no guardados)
        calcularPromedios(calificacionLocal);

        // NO recargar desde el servidor - confiar en el estado local
        // Los valores de examen y conducta no guardados se preservan
    } catch (error) {
        console.error('Error guardando calificación:', error);
        alert(error.response?.data?.message || 'Error al guardar la calificación');
    }
};

// Obtener calificación de actividad para un estudiante
const getActivityGrade = (actividadId, studentId) => {
    const calificacion = calificaciones.value.find(c => c.student_id === studentId);
    if (!calificacion || !calificacion.resumen) return null;

    const actividad = actividades.value.find(a => a.id === actividadId);
    if (!actividad) return null;

    const categoria = actividad.categoria;
    const resumen = calificacion.resumen[categoria];
    
    if (!resumen || !resumen.actividades) return null;

    const actividadData = resumen.actividades.find(a => a.id === actividadId);
    // Retornar 0 por defecto si no hay calificación
    return actividadData && actividadData.calificacion_obtenida !== null && actividadData.calificacion_obtenida !== undefined 
        ? actividadData.calificacion_obtenida 
        : 0;
};

// Obtener actividades por categoría (solo TAREAS ahora)
const getActividadesByCategoria = (categoria) => {
    // Solo retornar TAREAS, ya que examen y conducta son fijos
    if (categoria !== 'TAREA') {
        return [];
    }
    return actividades.value.filter(a => a.categoria === categoria && a.activa);
};

// Obtener total ponderado de una categoría
// Solo se usa para TAREAS (calculado desde actividades)
// EXAMEN y CONDUCTA se obtienen directamente de calificacion.score_examen y score_conducta
const getTotalPonderado = (calificacion, categoria) => {
    // Solo calcular para TAREAS desde resumen
    if (categoria !== 'TAREA') {
        return 0; // EXAMEN y CONDUCTA se obtienen directamente
    }
    if (!calificacion.resumen || !calificacion.resumen[categoria]) return 0;
    // Retornar el porcentaje calculado (0-100)
    return calificacion.resumen[categoria].porcentaje || 0;
};

// Función para calcular promedio localmente (sin guardar en BD)
const calcularPromedios = (item) => {
    if (!item.calificacion) {
        item.calificacion = {};
    }

    // Obtener valores actuales (todos en escala 0-100)
    // Usar SIEMPRE getTotalPonderado para obtener el valor más actualizado de las tareas
    // Esto asegura que si el usuario acaba de editar una actividad, el cálculo use el valor actualizado
    const porcentajeTareasActual = getTotalPonderado(item, 'TAREA') || 0;
    const rawScoreTareas = porcentajeTareasActual;
    
    const scoreExamen = item.calificacion.score_examen !== null && item.calificacion.score_examen !== undefined 
        ? parseFloat(item.calificacion.score_examen) || 0 
        : 0;
    const scoreConducta = item.calificacion.score_conducta !== null && item.calificacion.score_conducta !== undefined 
        ? parseFloat(item.calificacion.score_conducta) || 0 
        : 0;
    const tieneDerechoExamen = item.tiene_derecho_examen !== undefined ? item.tiene_derecho_examen : true;

    // Calcular puntos reales ponderados (en escala 0-100)
    // CRÍTICO: SUMA SIMPLE, sin re-escalar. Mantener porcentajes originales.
    // Si no tiene derecho a examen, simplemente no contar el examen (0), pero mantener 40% y 10%
    let puntosRealesTareas, puntosRealesExamen, puntosRealesConducta;
    let promedioRaw; // Suma de puntos reales ponderados (0-100)
    
    // Mantener porcentajes originales siempre (NO re-escalar)
    puntosRealesTareas = rawScoreTareas * 0.40;  // Siempre 40%
    puntosRealesConducta = scoreConducta * 0.10;  // Siempre 10%
    puntosRealesExamen = tieneDerechoExamen ? (scoreExamen * 0.50) : 0;  // 50% solo si tiene derecho
    
    // SUMA SIMPLE de los componentes disponibles (NO dividir entre suma de porcentajes activos)
    promedioRaw = puntosRealesTareas + puntosRealesConducta + puntosRealesExamen;
    
    // Convertir de 0-100 a 0-10 (el promedio final debe estar en escala 0-10)
    const promedio = promedioRaw / 10;
    item.calificacion.promedio_unidad = customRound(promedio, 2);
    
    // Actualizar score_tareas para mantener consistencia con el resumen
    item.calificacion.score_tareas = rawScoreTareas;
};

// Handlers para actualizar valores locales y calcular en tiempo real (SIN guardar)
const handleExamenInput = (item, value) => {
    // Actualizar localmente el valor mientras el usuario escribe
    if (!item.calificacion) {
        item.calificacion = {};
    }
    item.calificacion.score_examen = value === '' ? null : (parseFloat(value) || null);
    
    // Calcular promedio localmente en tiempo real
    calcularPromedios(item);
};

const handleConductaInput = (item, value) => {
    // Actualizar localmente el valor mientras el usuario escribe
    if (!item.calificacion) {
        item.calificacion = {};
    }
    item.calificacion.score_conducta = value === '' ? 0.00 : (parseFloat(value) || 0.00);
    
    // Calcular promedio localmente en tiempo real
    calcularPromedios(item);
};

// Handler para actividades (calcular localmente mientras se escribe)
const handleActivityInput = (item, actividadId, value) => {
    // Actualizar localmente el valor de la actividad
    const calificacionLocal = calificaciones.value.find(c => c.student_id === item.student_id);
    if (calificacionLocal && calificacionLocal.resumen && calificacionLocal.resumen.TAREA) {
        const actividadData = calificacionLocal.resumen.TAREA.actividades?.find(a => a.id === actividadId);
        if (actividadData) {
            const calificacionNum = value === '' ? 0 : (parseFloat(value) || 0);
            actividadData.calificacion_obtenida = calificacionNum;
            
            // Recalcular porcentaje de tareas localmente
            const actividadesResumen = calificacionLocal.resumen.TAREA.actividades || [];
            const totalObtenido = actividadesResumen.reduce((sum, a) => sum + (parseFloat(a.calificacion_obtenida) || 0), 0);
            const totalMaximo = actividadesResumen.reduce((sum, a) => {
                const act = actividades.value.find(ac => ac.id === a.id);
                return sum + (parseFloat(act?.valor_maximo || a.valor_maximo || 0));
            }, 0);
            const porcentajeTareas = totalMaximo > 0 ? (totalObtenido / totalMaximo) * 100 : 0;
            calificacionLocal.resumen.TAREA.porcentaje = porcentajeTareas;
            
            // Actualizar score_tareas en calificacion
            if (calificacionLocal.calificacion) {
                calificacionLocal.calificacion.score_tareas = porcentajeTareas;
            }
            
            // Recalcular promedio localmente
            calcularPromedios(calificacionLocal);
        }
    }
};

// Guardar calificación de examen o conducta (SOLO cuando sale del input)
const saveGrade = async (inscripcionId, studentId, tipo, valor) => {
    if (valor === '' || valor === null || valor === undefined) {
        return;
    }

    const valorNum = parseFloat(valor);
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

    try {
        const data = {
            inscripcion_id: inscripcionId,
            unidad: unidad.value,
        };

        if (tipo === 'examen') {
            data.score_examen = valorNum;
        } else if (tipo === 'conducta') {
            data.score_conducta = valorNum;
        }

        // Enviar petición al servidor
        const response = await axios.post('/teacher/grades', data);
        
        // Asegurar que calificacion existe
        if (!calificacionLocal.calificacion) {
            calificacionLocal.calificacion = {};
        }
        
        // Verificar si la respuesta tiene la propiedad .calificacion
        if (response.data && response.data.calificacion) {
            // Actualizar usando Object.assign para preservar otros valores locales
            // Acceder correctamente a response.data.calificacion (NO solo response.data)
            Object.assign(calificacionLocal.calificacion, {
                // Actualizar promedio si viene del servidor
                promedio_unidad: response.data.calificacion.promedio_unidad !== undefined 
                    ? response.data.calificacion.promedio_unidad 
                    : calificacionLocal.calificacion.promedio_unidad,
                // Actualizar score_tareas si viene del servidor
                score_tareas: response.data.calificacion.score_tareas !== undefined 
                    ? response.data.calificacion.score_tareas 
                    : calificacionLocal.calificacion.score_tareas,
                // Actualizar score_examen solo si el tipo es examen Y viene del servidor
                score_examen: (tipo === 'examen' && response.data.calificacion.score_examen !== undefined) 
                    ? response.data.calificacion.score_examen 
                    : calificacionLocal.calificacion.score_examen,
                // Actualizar score_conducta solo si el tipo es conducta Y viene del servidor
                score_conducta: (tipo === 'conducta' && response.data.calificacion.score_conducta !== undefined) 
                    ? response.data.calificacion.score_conducta 
                    : calificacionLocal.calificacion.score_conducta,
                // Actualizar derecho_examen si viene del servidor
                derecho_examen: response.data.calificacion.derecho_examen !== undefined 
                    ? response.data.calificacion.derecho_examen 
                    : calificacionLocal.calificacion.derecho_examen,
            });
        } else {
            // Si no hay respuesta estructurada, actualizar manualmente
            if (tipo === 'examen') {
                calificacionLocal.calificacion.score_examen = valorNum;
            } else if (tipo === 'conducta') {
                calificacionLocal.calificacion.score_conducta = valorNum;
            }
            // Recalcular promedio localmente
            calcularPromedios(calificacionLocal);
        }

        // NO recargar toda la lista - preservar estado local de otros estudiantes
    } catch (error) {
        console.error('Error guardando calificación:', error);
        alert(error.response?.data?.message || 'Error al guardar la calificación');
    }
};

// Calcular promedio final
// Ya no se usa esta función porque el promedio viene calculado del backend
// Pero la mantenemos por compatibilidad
const calcularPromedio = (calificacion) => {
    if (!calificacion.calificacion) return null;

    // Usar el promedio que viene del backend (ya está en escala 0-10)
    if (calificacion.calificacion.promedio_unidad !== null && calificacion.calificacion.promedio_unidad !== undefined) {
        return parseFloat(calificacion.calificacion.promedio_unidad);
    }

    // Fallback: calcular manualmente si no viene del backend
    // Tareas: porcentaje de 0-100, convertirlo a 0-10
    const tareas = getTotalPonderado(calificacion, 'TAREA') / 10; // Convertir a 0-10
    
    // Examen y Conducta: valores directos de 0-100, convertirlos a 0-10
    const examen = calificacion.tiene_derecho_examen && calificacion.calificacion?.score_examen !== null
        ? (parseFloat(calificacion.calificacion.score_examen) || 0) / 10
        : 0;
    const conducta = (parseFloat(calificacion.calificacion?.score_conducta) || 0) / 10;

    if (!calificacion.tiene_derecho_examen) {
        // Redistribuir: Tareas 80%, Conducta 20%
        return (tareas * 0.80) + (conducta * 0.20);
    } else {
        // Ponderación normal: Tareas 40%, Examen 50%, Conducta 10%
        return (tareas * 0.40) + (examen * 0.50) + (conducta * 0.10);
    }
};

// Aplicar redondeo personalizado
const customRound = (value) => {
    if (value === null || value === undefined) return null;
    const decimal = value - Math.floor(value);
    if (decimal <= 0.5) {
        return Math.floor(value * 100) / 100;
    } else {
        return Math.ceil(value * 100) / 100;
    }
};

// Obtener color de calificación
const getGradeColor = (promedio) => {
    if (!promedio) return darkMode.value ? 'text-gray-400' : 'text-gray-600';
    if (promedio >= 9.0) return 'text-green-600';
    if (promedio >= 8.0) return 'text-blue-600';
    if (promedio >= 7.0) return 'text-yellow-600';
    return 'text-red-600';
};

// Obtener color de asistencia
const getAttendanceColor = (porcentaje) => {
    if (porcentaje >= 80) return 'text-green-600';
    if (porcentaje >= 60) return 'text-yellow-600';
    return 'text-red-600';
};

// Watch unidad para recargar
watch(unidad, () => {
    if (showGradesTable.value) {
        loadGrades();
        if (showActivitiesModal.value) {
            loadActivities();
        }
    }
});

// Confirmar calificaciones (publicar al portal)
const confirmarCalificaciones = async () => {
    if (!academicLoad.value) return;
    
    if (!confirm('¿Estás seguro de confirmar estas calificaciones? Una vez confirmadas, se publicarán en el portal de alumnos y no podrán ser editadas a menos que actives el modo de edición.')) {
        return;
    }

    try {
        loadingConfirmacion.value = true;
        const response = await axios.post('/teacher/grades/confirm', {
            academic_load_id: academicLoad.value.id,
            unidad: unidad.value
        });
        
        alert('Calificaciones confirmadas exitosamente. Ya están visibles en el portal de alumnos.');
        // Preservar valores locales de examen y conducta al recargar
        await loadGrades(true);
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
                                    @click="openActivitiesModal"
                                    :class="['px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2', darkMode ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white']"
                                >
                                    <Cog6ToothIcon class="w-4 h-4" />
                                    Gestionar Actividades de Tareas
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
                        <select 
                            v-model="unidad"
                            :class="['px-5 py-3 rounded-lg border w-full md:w-auto text-sm', darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                        >
                            <option :value="1">Unidad 1</option>
                            <option :value="2">Unidad 2</option>
                            <option :value="3">Unidad 3</option>
                        </select>
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
                                        <!-- ZONA 1: DATOS E INPUTS (Fondo Blanco) -->
                                        <!-- 1. Alumno (Fijo) -->
                                        <th :class="['px-4 py-3 text-left font-bold text-xs sticky left-0 z-10 bg-white', darkMode ? 'bg-gray-800 text-white' : 'bg-white text-gray-900']">Alumno</th>
                                        
                                        <!-- 2. Asistencia (Fijo) -->
                                        <th :class="['px-4 py-3 text-center font-bold text-xs bg-white', darkMode ? 'bg-gray-800 text-white' : 'bg-white text-gray-900']">Asistencia</th>
                                        
                                        <!-- 3. Actividades Dinámicas (v-for) -->
                                        <template v-for="actividad in getActividadesByCategoria('TAREA')" :key="`tarea-${actividad.id}`">
                                            <th :class="['px-2 py-3 text-center font-bold text-xs whitespace-nowrap bg-white', darkMode ? 'bg-gray-800 text-white' : 'bg-white text-gray-900']">
                                                {{ actividad.titulo }}<br>
                                                <span class="text-xs font-normal">({{ actividad.valor_maximo }} pts)</span>
                                            </th>
                                        </template>
                                        
                                        <!-- 4. Input Examen (0-100 pts) -->
                                        <th :class="['px-4 py-3 text-center font-bold text-xs bg-white', darkMode ? 'bg-gray-800 text-white' : 'bg-white text-gray-900']">
                                            Examen<br>
                                            <span class="text-xs font-normal">(0-100 pts)</span>
                                        </th>
                                        
                                        <!-- 5. Input Conducta (0-100 pts) -->
                                        <th :class="['px-4 py-3 text-center font-bold text-xs bg-white', darkMode ? 'bg-gray-800 text-white' : 'bg-white text-gray-900']">
                                            Conducta<br>
                                            <span class="text-xs font-normal">(0-100 pts)</span>
                                        </th>
                                        
                                        <!-- ZONA 2: TABLERO DE RESULTADOS (Fondo de Color - Solo Lectura) -->
                                        <!-- 6. Resumen Tareas (40%) - Fondo Verde -->
                                        <th :class="['px-4 py-3 text-center font-bold text-xs bg-green-100', darkMode ? 'bg-green-900/30 text-white' : 'text-gray-900']">
                                            Total Tareas<br>
                                            <span class="text-xs font-normal">(40%)</span>
                                        </th>
                                        
                                        <!-- 7. Resumen Examen (50%) - Fondo Azul -->
                                        <th :class="['px-4 py-3 text-center font-bold text-xs bg-blue-100', darkMode ? 'bg-blue-900/30 text-white' : 'text-gray-900']">
                                            Examen<br>
                                            <span class="text-xs font-normal">(50%)</span>
                                        </th>
                                        
                                        <!-- 8. Resumen Conducta (10%) - Fondo Amarillo -->
                                        <th :class="['px-4 py-3 text-center font-bold text-xs bg-yellow-100', darkMode ? 'bg-yellow-900/30 text-white' : 'text-gray-900']">
                                            Conducta<br>
                                            <span class="text-xs font-normal">(10%)</span>
                                        </th>
                                        
                                        <!-- 9. PROMEDIO FINAL -->
                                        <th :class="['px-4 py-3 text-center font-bold text-xs', darkMode ? 'text-white' : 'text-gray-900']">Promedio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr 
                                        v-for="(item, index) in calificaciones" 
                                        :key="item.inscripcion_id"
                                        :class="['border-b-2', darkMode ? 'border-gray-600' : 'border-gray-200', index % 2 === 0 ? (darkMode ? 'bg-gray-800' : 'bg-white') : (darkMode ? 'bg-gray-700' : 'bg-gray-50')]"
                                    >
                                        <!-- ZONA 1: DATOS E INPUTS (Fondo Blanco) -->
                                        <!-- 1. Alumno (Fijo) -->
                                        <td :class="['px-4 py-3 sticky left-0 z-10 bg-white border-r-2', darkMode ? 'bg-gray-800 text-gray-200 border-gray-600' : 'bg-white text-gray-900 border-gray-200']">
                                            <div class="font-medium text-sm md:text-base">{{ item.student_name }}</div>
                                            <div :class="['text-xs', darkMode ? 'text-gray-400' : 'text-gray-500']">{{ item.matricula }}</div>
                                        </td>
                                        
                                        <!-- 2. Asistencia (Fijo) -->
                                        <td :class="['px-4 py-3 text-center bg-white', darkMode ? 'bg-gray-800' : 'bg-white']">
                                            <div :class="['font-semibold', getAttendanceColor(item.porcentaje_asistencia)]">
                                                {{ item.porcentaje_asistencia }}%
                                            </div>
                                            <div v-if="!item.tiene_derecho_examen" class="mt-1">
                                                <ExclamationTriangleIcon class="w-4 h-4 text-red-500 mx-auto" />
                                            </div>
                                        </td>
                                        
                                        <!-- 3. Actividades Dinámicas (v-for) -->
                                        <template v-for="actividad in getActividadesByCategoria('TAREA')" :key="`tarea-input-${actividad.id}`">
                                            <td :class="['px-2 py-3 bg-white', darkMode ? 'bg-gray-800' : 'bg-white']">
                                                <input
                                                    :value="getActivityGrade(actividad.id, item.student_id) ?? 0"
                                                    @input="handleActivityInput(item, actividad.id, $event.target.value)"
                                                    @change="saveActivityGrade(actividad.id, item.student_id, $event.target.value)"
                                                    type="number"
                                                    :min="0"
                                                    :max="actividad.valor_maximo"
                                                    step="0.1"
                                                    class="no-spinner w-16 px-2 py-1 rounded border text-center text-sm"
                                                    :class="[darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                                />
                                            </td>
                                        </template>
                                        
                                        <!-- 4. Input Examen (0-100 pts) -->
                                        <td :class="['px-4 py-3 bg-white', darkMode ? 'bg-gray-800' : 'bg-white']">
                                            <input
                                                :value="item.calificacion?.score_examen ?? ''"
                                                @input="handleExamenInput(item, $event.target.value)"
                                                @change="saveGrade(item.inscripcion_id, item.student_id, 'examen', $event.target.value)"
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.1"
                                                :disabled="!item.tiene_derecho_examen"
                                                class="no-spinner w-20 px-2 py-1 rounded border text-center text-sm"
                                                :class="[
                                                    item.tiene_derecho_examen
                                                        ? (darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900')
                                                        : 'bg-gray-200 border-gray-400 text-gray-500 cursor-not-allowed'
                                                ]"
                                            />
                                            <div v-if="!item.tiene_derecho_examen" class="mt-1">
                                                <ExclamationTriangleIcon class="w-4 h-4 text-red-500 mx-auto" />
                                            </div>
                                        </td>
                                        
                                        <!-- 5. Input Conducta (0-100 pts) -->
                                        <td :class="['px-4 py-3 bg-white', darkMode ? 'bg-gray-800' : 'bg-white']">
                                            <input
                                                :value="item.calificacion?.score_conducta ?? ''"
                                                @input="handleConductaInput(item, $event.target.value)"
                                                @change="saveGrade(item.inscripcion_id, item.student_id, 'conducta', $event.target.value)"
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.1"
                                                class="no-spinner w-20 px-2 py-1 rounded border text-center text-sm"
                                                :class="[darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                            />
                                        </td>
                                        
                                        <!-- ZONA 2: TABLERO DE RESULTADOS (Fondo de Color - Solo Lectura) -->
                                        <!-- 6. Resumen Tareas (40%) - Fondo Verde -->
                                        <td :class="['px-4 py-3 text-center bg-green-50', darkMode ? 'bg-green-900/20' : '']">
                                            <div class="flex flex-col items-center">
                                                <span class="font-bold text-lg" :class="darkMode ? 'text-green-300' : 'text-green-700'">
                                                    {{ customRound(getTotalPonderado(item, 'TAREA'))?.toFixed(1) || '0.0' }}
                                                </span>
                                                <span :class="['text-xs mt-1', darkMode ? 'text-green-400' : 'text-green-600']">
                                                    {{ ((getTotalPonderado(item, 'TAREA') || 0) * 0.40).toFixed(1) }} pts reales
                                                </span>
                                            </div>
                                        </td>
                                        
                                        <!-- 7. Resumen Examen (50%) - Fondo Azul -->
                                        <td :class="['px-4 py-3 text-center bg-blue-50', darkMode ? 'bg-blue-900/20' : '']">
                                            <div class="flex flex-col items-center">
                                                <span v-if="item.tiene_derecho_examen && item.calificacion && item.calificacion.score_examen !== null && item.calificacion.score_examen !== undefined" class="font-bold text-lg" :class="darkMode ? 'text-blue-300' : 'text-blue-700'">
                                                    {{ customRound(item.calificacion.score_examen)?.toFixed(1) || '0.0' }}
                                                </span>
                                                <span v-else class="font-bold text-lg" :class="darkMode ? 'text-gray-500' : 'text-gray-400'">
                                                    --
                                                </span>
                                                <span v-if="item.tiene_derecho_examen && item.calificacion && item.calificacion.score_examen !== null && item.calificacion.score_examen !== undefined" :class="['text-xs mt-1', darkMode ? 'text-blue-400' : 'text-blue-600']">
                                                    {{ ((item.calificacion?.score_examen || 0) * 0.50).toFixed(1) }} pts reales
                                                </span>
                                                <span v-else :class="['text-xs mt-1', darkMode ? 'text-gray-500' : 'text-gray-400']">
                                                    Sin derecho
                                                </span>
                                            </div>
                                        </td>
                                        
                                        <!-- 8. Resumen Conducta (10%) - Fondo Amarillo -->
                                        <td :class="['px-4 py-3 text-center bg-yellow-50', darkMode ? 'bg-yellow-900/20' : '']">
                                            <div class="flex flex-col items-center">
                                                <span class="font-bold text-lg" :class="darkMode ? 'text-yellow-300' : 'text-yellow-700'">
                                                    {{ item.calificacion?.score_conducta ? customRound(item.calificacion.score_conducta)?.toFixed(1) : '0.0' }}
                                                </span>
                                                <span :class="['text-xs mt-1', darkMode ? 'text-yellow-400' : 'text-yellow-600']">
                                                    {{ ((item.calificacion?.score_conducta || 0) * 0.10).toFixed(1) }} pts reales
                                                </span>
                                            </div>
                                        </td>
                                        
                                        <td :class="['px-4 py-3 text-center']">
                                            <span :class="['font-bold text-lg', getGradeColor(item.calificacion?.promedio_unidad)]">
                                                {{ item.calificacion?.promedio_unidad ? customRound(item.calificacion.promedio_unidad)?.toFixed(2) : '--' }}
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

        <!-- Modal de Gestión de Actividades -->
        <div v-if="showActivitiesModal" class="fixed inset-0 z-50 overflow-y-auto" @click.self="closeActivitiesModal">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div :class="['relative rounded-2xl shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto', darkMode ? 'bg-gray-800 border-2 border-gray-600' : 'bg-white border-2 border-gray-200']">
                    <!-- Header del Modal -->
                    <div :class="['sticky top-0 z-10 flex items-center justify-between p-6 border-b', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white border-gray-200']">
                        <h2 :class="['text-2xl font-bold', darkMode ? 'text-white' : 'text-gray-900']">
                            Gestionar Actividades de Tareas - Unidad {{ unidad }}
                        </h2>
                        <p :class="['text-sm mt-1', darkMode ? 'text-gray-400' : 'text-gray-600']">
                            Nota: El Examen (50%) y Conducta (10%) se califican directamente en la tabla de calificaciones
                        </p>
                        <button
                            @click="closeActivitiesModal"
                            :class="['p-2 rounded-lg', darkMode ? 'hover:bg-gray-700 text-gray-300' : 'hover:bg-gray-100 text-gray-600']"
                        >
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                    </div>

                    <!-- Contenido del Modal -->
                    <div class="p-6 space-y-6">
                        <!-- Formulario de Actividad -->
                        <div :class="['rounded-xl p-4 border', darkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-200']">
                            <h3 :class="['text-lg font-semibold mb-4', darkMode ? 'text-white' : 'text-gray-900']">
                                {{ editingActividad ? 'Editar Actividad de Tarea' : 'Nueva Actividad de Tarea' }}
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label :class="['block text-sm font-medium mb-2', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                        Título *
                                    </label>
                                    <input
                                        v-model="actividadForm.titulo"
                                        type="text"
                                        :class="['w-full px-3 py-2 rounded-lg border', darkMode ? 'bg-gray-600 border-gray-500 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                        placeholder="Ej: Tarea 1, Proyecto Final, etc."
                                    />
                                </div>
                                <!-- Categoría fija: solo TAREAS -->
                                <div>
                                    <label :class="['block text-sm font-medium mb-2', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                        Categoría *
                                    </label>
                                    <input
                                        type="text"
                                        value="Tarea (40%)"
                                        disabled
                                        :class="['w-full px-3 py-2 rounded-lg border', darkMode ? 'bg-gray-700 border-gray-500 text-gray-400 cursor-not-allowed' : 'bg-gray-100 border-gray-300 text-gray-600 cursor-not-allowed']"
                                    />
                                    <input type="hidden" v-model="actividadForm.categoria" value="TAREA" />
                                </div>
                                <div>
                                    <label :class="['block text-sm font-medium mb-2', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                        Valor Máximo (puntos) *
                                    </label>
                                    <input
                                        v-model.number="actividadForm.valor_maximo"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        :class="['w-full px-3 py-2 rounded-lg border', darkMode ? 'bg-gray-600 border-gray-500 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                        placeholder="100"
                                    />
                                </div>
                                <div>
                                    <label :class="['block text-sm font-medium mb-2', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                        Fecha Límite
                                    </label>
                                    <input
                                        v-model="actividadForm.fecha_limite"
                                        type="date"
                                        :class="['w-full px-3 py-2 rounded-lg border', darkMode ? 'bg-gray-600 border-gray-500 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                    />
                                </div>
                                <div class="md:col-span-2">
                                    <label :class="['block text-sm font-medium mb-2', darkMode ? 'text-gray-300' : 'text-gray-700']">
                                        Descripción
                                    </label>
                                    <textarea
                                        v-model="actividadForm.descripcion"
                                        rows="3"
                                        :class="['w-full px-3 py-2 rounded-lg border', darkMode ? 'bg-gray-600 border-gray-500 text-white' : 'bg-white border-gray-300 text-gray-900']"
                                        placeholder="Descripción de la actividad..."
                                    ></textarea>
                                </div>
                            </div>
                            <div class="mt-4 flex gap-2">
                                <button
                                    @click="saveActividad"
                                    :class="['px-4 py-2 rounded-lg text-sm font-medium', darkMode ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-green-500 hover:bg-green-600 text-white']"
                                >
                                    {{ editingActividad ? 'Actualizar' : 'Crear' }} Actividad
                                </button>
                                <button
                                    v-if="editingActividad"
                                    @click="closeActivitiesModal"
                                    :class="['px-4 py-2 rounded-lg text-sm font-medium', darkMode ? 'bg-gray-600 hover:bg-gray-700 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700']"
                                >
                                    Cancelar
                                </button>
                            </div>
                        </div>

                        <!-- Lista de Actividades -->
                        <div>
                            <h3 :class="['text-lg font-semibold mb-4', darkMode ? 'text-white' : 'text-gray-900']">
                                Actividades Registradas
                            </h3>
                            
                            <div v-if="loadingActivities" class="text-center py-8">
                                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2" :class="darkMode ? 'border-blue-400' : 'border-blue-600'"></div>
                            </div>
                            
                            <div v-else-if="actividades.length === 0" :class="['p-4 rounded-lg text-center', darkMode ? 'bg-gray-700' : 'bg-gray-100']">
                                <p :class="['text-sm', darkMode ? 'text-gray-400' : 'text-gray-600']">
                                    No hay actividades registradas. Crea una nueva actividad arriba.
                                </p>
                            </div>
                            
                            <div v-else class="space-y-3">
                                <div
                                    v-for="actividad in actividades"
                                    :key="actividad.id"
                                    :class="['p-4 rounded-lg border', darkMode ? 'bg-gray-700 border-gray-600' : 'bg-white border-gray-200']"
                                >
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-2">
                                                <h4 :class="['font-semibold', darkMode ? 'text-white' : 'text-gray-900']">
                                                    {{ actividad.titulo }}
                                                </h4>
                                                <span class="px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                                                    TAREA
                                                </span>
                                            </div>
                                            <p v-if="actividad.descripcion" :class="['text-sm mb-2', darkMode ? 'text-gray-300' : 'text-gray-600']">
                                                {{ actividad.descripcion }}
                                            </p>
                                            <div class="flex gap-4 text-sm">
                                                <span :class="[darkMode ? 'text-gray-400' : 'text-gray-600']">
                                                    Valor: <strong>{{ actividad.valor_maximo }} pts</strong>
                                                </span>
                                                <span v-if="actividad.fecha_limite" :class="[darkMode ? 'text-gray-400' : 'text-gray-600']">
                                                    Fecha límite: <strong>{{ new Date(actividad.fecha_limite).toLocaleDateString() }}</strong>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex gap-2 ml-4">
                                            <button
                                                @click="editActividad(actividad)"
                                                :class="['p-2 rounded-lg', darkMode ? 'hover:bg-gray-600 text-blue-400' : 'hover:bg-gray-100 text-blue-600']"
                                            >
                                                <PencilIcon class="w-5 h-5" />
                                            </button>
                                            <button
                                                @click="deleteActividad(actividad.id)"
                                                :class="['p-2 rounded-lg', darkMode ? 'hover:bg-gray-600 text-red-400' : 'hover:bg-gray-100 text-red-600']"
                                            >
                                                <TrashIcon class="w-5 h-5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
