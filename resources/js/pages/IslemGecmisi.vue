<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    logs: Array
});

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('tr-TR', { 
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    }).format(date);
};

const getActionBadge = (action) => {
    switch (action) {
        case 'settings_updated': return 'bg-amber-100 text-amber-700 border-amber-200';
        case 'user_created': return 'bg-emerald-100 text-emerald-700 border-emerald-200';
        case 'user_updated': return 'bg-blue-100 text-blue-700 border-blue-200';
        case 'excel_import': return 'bg-indigo-100 text-indigo-700 border-indigo-200';
        case 'database_wipe': return 'bg-rose-100 text-rose-700 border-rose-200';
        case 'visit_created': return 'bg-teal-100 text-teal-700 border-teal-200';
        case 'visit_deleted': return 'bg-orange-100 text-orange-700 border-orange-200';
        default: return 'bg-slate-100 text-slate-700 border-slate-200';
    }
};
</script>

<template>
    <Head title="İşlem Geçmişi" />

    <div class="max-w-7xl mx-auto space-y-6 pb-20">
        <div class="flex justify-between items-end">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Sistem İşlem Geçmişi</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-1 text-xs">Sistemde yapılan kritik değişikliklerin ve işlemlerin kaydı.</p>
            </div>
        </div>

        <div class="bg-white dark:bg-[#1e293b] rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider font-semibold">
                            <th class="px-4 py-3">Tarih / Saat</th>
                            <th class="px-4 py-3">Kullanıcı</th>
                            <th class="px-4 py-3">İşlem Tipi</th>
                            <th class="px-4 py-3">Açıklama</th>
                            <th class="px-4 py-3 text-right">IP Adresi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="log in logs" :key="log.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors text-xs">
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                {{ formatDate(log.created_at) }}
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-200">
                                {{ log.user?.name || 'Sistem / Bilinmiyor' }}
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['px-2 py-1 rounded-md text-[10px] font-bold border', getActionBadge(log.action)]">
                                    {{ log.action.toUpperCase() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                {{ log.description }}
                            </td>
                            <td class="px-4 py-3 text-right text-slate-500 font-mono text-[10px]">
                                {{ log.ip_address || '-' }}
                            </td>
                        </tr>
                        <tr v-if="logs.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500">Henüz kaydedilmiş bir işlem bulunmuyor.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>