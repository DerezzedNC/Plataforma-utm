<script setup>
import { ref, computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import PhosphorIcon from '@/Components/PhosphorIcon.vue';
import { Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';

const showingNavigationDropdown = ref(false);

// Usar el composable de modo oscuro
const { darkMode, toggleDarkMode } = useDarkMode();

// Verificar si es estudiante usando computed
const props = defineProps({
    auth: Object,
});

const isStudent = computed(() => {
    return props.auth?.user?.email?.endsWith('@alumno.utmetropolitana.edu.mx') || false;
});
</script>

<template>
    <div>
        <div :class="['min-h-screen flex transition-colors duration-300', darkMode ? 'bg-gray-900' : 'bg-gray-50']">
            
            <!-- Sidebar Navigation - Solo para Estudiantes -->
            <aside 
                v-if="isStudent"
                :class="[
                    'fixed left-0 top-0 h-full w-64 transition-transform duration-300 z-30',
                    'border-r shadow-lg',
                    darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200',
                    showingNavigationDropdown ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
                ]"
            >
                <!-- Logo Section -->
                <div class="flex items-center justify-center h-20 px-6 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                    <Link :href="route('dashboard')" class="flex flex-col items-center">
                        <div :class="['w-14 h-14 rounded-xl flex items-center justify-center text-white font-bold text-2xl mb-2', darkMode ? 'bg-green-500' : 'bg-green-600']">
                            UTM
                        </div>
                        <h2 :class="['text-sm font-display font-bold text-center', darkMode ? 'text-gray-300' : 'text-gray-700']">
                            Universidad Técnica<br>Metropolitana
                        </h2>
                    </Link>
                </div>

                <!-- Navigation Links -->
                <nav class="mt-8 px-4">
                    <div class="space-y-2">
                        <Link
                            :href="route('dashboard')"
                            :class="[
                                'flex items-center px-4 py-3 rounded-lg transition-all duration-200',
                                route().current('dashboard') 
                                    ? (darkMode ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700')
                                    : (darkMode ? 'text-gray-300 hover:bg-gray-700' : 'text-gray-700 hover:bg-gray-100')
                            ]"
                        >
                            <PhosphorIcon name="house" :size="20" weight="regular" class="mr-3" />
                            <span class="font-medium">Home</span>
                        </Link>

                        <Link
                            :href="route('consultar-horario')"
                            :class="[
                                'flex items-center px-4 py-3 rounded-lg transition-all duration-200',
                                route().current('consultar-horario') 
                                    ? (darkMode ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700')
                                    : (darkMode ? 'text-gray-300 hover:bg-gray-700' : 'text-gray-700 hover:bg-gray-100')
                            ]"
                        >
                            <PhosphorIcon name="clock" :size="20" weight="regular" class="mr-3" />
                            <span class="font-medium">Horario</span>
                        </Link>

                        <Link
                            :href="route('historial-academico')"
                            :class="[
                                'flex items-center px-4 py-3 rounded-lg transition-all duration-200',
                                route().current('historial-academico') 
                                    ? (darkMode ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700')
                                    : (darkMode ? 'text-gray-300 hover:bg-gray-700' : 'text-gray-700 hover:bg-gray-100')
                            ]"
                        >
                            <PhosphorIcon name="document" :size="20" weight="regular" class="mr-3" />
                            <span class="font-medium">Calificaciones</span>
                        </Link>

                        <Link
                            :href="route('cursos.index')"
                            :class="[
                                'flex items-center px-4 py-3 rounded-lg transition-all duration-200',
                                route().current('cursos.index') || route().current('cursos-extra')
                                    ? (darkMode ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700')
                                    : (darkMode ? 'text-gray-300 hover:bg-gray-700' : 'text-gray-700 hover:bg-gray-100')
                            ]"
                        >
                            <PhosphorIcon name="folder" :size="20" weight="regular" class="mr-3" />
                            <span class="font-medium">Cursos</span>
                        </Link>

                        <Link
                            :href="route('procesos-administrativos')"
                            :class="[
                                'flex items-center px-4 py-3 rounded-lg transition-all duration-200',
                                route().current('procesos-administrativos') 
                                    ? (darkMode ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700')
                                    : (darkMode ? 'text-gray-300 hover:bg-gray-700' : 'text-gray-700 hover:bg-gray-100')
                            ]"
                        >
                            <PhosphorIcon name="gear" :size="20" weight="regular" class="mr-3" />
                            <span class="font-medium">Trámites</span>
                        </Link>

                        <Link
                            :href="route('profile.edit')"
                            :class="[
                                'flex items-center px-4 py-3 rounded-lg transition-all duration-200',
                                route().current('profile.edit') 
                                    ? (darkMode ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700')
                                    : (darkMode ? 'text-gray-300 hover:bg-gray-700' : 'text-gray-700 hover:bg-gray-100')
                            ]"
                        >
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <span class="font-medium">Mi Perfil</span>
                        </Link>
                    </div>
                </nav>

                <!-- Bottom Section -->
                <div class="absolute bottom-0 left-0 right-0 px-4 pb-6" :class="darkMode ? 'border-t border-gray-700' : 'border-t border-gray-200'">
                    <div class="mt-6">
                        <button
                            @click="toggleDarkMode"
                            :class="['flex items-center w-full px-4 py-3 rounded-lg transition-all duration-200', darkMode ? 'text-gray-300 hover:bg-gray-700' : 'text-gray-700 hover:bg-gray-100']"
                        >
                            <svg v-if="!darkMode" class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
                            </svg>
                            <svg v-else class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                            </svg>
                            <span class="font-medium">{{ darkMode ? 'Modo Claro' : 'Modo Oscuro' }}</span>
                        </button>
                    </div>
                </div>
            </aside>

            <!-- Main Content Area -->
            <div :class="['flex-1 transition-all duration-300', isStudent ? 'md:ml-64' : '']">
                <!-- Top Navigation Bar -->
                <nav :class="['sticky top-0 z-20 border-b shadow-sm transition-colors duration-300', darkMode ? 'border-gray-700 bg-gray-800' : 'border-gray-200 bg-white']">
                    <div class="mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex h-16 justify-between items-center">
                            <!-- Left side - Mobile Menu and Logo (if not student) -->
                            <div class="flex items-center">
                                <!-- Mobile menu button (only for students with sidebar) -->
                                <button
                                    v-if="isStudent"
                                    @click="showingNavigationDropdown = !showingNavigationDropdown"
                                    :class="['md:hidden inline-flex items-center justify-center rounded-md p-2 transition duration-150 ease-in-out', darkMode ? 'text-gray-300 hover:bg-gray-700 hover:text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900']"
                                >
                                    <PhosphorIcon v-if="!showingNavigationDropdown" name="menu" :size="24" weight="regular" />
                                    <PhosphorIcon v-else name="x" :size="24" weight="regular" />
                                </button>

                                <!-- Logo (only when not student or on mobile) -->
                                <div v-if="!isStudent" class="flex items-center">
                                    <Link :href="route('dashboard')" class="flex items-center">
                                        <div :class="['w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold text-lg mr-3', darkMode ? 'bg-green-500' : 'bg-green-600']">
                                            UTM
                                        </div>
                                        <div>
                                            <h1 :class="['text-lg font-display font-bold', darkMode ? 'text-gray-100' : 'text-gray-900']">
                                                {{ $page.props.auth.user.email.endsWith('@admin.utmetropolitana.edu.mx') ? 'Portal Administrativo' : ($page.props.auth.user.email.endsWith('@utmetropolitana.edu.mx') && !$page.props.auth.user.email.includes('@alumno.') ? 'Portal Docente' : 'Portal UTM') }}
                                            </h1>
                                        </div>
                                    </Link>
                                </div>
                            </div>

                            <!-- Right side - User dropdown -->
                            <div class="flex items-center">
                                <div class="relative">
                                    <Dropdown align="right" width="48">
                                        <template #trigger>
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium transition duration-150 ease-in-out focus:outline-none text-gray-700 hover:bg-green-50 hover:text-green-700"
                                            >
                                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-white font-semibold mr-2 bg-green-600">
                                                    {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                                                </div>
                                                <span class="hidden sm:inline">{{ $page.props.auth.user.name }}</span>
                                                <PhosphorIcon name="chevron-down" :size="16" weight="regular" class="ml-2" />
                                            </button>
                                        </template>

                                        <template #content>
                                            <div class="px-4 py-3 border-b border-gray-200">
                                                <p class="text-sm font-medium text-gray-900">
                                                    {{ $page.props.auth.user.name }}
                                                </p>
                                            </div>
                                            
                                            <DropdownLink :href="route('profile.edit')">
                                                Mi Perfil
                                            </DropdownLink>
                                            <DropdownLink
                                                :href="route('logout')"
                                                method="post"
                                                as="button"
                                            >
                                                Cerrar Sesión
                                            </DropdownLink>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                        </div>
                    </div>
                </nav>

                <!-- Page Content -->
                <main :class="['min-h-screen', darkMode ? 'bg-gray-900' : 'bg-gray-50']">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>