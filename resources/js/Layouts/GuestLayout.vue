<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-green-50 to-green-100 flex items-center justify-center p-4">
        <div class="w-full max-w-lg">
            <!-- Logo Section -->
            <div class="text-center mb-8">
                <Link href="/" class="inline-block">
                    <div class="w-20 h-20 bg-green-600 rounded-2xl flex items-center justify-center text-white font-bold text-2xl mx-auto mb-4 shadow-lg">
                        UTM
                    </div>
                    <h1 class="text-2xl font-bold text-green-800">Universidad Técnica Metropolitana</h1>
                    <p class="text-green-600 mt-2">Plataforma Educativa</p>
                </Link>
            </div>

            <!-- Form Container -->
            <div class="bg-white rounded-2xl shadow-xl p-8 border border-green-100">
                <slot />
            </div>

            <!-- Footer -->
            <div class="text-center mt-6">
                <p class="text-sm text-green-600">
                    Desarrollado por Angel Noh y Mauricio Chale - 4E
                </p>
            </div>
        </div>
    </div>
</template>
