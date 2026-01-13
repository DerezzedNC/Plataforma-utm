import { ref, onMounted } from 'vue';

// Estado global del modo oscuro
const darkMode = ref(false);

export function useDarkMode() {
    onMounted(() => {
        // Cargar el estado del modo oscuro desde localStorage
        const savedMode = localStorage.getItem('darkMode');
        darkMode.value = savedMode === 'true';
        
        // Aplicar el modo oscuro al documento
        if (darkMode.value) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    });

    const toggleDarkMode = () => {
        darkMode.value = !darkMode.value;
        localStorage.setItem('darkMode', darkMode.value.toString());
        
        if (darkMode.value) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    };

    return {
        darkMode,
        toggleDarkMode
    };
}