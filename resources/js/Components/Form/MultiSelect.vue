<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { XMarkIcon, ChevronDownIcon, CheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => []
    },
    options: {
        type: Array,
        required: true,
        validator: (value) => {
            return value.every(opt => opt.id !== undefined && opt.name !== undefined);
        }
    },
    placeholder: {
        type: String,
        default: 'Seleccionar opciones...'
    },
    disabled: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue']);

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

// Estado del componente
const isOpen = ref(false);
const containerRef = ref(null);
const dropdownRef = ref(null);
const maxDropdownHeight = ref(400);

// Computed para opciones filtradas (todas las opciones, sin filtro de búsqueda)
const filteredOptions = computed(() => {
    return props.options;
});

// Computed para opciones seleccionadas
const selectedOptions = computed(() => {
    return props.options.filter(option => 
        props.modelValue.includes(option.id)
    );
});

// Verificar si una opción está seleccionada
const isSelected = (id) => {
    return props.modelValue.includes(id);
};

// Toggle selección de una opción
const toggleOption = (id) => {
    if (props.disabled) return;
    
    const currentValue = [...props.modelValue];
    const index = currentValue.indexOf(id);
    
    if (index > -1) {
        // Deseleccionar
        currentValue.splice(index, 1);
    } else {
        // Seleccionar
        currentValue.push(id);
    }
    
    emit('update:modelValue', currentValue);
};

// Eliminar una opción seleccionada
const removeOption = (id, event) => {
    event.stopPropagation();
    if (props.disabled) return;
    
    const currentValue = props.modelValue.filter(val => val !== id);
    emit('update:modelValue', currentValue);
};

// Calcular altura máxima del dropdown dinámicamente
const calculateMaxHeight = () => {
    if (!containerRef.value || !isOpen.value) return;
    
    const rect = containerRef.value.getBoundingClientRect();
    const viewportHeight = window.innerHeight;
    const spaceBelow = viewportHeight - rect.bottom;
    const spaceAbove = rect.top;
    
    // Calcular altura basada en la cantidad de opciones
    const optionCount = props.options.length;
    const optionHeight = 52; // Altura aproximada de cada opción (py-3 + texto)
    const padding = 16; // py-2 = 8px arriba y abajo
    const minHeight = 200;
    const maxHeight = 700; // Aumentado significativamente para más espacio
    
    // Calcular altura ideal basada en el contenido
    const idealContentHeight = optionCount * optionHeight + padding;
    
    // Usar el espacio disponible, priorizando el espacio debajo
    const availableSpaceBelow = spaceBelow - 30; // 30px de margen
    const availableSpaceAbove = spaceAbove - 30;
    const maxAvailableSpace = Math.max(availableSpaceBelow, availableSpaceAbove);
    
    // Si hay muchas opciones, usar más espacio disponible
    // Priorizar mostrar más opciones sin scroll si hay espacio
    if (optionCount > 5) {
        // Para muchas opciones, usar más espacio disponible
        maxDropdownHeight.value = Math.max(
            Math.min(idealContentHeight, maxAvailableSpace, maxHeight),
            minHeight
        );
    } else {
        // Para pocas opciones, usar solo el espacio necesario
        maxDropdownHeight.value = Math.max(
            Math.min(idealContentHeight, maxAvailableSpace),
            minHeight
        );
    }
};

// Toggle dropdown
const toggleDropdown = (event) => {
    if (props.disabled) return;
    // No abrir si se hizo clic en el botón de eliminar de un tag
    if (event && event.target) {
        const target = event.target;
        if (target.closest('button')) {
            return;
        }
    }
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        // Calcular altura máxima cuando se abre
        setTimeout(() => {
            calculateMaxHeight();
            // Recalcular cuando se hace scroll o resize
            window.addEventListener('scroll', calculateMaxHeight, { passive: true });
            window.addEventListener('resize', calculateMaxHeight, { passive: true });
        }, 10);
    } else {
        // Remover listeners cuando se cierra
        window.removeEventListener('scroll', calculateMaxHeight);
        window.removeEventListener('resize', calculateMaxHeight);
    }
};

// Cerrar dropdown
const closeDropdown = () => {
    isOpen.value = false;
    // Remover listeners cuando se cierra
    window.removeEventListener('scroll', calculateMaxHeight);
    window.removeEventListener('resize', calculateMaxHeight);
};

