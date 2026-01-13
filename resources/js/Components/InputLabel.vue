<script setup>
import { useDarkMode } from '@/composables/useDarkMode.js';

defineProps({
    value: {
        type: String,
    },
});

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();
</script>

<template>
    <label :class="['block text-sm font-medium', darkMode ? 'text-gray-300' : 'text-gray-700']">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
