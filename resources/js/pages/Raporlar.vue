<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Bar, Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement } from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement);

const props = defineProps({
    stats: Object,
    consultantStats: Object,
    consultantLabels: Object,
    workItems: Array,
    consultants: Array
});

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');
const selectedConsultant = ref('all');
const selectedState = ref('dated');
const search = ref('');
const currentPage = ref(1);
const itemsPerPage = 25;

const filteredStats = computed(() => {
    if (selectedConsultant.value === 'all') return props.stats;

    const s = props.consultantStats[selectedConsultant.value] || { overdue: 0, crit: 0, soon: 0, ok: 0, missing: 0 };
    return {
        overdue: s.overdue,
        crit: s.crit,
        soon: s.soon,
        ok: s.ok,
        missing: s.missing,
    };
});

const doughnutData = computed(() => ({
        labels: ['Gecikmiş', 'Kritik', 'Yaklaşan', 'Veri Yok'],
        datasets: [{
            data: [filteredStats.value.overdue, filteredStats.value.crit, filteredStats.value.soon, filteredStats.value.missing],
            backgroundColor: ['#e11d48', '#ea580c', '#d97706', '#64748b'],
        borderWidth: 0
    }]
}));

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
    cutout: '70%'
};

const barData = computed(() => {
    const labels = Object.keys(props.consultantStats);
    return {
        labels: labels.map(id => props.consultantLabels[id] || 'Atanmamış'),
        datasets: [
            { label: 'Gecikmiş', data: labels.map(l => props.consultantStats[l].overdue), backgroundColor: '#e11d48' },
            { label: 'Kritik', data: labels.map(l => props.consultantStats[l].crit), backgroundColor: '#ea580c' },
            { label: 'Yaklaşan', data: labels.map(l => props.consultantStats[l].soon), backgroundColor: '#d97706' }
        ]
    };
});

const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'top' } },
    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } } }
};

// DÜZELTİLDİ: URL '/api/reports/pdf' yerine '/api/export-pdf' olarak değiştirildi
const downloadPdf = () => {
    const url = new URL('/api/export-pdf', window.location.origin);
    url.searchParams.append('consultant', selectedConsultant.value);
    window.location.href = url.toString();
};

const filteredWork = computed(() => {
    const query = search.value.toLocaleLowerCase('tr');
    return (props.workItems || []).filter((item) => {
        if (selectedConsultant.value !== 'all' && String(item.consultant_user_id || '') !== selectedConsultant.value) return false;
        if (selectedState.value === 'dated' && !['overdue', 'crit', 'soon'].includes(item.state)) return false;
        if (!['dated', 'all'].includes(selectedState.value) && item.state !== selectedState.value) return false;
        if (!query) return true;
        const firm = String(item.firm_name || '').toLocaleLowerCase('tr');
        const topic = String(item.topic || '').toLocaleLowerCase('tr');
        return firm.includes(query) || topic.includes(query);
    });
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredWork.value.length / itemsPerPage)));
const paginatedWork = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredWork.value.slice(start, start + itemsPerPage);
});

watch([selectedConsultant, selectedState, search], () => {
    currentPage.value = 1;
});

const statusClass = (state) => {
    if (state === 'missing') return 'bg-red-100 text-red-800 border-red-300 dark:bg-red-900/40 dark:text-red-300';
    if (state === 'overdue') return 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-400';
    if (state === 'crit') return 'bg-orange-100 text-orange-700 border-orange-200 dark:bg-orange-900/30 dark:text-orange-400';
    return 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400';
};

const kalanSure = (item) => {
    if (item.state === 'missing') return 'Veri girilmemiş';
    return item.days === null || item.days === undefined ? 'Belirsiz' : `${item.days} gün`;
};
</script>