// Click outside handler
const handleClickOutside = (event) => {
    if (containerRef.value && !containerRef.value.contains(event.target)) {
        closeDropdown();
    }
};

// Escuchar clicks fuera del componente
onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    window.removeEventListener('scroll', calculateMaxHeight);
    window.removeEventListener('resize', calculateMaxHeight);
});

// Obtener nombre de opción por ID
const getOptionName = (id) => {
    const option = props.options.find(opt => opt.id === id);
    return option ? option.name : '';
};
</script>

<template>
    <div ref="containerRef" class="relative w-full">
        <!-- Campo principal -->
        <div
            @click="toggleDropdown"
            :class="[
                'w-full min-h-[42px] h-auto rounded-lg border transition-all flex flex-wrap items-center gap-2 p-1.5 cursor-pointer',
                darkMode
                    ? 'bg-gray-700 border-gray-600 text-white focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500'
                    : 'bg-white border-gray-300 text-gray-900 focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500',
                disabled && 'opacity-50 cursor-not-allowed',
                isOpen && (darkMode ? 'ring-2 ring-blue-500 border-blue-500' : 'ring-2 ring-blue-500 border-blue-500')
            ]"
        >
            <!-- Tags de opciones seleccionadas -->
            <template v-if="selectedOptions.length > 0">
                <span
                    v-for="option in selectedOptions"
                    :key="option.id"
                    :class="[
                        'inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-sm font-medium',
                        darkMode
                            ? 'bg-gray-600 text-gray-200'
                            : 'bg-gray-100 text-gray-700'
                    ]"
                >
                    {{ option.name }}
                    <button
                        type="button"
                        @click="removeOption(option.id, $event)"
                        :class="[
                            'hover:text-red-500 transition-colors flex-shrink-0',
                            darkMode ? 'text-gray-300 hover:text-red-400' : 'text-gray-500'
                        ]"
                    >
                        <XMarkIcon class="w-3.5 h-3.5" />
                    </button>
                </span>
            </template>

            <!-- Placeholder cuando no hay selecciones -->
            <span
                v-if="selectedOptions.length === 0"
                :class="[
                    'text-sm',
                    darkMode ? 'text-gray-400' : 'text-gray-500'
                ]"
            >
                {{ placeholder }}
            </span>

            <!-- Icono de flecha -->
            <div class="flex-shrink-0">
                <ChevronDownIcon
                    :class="[
                        'w-5 h-5 transition-transform',
                        isOpen && 'transform rotate-180',
                        darkMode ? 'text-gray-400' : 'text-gray-500'
                    ]"
                />
            </div>
        </div>

        <!-- Dropdown -->
        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                ref="dropdownRef"
                :class="[
                    'absolute z-50 w-full mt-2 rounded-lg border shadow-xl overflow-hidden',
                    darkMode
                        ? 'bg-gray-800 border-gray-600'
                        : 'bg-white border-gray-200'
                ]"
                :style="{ maxHeight: `${maxDropdownHeight}px` }"
            >

                <!-- Lista de opciones -->
                <div class="py-2 overflow-y-auto" :style="{ maxHeight: `${maxDropdownHeight - 10}px` }">
                    <div
                        v-if="filteredOptions.length === 0"
                        :class="[
                            'px-4 py-4 text-base text-center',
                            darkMode ? 'text-gray-400' : 'text-gray-500'
                        ]"
                    >
                        No se encontraron opciones
                    </div>

                    <button
                        v-for="option in filteredOptions"
                        :key="option.id"
                        type="button"
                        @click="toggleOption(option.id)"
                        :class="[
                            'w-full px-4 py-3 text-left text-base transition-colors flex items-center justify-between',
                            darkMode
                                ? isSelected(option.id)
                                    ? 'bg-blue-600 text-white'
                                    : 'text-gray-300 hover:bg-gray-700'
                                : isSelected(option.id)
                                    ? 'bg-blue-50 text-blue-900 border-l-4 border-blue-500'
                                    : 'text-gray-900 hover:bg-gray-50'
                        ]"
                    >
                        <span :class="isSelected(option.id) ? 'font-semibold' : 'font-normal'">
                            {{ option.name }}
                        </span>
                        <CheckIcon
                            v-if="isSelected(option.id)"
                            :class="[
                                'w-5 h-5 flex-shrink-0',
                                darkMode ? 'text-white' : 'text-blue-600'
                            ]"
                        />
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>

