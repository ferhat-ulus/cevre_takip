<script setup>
import { ref, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const username = ref('');
const password = ref('');
const error = ref('');
const isLoading = ref(false);
const isDark = ref(false);

onMounted(() => {
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        isDark.value = true;
        document.documentElement.classList.add('dark');
    } else {
        isDark.value = false;
        document.documentElement.classList.remove('dark');
    }
});

const toggleTheme = () => {
    isDark.value = !isDark.value;
    if (isDark.value) {
        document.documentElement.classList.add('dark');
        localStorage.theme = 'dark';
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.theme = 'light';
    }
};

const handleLogin = async () => {
    isLoading.value = true;
    error.value = '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        const response = await fetch('/api/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                username: username.value,
                password: password.value
            })
        });

        const data = await response.json();

        if (response.ok) {
            router.visit('/');
        } else {
            error.value = data.error || data.message || 'Giriş başarısız. Bilgilerinizi kontrol edin.';
        }
    } catch (err) {
        error.value = 'Sunucuya bağlanılamadı. Lütfen tekrar deneyin.';
    } finally {
        isLoading.value = false;
    }
};
</script>

<template>
    <Head title="Giriş Yap" />

    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 transition-colors duration-300 p-4 relative">
        
        <button @click="toggleTheme" class="absolute top-6 right-6 p-2 rounded-full bg-gray-200 dark:bg-gray-800 text-xl shadow-md hover:bg-gray-300 dark:hover:bg-gray-700 transition">
            <span v-if="isDark">🌞</span>
            <span v-else>🌙</span>
        </button>

        <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 border border-gray-100 dark:border-gray-700">
            
            <div class="text-center mb-8 flex flex-col items-center">
                <!-- YENİ LOGO ALANI -->
                <img src="/logo.png" alt="Logo" class="h-20 mb-4 object-contain">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Çevre Takip</h1>
                <p class="text-gray-500 dark:text-gray-400 text-sm">Sisteme giriş yapmak için bilgilerinizi girin</p>
            </div>

            <div v-if="error" class="mb-4 p-3 bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-200 rounded-lg text-sm text-center">
                {{ error }}
            </div>

            <form @submit.prevent="handleLogin" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kullanıcı Adı</label>
                    <input v-model="username" type="text" required class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 outline-none transition" placeholder="admin">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Şifre</label>
                    <input v-model="password" type="password" required class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 outline-none transition" placeholder="••••••••">
                </div>
                <button type="submit" :disabled="isLoading" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 rounded-lg transition duration-200 disabled:opacity-50 flex justify-center items-center">
                    <span v-if="isLoading">Giriş Yapılıyor...</span>
                    <span v-else>Giriş Yap</span>
                </button>
            </form>
        </div>
    </div>
</template>