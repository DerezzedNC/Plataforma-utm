<script setup>
import { onMounted, ref } from 'vue';
import { useDarkMode } from '@/composables/useDarkMode.js';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        :class="['rounded-md shadow-sm focus:ring-indigo-500', 
                 darkMode 
                 ? 'border-gray-700 bg-gray-900 text-gray-300 focus:border-indigo-600 focus:ring-indigo-600' 
                 : 'border-gray-300 focus:border-indigo-500']"
        v-model="model"
        ref="input"
    />
</template>
