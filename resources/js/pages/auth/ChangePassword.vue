<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const password = ref('');
const passwordConfirmation = ref('');
const error = ref('');
const isSaving = ref(false);

const save = async () => {
    isSaving.value = true;
    error.value = '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        const response = await fetch('/api/sifre-degistir', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                password: password.value,
                password_confirmation: passwordConfirmation.value,
            }),
        });
        const data = await response.json();

        if (response.ok) {
            router.visit(data.redirect);
        } else {
            error.value = data.message || data.error || 'Şifre güncellenemedi.';
        }
    } catch {
        error.value = 'Sunucuya bağlanılamadı. Lütfen tekrar deneyin.';
    } finally {
        isSaving.value = false;
    }
};
</script>

<template>
    <Head title="Şifre Değiştir" />

    <div class="flex min-h-screen items-center justify-center bg-gray-50 p-4 dark:bg-gray-900">
        <div class="w-full max-w-md rounded-2xl border border-gray-100 bg-white p-8 shadow-xl dark:border-gray-700 dark:bg-gray-800">
            <div class="mb-8 text-center">
                <img src="/logo.png" alt="Ulus Çevre" class="mx-auto mb-4 h-20 object-contain">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Şifrenizi değiştirin</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Güvenliğiniz için devam etmeden önce yeni bir şifre belirleyin.</p>
            </div>

            <p v-if="error" class="mb-4 rounded-lg bg-red-100 p-3 text-center text-sm text-red-700 dark:bg-red-900/50 dark:text-red-200">{{ error }}</p>

            <form class="space-y-5" @submit.prevent="save">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Yeni şifre</label>
                    <input v-model="password" type="password" minlength="8" required autocomplete="new-password" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-2 text-gray-900 outline-none transition focus:ring-2 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="En az 8 karakter">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Yeni şifre tekrar</label>
                    <input v-model="passwordConfirmation" type="password" minlength="8" required autocomplete="new-password" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-2 text-gray-900 outline-none transition focus:ring-2 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="Şifrenizi tekrar yazın">
                </div>
                <button type="submit" :disabled="isSaving" class="flex w-full items-center justify-center rounded-lg bg-brand-600 py-2.5 font-semibold text-white transition hover:bg-brand-700 disabled:opacity-50">
                    {{ isSaving ? 'Kaydediliyor...' : 'Şifreyi Kaydet ve Devam Et' }}
                </button>
            </form>
        </div>
    </div>
</template>
