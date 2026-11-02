<script setup>
import { ref, computed } from 'vue';
import { Link, Head } from '@inertiajs/vue3'; // Head import edildi

const props = defineProps({
    firms: Array,
    consultants: Array,
    isAdmin: Boolean
});

const search = ref('');
const selectedConsultant = ref('all');
const selectedState = ref('all'); 

const currentPage = ref(1);
const itemsPerPage = ref(20); 

const editingNoteId = ref(null);
const editNoteText = ref('');

const filteredFirms = computed(() => {
    let result = props.firms;
    if (selectedConsultant.value !== 'all') result = result.filter(f => f.consultant === selectedConsultant.value);
    if (selectedState.value !== 'all') result = result.filter(f => f.overallState === selectedState.value);
    if (search.value) {
        const q = search.value.toLowerCase();
        result = result.filter(f => f.name.toLowerCase().includes(q) || f.consultant.toLowerCase().includes(q) || f.sector.toLowerCase().includes(q));
    }
    return result;
});

const totalPages = computed(() => itemsPerPage.value === 'all' ? 1 : Math.ceil(filteredFirms.value.length / itemsPerPage.value));
const paginatedFirms = computed(() => {
    if (itemsPerPage.value === 'all') return filteredFirms.value;
    const start = (currentPage.value - 1) * itemsPerPage.value;
    return filteredFirms.value.slice(start, start + parseInt(itemsPerPage.value));
});

const resetPage = () => { currentPage.value = 1; };

const startEditing = (firm) => { editingNoteId.value = firm.id; editNoteText.value = firm.note || ''; };
const saveNote = async (firm) => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    try {
        const response = await fetch(`/api/firms/${firm.id}/note`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ note: editNoteText.value })
        });
        if (response.ok) { firm.note = editNoteText.value; editingNoteId.value = null; } else { alert('Not kaydedilemedi.'); }
    } catch (err) { alert('Sunucu hatası.'); }
};

const getStatusColor = (state) => {
    if (state === 'overdue') return 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20';
    if (state === 'crit') return 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-500/10 dark:text-orange-400 dark:border-orange-500/20';
    if (state === 'soon') return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20';
    if (state === 'surec') return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/20';
    if (state === 'muaf') return 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-700/50 dark:text-slate-400 dark:border-slate-600';
    if (state === 'ok') return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20';
    return 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-700/50 dark:text-gray-400 dark:border-gray-600';
};
const getStatusText = (state) => {
    const map = { 'overdue': 'Gecikmiş', 'crit': 'Kritik', 'soon': 'Yaklaşan', 'ok': 'Güncel', 'surec': 'Süreçte', 'missing': 'Veri Yok', 'noperiod': 'Periyot Yok', 'muaf': 'Kapsam Dışı' };
    return map[state] || state;
};
const getDaysColor = (days, state) => {
    if (days === null) return 'text-slate-500';
    if (days < 0) return 'text-rose-600 dark:text-rose-400 font-bold';
    if (state === 'crit') return 'text-orange-600 dark:text-orange-400 font-bold';
    if (state === 'soon') return 'text-amber-600 dark:text-amber-400 font-bold';
    return 'text-slate-600 dark:text-slate-400';
};
const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    return new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date);
};
</script>