<template>
    <Head title="Analiz ve Raporlar" />

    <div class="w-full space-y-6">
        
        <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Görsel Raporlama</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-1 text-xs">Grafikler özeti gösterir. Listenin tamamı aşağıdadır.</p>
            </div>
            
            <div class="flex flex-col sm:flex-row items-center gap-3">
                <div v-if="isAdmin" class="flex items-center bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 shadow-sm w-full sm:w-auto">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 mr-2 whitespace-nowrap">Danışman:</span>
                    <select v-model="selectedConsultant" class="w-full bg-transparent border-none text-sm font-medium text-indigo-600 dark:text-indigo-400 outline-none cursor-pointer">
                        <option value="all" class="dark:bg-gray-800">Tümü (Hepsi)</option>
                        <option v-for="c in consultants" :key="c.id" :value="String(c.id)" class="dark:bg-gray-800">{{ c.name }}</option>
                    </select>
                </div>

                <button @click="downloadPdf" class="bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition flex items-center space-x-2 w-full sm:w-auto justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>PDF İndir</span>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-[#1e293b] p-6 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 lg:col-span-1">
                <h2 class="text-lg font-bold text-slate-800 dark:text-white mb-4">
                    Genel Durum 
                    <span v-if="selectedConsultant !== 'all'" class="text-sm text-indigo-500 font-medium">({{ consultants.find(c => String(c.id) === selectedConsultant)?.name }})</span>
                </h2>
                <div class="h-64 relative">
                    <Doughnut :data="doughnutData" :options="doughnutOptions" />
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-3xl font-extrabold text-slate-800 dark:text-white">
                            {{ filteredStats.overdue + filteredStats.crit + filteredStats.soon + filteredStats.missing }}
                        </span>
                        <span class="text-[10px] text-slate-500 uppercase tracking-widest">Açık İş</span>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-[#1e293b] p-6 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 lg:col-span-2">
                <h2 class="text-lg font-bold text-slate-800 dark:text-white mb-4">Danışman Bazlı İş Yükü</h2>
                <div class="h-64 relative">
                    <Bar :data="barData" :options="barOptions" />
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-[#1e293b] rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-800 dark:text-white">Tüm işler</h2>
                    <p class="mt-0.5 text-xs text-slate-500">{{ filteredWork.length }} kayıt. Varsayılan liste tarihi belli işlerdir.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <select v-model="selectedState" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                        <option value="dated">Tarihi belli işler</option>
                        <option value="overdue">Gecikmiş</option>
                        <option value="crit">Kritik</option>
                        <option value="soon">Yaklaşan</option>
                        <option value="missing">Veri yok</option>
                        <option value="all">Hepsi</option>
                    </select>
                    <input v-model="search" type="search" placeholder="Tesis veya konu ara..." class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider font-semibold">
                            <th class="px-5 py-3">Tesis</th>
                            <th v-if="isAdmin" class="px-5 py-3">Danışman</th>
                            <th class="px-5 py-3">Konu</th>
                            <th class="px-5 py-3">Kalan süre</th>
                            <th class="px-5 py-3">Durum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="item in paginatedWork" :key="`${item.firm_id}-${item.topic}-${item.state}`" class="text-xs hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                            <td class="px-5 py-3 font-semibold text-slate-800 dark:text-slate-200">
                                <Link :href="`/firma/${item.firm_id}`" class="hover:text-indigo-600 dark:hover:text-indigo-400">{{ item.firm_name }}</Link>
                            </td>
                            <td v-if="isAdmin" class="px-5 py-3 text-slate-600 dark:text-slate-400">{{ item.consultant }}</td>
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ item.topic }}</td>
                            <td class="px-5 py-3 font-semibold text-slate-700 dark:text-slate-200">{{ kalanSure(item) }}</td>
                            <td class="px-5 py-3">
                                <span :class="['rounded-full border px-2 py-0.5 text-[10px] font-bold', statusClass(item.state)]">{{ item.state_label }}</span>
                            </td>
                        </tr>
                        <tr v-if="filteredWork.length === 0">
                            <td :colspan="isAdmin ? 5 : 4" class="px-5 py-8 text-center text-slate-500">Seçilen filtreye uygun iş yok.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="totalPages > 1" class="flex items-center justify-between border-t border-slate-200 px-5 py-3 dark:border-slate-700">
                <span class="text-xs text-slate-500">Sayfa {{ currentPage }} / {{ totalPages }}</span>
                <div class="flex gap-2">
                    <button type="button" @click="currentPage--" :disabled="currentPage === 1" class="rounded border border-slate-300 px-3 py-1.5 text-xs disabled:opacity-50 dark:border-slate-600">Önceki</button>
                    <button type="button" @click="currentPage++" :disabled="currentPage === totalPages" class="rounded border border-slate-300 px-3 py-1.5 text-xs disabled:opacity-50 dark:border-slate-600">Sonraki</button>
                </div>
            </div>
        </div>
    </div>
</template>
