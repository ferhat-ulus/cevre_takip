<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';

const isDownloadingPdf = ref(false);
const isDownloadingExcel = ref(false);

const downloadPdf = () => {
    isDownloadingPdf.value = true;
    window.location.href = '/api/export-pdf';
    setTimeout(() => { isDownloadingPdf.value = false; }, 3000);
};

const downloadExcel = () => {
    isDownloadingExcel.value = true;
    window.location.href = '/api/export-excel';
    setTimeout(() => { isDownloadingExcel.value = false; }, 3000);
};
</script>

<template>
    <Head title="Rapor İndir" />

    <div class="max-w-7xl mx-auto space-y-6 pb-20">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Rapor İndir</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1 text-xs">Sistemdeki güncel verileri PDF veya Excel formatında indirin.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- PDF İNDİRME KARTI -->
            <div class="bg-white dark:bg-[#1e293b] p-6 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 flex flex-col items-center text-center">
                <div class="w-16 h-16 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                </div>
                <h2 class="text-lg font-bold text-slate-800 dark:text-white mb-2">PDF Raporu İndir</h2>
                <p class="text-xs text-slate-500 mb-6 flex-1">Tesislerin mevzuat durumlarını, öncelikli işleri ve risk analizlerini içeren resmi PDF raporu.</p>
                <button @click="downloadPdf" :disabled="isDownloadingPdf" class="w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-semibold transition disabled:opacity-50">
                    {{ isDownloadingPdf ? 'Hazırlanıyor...' : 'PDF Olarak İndir' }}
                </button>
            </div>

            <!-- EXCEL İNDİRME KARTI -->
            <div class="bg-white dark:bg-[#1e293b] p-6 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 flex flex-col items-center text-center">
                <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <h2 class="text-lg font-bold text-slate-800 dark:text-white mb-2">Excel Raporu İndir</h2>
                <p class="text-xs text-slate-500 mb-6 flex-1">Tüm tesislerin mevzuat detaylarını içeren, üzerinde çalışabileceğiniz Excel tablosu.</p>
                <button @click="downloadExcel" :disabled="isDownloadingExcel" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-semibold transition disabled:opacity-50">
                    {{ isDownloadingExcel ? 'Hazırlanıyor...' : 'Excel Olarak İndir' }}
                </button>
            </div>

        </div>
    </div>
</template>