<template>
    <Head title="Emisyon Süreçleri" /> <!-- BAŞLIK EKLENDİ -->

    <div class="max-w-7xl mx-auto space-y-4">
        <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Emisyon Süreçleri</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-1 text-xs">Tesislerin emisyon ölçüm ve rapor durumları.</p>
            </div>
            <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
                <div v-if="isAdmin" class="flex items-center w-full sm:w-auto bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 shadow-sm">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 mr-2 whitespace-nowrap">Danışman:</span>
                    <select v-model="selectedConsultant" @change="resetPage" class="w-full bg-transparent border-none text-sm font-medium text-indigo-600 dark:text-indigo-400 outline-none cursor-pointer">
                        <option value="all" class="dark:bg-gray-800">Tümü (Hepsi)</option>
                        <option v-for="c in consultants" :key="c" :value="c" class="dark:bg-gray-800">{{ c }}</option>
                    </select>
                </div>
                <div class="relative w-full sm:w-64">
                    <svg class="w-4 h-4 absolute left-3 top-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input v-model="search" @input="resetPage" type="text" placeholder="Tesis, danışman veya sektör ara..." class="w-full pl-9 pr-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button @click="selectedState = 'all'; resetPage()" :class="['px-4 py-2 rounded-lg text-sm font-semibold transition border', selectedState === 'all' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50 dark:bg-[#1e293b] dark:text-slate-300 dark:border-slate-700']">Tümü ({{ firms.length }})</button>
            <button @click="selectedState = 'overdue'; resetPage()" :class="['px-4 py-2 rounded-lg text-sm font-semibold transition border', selectedState === 'overdue' ? 'bg-rose-600 text-white border-rose-600 shadow-md' : 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100 dark:bg-rose-900/30 dark:text-rose-400 dark:border-rose-800']">🔴 Gecikmiş</button>
            <button @click="selectedState = 'crit'; resetPage()" :class="['px-4 py-2 rounded-lg text-sm font-semibold transition border', selectedState === 'crit' ? 'bg-orange-500 text-white border-orange-500 shadow-md' : 'bg-orange-50 text-orange-700 border-orange-200 hover:bg-orange-100 dark:bg-orange-900/30 dark:text-orange-400 dark:border-orange-800']">🟠 Kritik</button>
            <button @click="selectedState = 'soon'; resetPage()" :class="['px-4 py-2 rounded-lg text-sm font-semibold transition border', selectedState === 'soon' ? 'bg-amber-500 text-white border-amber-500 shadow-md' : 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800']">🟡 Yaklaşan</button>
        </div>

        <div class="bg-white dark:bg-[#1e293b] rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden flex flex-col">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider font-semibold">
                            <th class="px-4 py-3">Tesis Adı</th>
                            <th class="px-4 py-3">Danışman</th>
                            <th class="px-4 py-3">Emisyon Ölçümü</th>
                            <th class="px-4 py-3">Emisyon Raporu</th>
                            <th class="px-4 py-3 w-64">Notlar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="firm in paginatedFirms" :key="firm.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors text-xs">
                            <td class="px-4 py-3">
                                <Link :href="`/firma/${firm.id}`" class="font-bold text-indigo-600 dark:text-indigo-400 hover:underline">{{ firm.name }}</Link>
                                <div class="text-[10px] text-slate-500">{{ firm.sector }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 font-medium">{{ firm.consultant }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center space-x-2">
                                    <span :class="['px-2 py-0.5 rounded-full text-[10px] font-bold border', getStatusColor(firm.emisyonOlcum.state)]">{{ getStatusText(firm.emisyonOlcum.state) }}</span>
                                    <span v-if="firm.emisyonOlcum.due" class="text-[11px]">
                                        {{ formatDate(firm.emisyonOlcum.due) }}
                                        <span v-if="firm.emisyonOlcum.days !== null" :class="getDaysColor(firm.emisyonOlcum.days, firm.emisyonOlcum.state)">({{ firm.emisyonOlcum.days }} gün)</span>
                                    </span>
                                </div>
                                <div v-if="firm.emisyonOlcum.label" class="text-[10px] text-slate-400 mt-1 truncate max-w-[200px]" :title="firm.emisyonOlcum.label">{{ firm.emisyonOlcum.label }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center space-x-2">
                                    <span :class="['px-2 py-0.5 rounded-full text-[10px] font-bold border', getStatusColor(firm.emisyonRapor.state)]">{{ getStatusText(firm.emisyonRapor.state) }}</span>
                                    <span v-if="firm.emisyonRapor.due" class="text-[11px]">
                                        {{ formatDate(firm.emisyonRapor.due) }}
                                        <span v-if="firm.emisyonRapor.days !== null" :class="getDaysColor(firm.emisyonRapor.days, firm.emisyonRapor.state)">({{ firm.emisyonRapor.days }} gün)</span>
                                    </span>
                                </div>
                                <div v-if="firm.emisyonRapor.label" class="text-[10px] text-slate-400 mt-1 truncate max-w-[200px]" :title="firm.emisyonRapor.label">{{ firm.emisyonRapor.label }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="editingNoteId === firm.id" class="flex flex-col space-y-1">
                                    <textarea v-model="editNoteText" rows="2" class="w-full text-xs rounded border border-slate-300 dark:border-slate-600 p-1 dark:bg-slate-700 dark:text-white"></textarea>
                                    <div class="flex space-x-1">
                                        <button @click="saveNote(firm)" class="bg-indigo-600 text-white px-2 py-1 rounded text-[10px]">Kaydet</button>
                                        <button @click="editingNoteId = null" class="bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 px-2 py-1 rounded text-[10px]">İptal</button>
                                    </div>
                                </div>
                                <div v-else class="cursor-pointer group flex items-start space-x-1" @click="startEditing(firm)">
                                    <span v-if="firm.note" class="text-xs text-slate-600 dark:text-slate-400 leading-tight">{{ firm.note }}</span>
                                    <span v-else class="text-xs text-slate-400 italic group-hover:text-indigo-500">Not ekle...</span>
                                    <svg class="w-3 h-3 text-slate-400 opacity-0 group-hover:opacity-100 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row items-center justify-between bg-slate-50/50 dark:bg-slate-800/30 gap-4">
                <div class="flex items-center space-x-2">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Sayfa başına:</span>
                    <select v-model="itemsPerPage" @change="resetPage" class="bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-xs rounded-md py-1 px-2 outline-none cursor-pointer">
                        <option :value="20">20</option><option :value="50">50</option><option :value="100">100</option><option value="all">Tümü</option>
                    </select>
                    <span class="text-xs text-slate-500 dark:text-slate-400 ml-2">Toplam <strong>{{ filteredFirms.length }}</strong> kayıt</span>
                </div>
                <div v-if="itemsPerPage !== 'all' && totalPages > 1" class="flex space-x-2">
                    <button @click="currentPage--" :disabled="currentPage === 1" class="px-3 py-1.5 rounded bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-xs font-medium disabled:opacity-50 hover:bg-slate-50 dark:hover:bg-slate-600">Önceki</button>
                    <div class="flex items-center px-3 text-xs font-medium text-slate-600 dark:text-slate-300">{{ currentPage }} / {{ totalPages }}</div>
                    <button @click="currentPage++" :disabled="currentPage === totalPages" class="px-3 py-1.5 rounded bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-xs font-medium disabled:opacity-50 hover:bg-slate-50 dark:hover:bg-slate-600">Sonraki</button>
                </div>
            </div>
        </div>
    </div>
</template>