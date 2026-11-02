<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Bar, Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement } from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement);

const props = defineProps({
    stats: Object,
    consultantStats: Object,
    riskyFirms: Array,
    consultants: Array
});

const selectedConsultant = ref('all');
const selectedFirmDetails = ref(null);

const filteredRiskyFirms = computed(() => {
    if (selectedConsultant.value === 'all') return props.riskyFirms;
    return props.riskyFirms.filter(f => f.consultant === selectedConsultant.value);
});

const filteredStats = computed(() => {
    if (selectedConsultant.value === 'all') return props.stats;
    
    const s = props.consultantStats[selectedConsultant.value] || { overdue: 0, crit: 0, soon: 0, ok: 0 };
    return {
        overdue: s.overdue,
        crit: s.crit,
        soon: s.soon,
        ok: s.ok
    };
});

const doughnutData = computed(() => ({
    labels: ['Gecikmiş', 'Kritik', 'Yaklaşan', 'Güncel'],
    datasets: [{
        data: [filteredStats.value.overdue, filteredStats.value.crit, filteredStats.value.soon, filteredStats.value.ok],
        backgroundColor: ['#e11d48', '#ea580c', '#d97706', '#059669'],
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
        labels: labels,
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

const showFirmDetails = (firm) => {
    selectedFirmDetails.value = firm;
};
</script>

<template>
    <Head title="Analiz ve Raporlar" />

    <div class="max-w-7xl mx-auto space-y-6">
        
        <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Görsel Raporlama</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-1 text-xs">Sistemdeki genel durumu analiz edin ve rapor indirin.</p>
            </div>
            
            <div class="flex flex-col sm:flex-row items-center gap-3">
                <div class="flex items-center bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 shadow-sm w-full sm:w-auto">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 mr-2 whitespace-nowrap">Danışman:</span>
                    <select v-model="selectedConsultant" class="w-full bg-transparent border-none text-sm font-medium text-indigo-600 dark:text-indigo-400 outline-none cursor-pointer">
                        <option value="all" class="dark:bg-gray-800">Tümü (Hepsi)</option>
                        <option v-for="c in consultants" :key="c" :value="c" class="dark:bg-gray-800">{{ c }}</option>
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
                    <span v-if="selectedConsultant !== 'all'" class="text-sm text-indigo-500 font-medium">({{ selectedConsultant }})</span>
                </h2>
                <div class="h-64 relative">
                    <Doughnut :data="doughnutData" :options="doughnutOptions" />
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-3xl font-extrabold text-slate-800 dark:text-white">
                            {{ filteredStats.overdue + filteredStats.crit + filteredStats.soon + filteredStats.ok }}
                        </span>
                        <span class="text-[10px] text-slate-500 uppercase tracking-widest">Toplam İş</span>
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

        <div class="bg-white dark:bg-[#1e293b] rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden mt-6">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center">
                <h2 class="text-lg font-bold text-slate-800 dark:text-white">En Riskli 10 Tesis</h2>
                <span class="text-xs font-semibold bg-rose-100 text-rose-700 px-2 py-1 rounded-md dark:bg-rose-900/30 dark:text-rose-400">Açık İş Sayısına Göre</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider font-semibold">
                            <th class="px-5 py-3">Tesis Adı</th>
                            <th class="px-5 py-3">Danışman</th>
                            <th class="px-5 py-3 text-center">Açık İş Sayısı</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="(firm, index) in filteredRiskyFirms" :key="index" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors text-xs">
                            <td class="px-5 py-3 font-bold text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer" @click="showFirmDetails(firm)">
                                {{ firm.name }}
                            </td>
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-400">{{ firm.consultant }}</td>
                            <td class="px-5 py-3 text-center">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-rose-100 text-rose-700 font-bold dark:bg-rose-900/50 dark:text-rose-400">
                                    {{ firm.risky_count }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="filteredRiskyFirms.length === 0">
                            <td colspan="3" class="px-5 py-8 text-center text-slate-500">Seçili kritere uygun riskli tesis bulunmuyor.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tesis Detay Modalı -->
        <div v-if="selectedFirmDetails" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-[#1e293b] rounded-xl p-5 w-full max-w-lg shadow-xl border border-slate-200 dark:border-slate-700 text-sm">
                <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-700 pb-3 mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800 dark:text-white">{{ selectedFirmDetails.name }}</h3>
                        <p class="text-xs text-slate-500">Danışman: {{ selectedFirmDetails.consultant }}</p>
                    </div>
                    <button @click="selectedFirmDetails = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <h4 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Uyarı Veren İşler ({{ selectedFirmDetails.risky_count }})</h4>
                <div class="space-y-2 max-h-60 overflow-y-auto pr-2">
                    <div v-for="(issue, idx) in selectedFirmDetails.issues" :key="idx" class="p-2 bg-slate-50 dark:bg-slate-800 rounded border border-slate-200 dark:border-slate-700 flex justify-between items-center">
                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ issue.topic }}</span>
                        <div class="text-right">
                            <span :class="['px-2 py-0.5 rounded text-[10px] font-bold border', 
                                issue.color === 'overdue' ? 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-400' : 
                                issue.color === 'crit' ? 'bg-orange-100 text-orange-700 border-orange-200 dark:bg-orange-900/30 dark:text-orange-400' : 
                                'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400']">
                                {{ issue.state }}
                            </span>
                            <div class="text-[10px] text-slate-500 mt-1">{{ issue.days }} gün ({{ issue.due }})</div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 text-right">
                    <button @click="selectedFirmDetails = null" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition">Kapat</button>
                </div>
            </div>
        </div>
    </div>
</template>