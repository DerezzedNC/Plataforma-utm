<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Iniciar sesión - UTM" />

        <!-- Título de la página -->
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-green-800">Iniciar Sesión</h2>
            <p class="text-gray-600 mt-2">Ingresa tus credenciales institucionales</p>
        </div>

        <div v-if="status" class="mb-6 p-4 text-sm font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div>
                <InputLabel for="email" value="Correo institucional" class="text-xl font-bold text-green-900 mb-3 block" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-2 block w-full px-4 py-4 text-lg border-2 border-gray-300 rounded-xl focus:border-green-500 focus:ring-green-500 transition-colors duration-200"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="ejemplo@utm.mx"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Contraseña" class="text-xl font-bold text-green-900 mb-3 block" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-2 block w-full px-4 py-4 text-lg border-2 border-gray-300 rounded-xl focus:border-green-500 focus:ring-green-500 transition-colors duration-200"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex items-center">
                <label class="flex items-center cursor-pointer">
                    <Checkbox 
                        name="remember" 
                        v-model:checked="form.remember" 
                        class="w-5 h-5 text-green-600 border-2 border-gray-300 rounded focus:ring-green-500" 
                    />
                    <span class="ml-3 text-lg text-gray-600">Recuérdame</span>
                </label>
            </div>

            <div class="space-y-4">
                <PrimaryButton
                    class="w-full py-4 px-6 text-lg font-semibold bg-green-600 hover:bg-green-700 rounded-xl transition-colors duration-200 shadow-lg hover:shadow-xl"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">Iniciando sesión...</span>
                    <span v-else>INICIAR SESIÓN</span>
                </PrimaryButton>

                <div class="text-center">
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-lg text-green-600 hover:text-green-800 underline transition-colors duration-200"
                    >
                        ¿Se te olvidó tu contraseña?
                    </Link>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>
