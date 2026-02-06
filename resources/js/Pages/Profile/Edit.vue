<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode.js';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

// Usar el composable de modo oscuro
const { darkMode } = useDarkMode();
</script>

<template>
    <Head title="Perfil" />

    <AuthenticatedLayout>
        <template #header>
            <h2 :class="['text-xl font-semibold leading-tight', darkMode ? 'text-gray-200' : 'text-gray-800']">
                Perfil
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div :class="['p-4 shadow sm:rounded-lg sm:p-8', darkMode ? 'bg-gray-800' : 'bg-white']">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div :class="['p-4 shadow sm:rounded-lg sm:p-8', darkMode ? 'bg-gray-800' : 'bg-white']">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
