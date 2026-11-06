<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    backups: Array
});

const isProcessing = ref(false);
const message = ref('');
const errorMsg = ref('');

const createBackup = () => {
    isProcessing.value = true;
    message.value = '';
    errorMsg.value = '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    fetch('/api/backups/run', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            message.value = data.message;
            setTimeout(() => router.reload(), 1500);
        } else {
            errorMsg.value = data.error;
        }
    })
    .catch(() => errorMsg.value = 'Sunucu hatası oluştu.')
    .finally(() => isProcessing.value = false);
};

const deleteBackup = (filename) => {
    if (!confirm('Bu yedeği silmek istediğinize emin misiniz?')) return;
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    fetch(`/api/backups/${filename}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
    })
    .then(res => {
        if (res.ok) router.reload();
        else alert('Silinemedi.');
    });
};
</script>

<template>
    <Head title="Yedek Yönetimi" />

    <div class="w-full space-y-6 pb-20">
        
        <div class="flex justify-between items-end">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Yedek Yönetimi</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-1 text-xs">Sisteminizin günlük otomatik yedeklerini görüntüleyin ve indirin.</p>
            </div>
            
            <button @click="createBackup" :disabled="isProcessing" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition disabled:opacity-50 flex items-center space-x-2">
                <svg v-if="isProcessing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                <span>{{ isProcessing ? 'Yedek Alınıyor...' : 'Şimdi Yedek Al' }}</span>
            </button>
        </div>

        <div v-if="message" class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg text-sm font-bold flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ message }}
        </div>
        <div v-if="errorMsg" class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-lg text-sm font-bold flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            {{ errorMsg }}
        </div>

        <div class="bg-white dark:bg-[#1e293b] rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider font-semibold">
                            <th class="px-4 py-3">Dosya Adı</th>
                            <th class="px-4 py-3">Tarih</th>
                            <th class="px-4 py-3">Boyut</th>
                            <th class="px-4 py-3 text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="backup in backups" :key="backup.name" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors text-xs">
                            <td class="px-4 py-3 font-bold text-slate-800 dark:text-slate-200 font-mono">{{ backup.name }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ backup.date }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ backup.size }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <a :href="`/api/backups/download/${backup.name}`" class="text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 p-1.5 rounded-md transition-colors" title="Bilgisayara İndir">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    </a>
                                    <button @click="deleteBackup(backup.name)" class="text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 p-1.5 rounded-md transition-colors" title="Sil">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="backups.length === 0">
                            <td colspan="4" class="px-4 py-8 text-center text-slate-500">Henüz alınmış bir yedek bulunmuyor.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>