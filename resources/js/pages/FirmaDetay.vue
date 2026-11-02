<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    firm: Object,
    evaluations: Object,
    meta: Object,
    canEdit: Boolean
});

const form = ref(JSON.parse(JSON.stringify(props.firm)));
const isSaving = ref(false);

const groupedObligations = computed(() => {
    const groups = {};
    props.meta.obligations.forEach(obl => {
        if (!groups[obl.g]) groups[obl.g] = [];
        groups[obl.g].push(obl);
    });
    return groups;
});

const getStatusColor = (state) => {
    if (state === 'overdue') return 'text-rose-600 dark:text-rose-400';
    if (state === 'crit') return 'text-orange-600 dark:text-orange-400';
    if (state === 'soon') return 'text-amber-600 dark:text-amber-400';
    if (state === 'ok') return 'text-emerald-600 dark:text-emerald-400';
    return 'text-slate-500 dark:text-slate-400';
};

// Tarihi GG.AA.YYYY formatına çevirir
const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    return new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date);
};

const save = async () => {
    if (!props.canEdit) return;
    isSaving.value = true;
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    try {
        const response = await fetch(`/api/firms/${props.firm.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                sector: form.value.sector,
                profile: form.value.profile,
                items: form.value.items
            })
        });

        const data = await response.json();

        if (response.ok) {
            window.location.reload();
        } else {
            alert(data.error || data.message || 'Kaydetme sırasında bir hata oluştu.');
            isSaving.value = false;
        }
    } catch (err) {
        alert('Sunucuya bağlanılamadı. Lütfen tekrar deneyin.');
        isSaving.value = false;
    }
};

const changeItemState = (key, state) => {
    form.value.items[key].s = state;
    // Sadece 'Girilmedi' seçilirse tarihi sil. 'Kapsam Dışı' seçilirse tarihi koru.
    if (state === 'girilmedi') {
        form.value.items[key].d = null;
    }
};
</script>

<template>
    <Head :title="firm.name + ' - Detay'" />

    <div class="max-w-7xl mx-auto space-y-6 pb-20">
        
        <!-- Üst Başlık ve Geri Dön -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center bg-white dark:bg-[#1e293b] p-4 md:p-5 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 gap-4">
            
            <div class="flex items-start md:items-center space-x-3 w-full md:w-auto md:flex-1 min-w-0">
                <Link href="/" class="p-2.5 bg-slate-100 dark:bg-slate-700 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors shrink-0 text-slate-600 dark:text-slate-300 mt-1 md:mt-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </Link>
                
                <div class="min-w-0 flex-1">
                    <h1 class="text-lg md:text-xl font-bold text-slate-900 dark:text-white tracking-tight line-clamp-2 leading-tight break-words">
                        {{ firm.name }}
                    </h1>
                    <div class="flex flex-wrap items-center gap-2 mt-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/20">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            {{ firm.consultant }}
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-700/50 dark:text-slate-300 dark:border-slate-600">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            {{ firm.sector || 'Belirtilmemiş' }}
                        </span>
                    </div>
                </div>
            </div>

            <button v-if="canEdit" @click="save" :disabled="isSaving" class="w-full md:w-auto shrink-0 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition-all disabled:opacity-50 flex items-center justify-center space-x-2">
                <svg v-if="isSaving" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span>{{ isSaving ? 'Kaydediliyor...' : 'Değişiklikleri Kaydet' }}</span>
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            
            <!-- SOL TARAF: Profil Bilgileri -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white dark:bg-[#1e293b] p-5 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-4 border-b pb-2 dark:border-slate-700">Tesis Profili</h3>
                    
                    <div class="space-y-4 text-sm">
                        <div>
                            <label class="block text-xs font-medium mb-1 dark:text-slate-400">Sektör</label>
                            <input type="text" v-model="form.sector" :disabled="!canEdit" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 p-2 dark:text-white text-sm disabled:opacity-60 uppercase">
                        </div>

                        <div>
                            <label class="block text-xs font-medium mb-1 dark:text-slate-400">ÇED Kararı</label>
                            <select v-model="form.profile.cedKarari" :disabled="!canEdit" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 p-2 dark:text-white text-sm disabled:opacity-60">
                                <option value="">Seçiniz...</option>
                                <option value="ÇED KAPSAMDIŞI">ÇED KAPSAMDIŞI</option>
                                <option value="ÇED KAPSAMDIŞI-SINIR">ÇED KAPSAMDIŞI-SINIR</option>
                                <option value="ÇED OLUMLU">ÇED OLUMLU</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1 dark:text-slate-400">ÇED Tarihi</label>
                            <input type="date" v-model="form.profile.cedTarihi" :disabled="!canEdit" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 p-2 dark:text-white text-sm disabled:opacity-60">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1 dark:text-slate-400">Atıksu Kurumu</label>
                            <select v-model="form.profile.atiksuKurum" :disabled="!canEdit" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 p-2 dark:text-white text-sm disabled:opacity-60">
                                <option value="">Seçiniz...</option>
                                <option value="KANALİZASYON İDARESİ">KANALİZASYON İDARESİ</option>
                                <option value="OSB">OSB</option>
                                <option value="ALICI ORTAM">ALICI ORTAM</option>
                                <option value="VİDANJÖR">VİDANJÖR</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1 dark:text-slate-400">İzin Konusu</label>
                            <select v-model="form.profile.izinKonusu" :disabled="!canEdit" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 p-2 dark:text-white text-sm disabled:opacity-60">
                                <option value="">Seçiniz...</option>
                                <option value="İZİN EK-1">İZİN EK-1</option>
                                <option value="İZİN EK-2">İZİN EK-2</option>
                                <option value="KAPSAMDIŞI">KAPSAMDIŞI</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SAĞ TARAF: Konular -->
            <div class="lg:col-span-3 space-y-6">
                <div v-for="(obls, groupName) in groupedObligations" :key="groupName" class="bg-white dark:bg-[#1e293b] rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div class="bg-slate-50 dark:bg-slate-800/50 px-5 py-3 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-md font-bold text-indigo-700 dark:text-indigo-400">{{ groupName }}</h3>
                    </div>
                    
                    <div class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <div v-for="obl in obls" :key="obl.k" class="p-4 grid grid-cols-1 lg:grid-cols-12 gap-4 items-center hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors">
                            
                            <div class="lg:col-span-4">
                                <div class="font-semibold text-sm text-slate-800 dark:text-slate-200">{{ obl.n }}</div>
                                <div class="flex flex-wrap items-center gap-2 mt-1">
                                    <span :class="['text-xs font-bold', getStatusColor(evaluations[obl.k]?.state)]">
                                        {{ meta.stateL[evaluations[obl.k]?.state] || 'Bilinmiyor' }}
                                    </span>
                                    
                                    <span v-if="evaluations[obl.k]?.days !== null && evaluations[obl.k]?.days !== undefined" class="text-[10px] text-slate-500">
                                        ({{ Math.round(evaluations[obl.k].days) }} gün)
                                    </span>
                                    <span v-if="evaluations[obl.k]?.due" class="text-[10px] text-slate-500 font-semibold">
                                        - Son Tarih: {{ formatDate(evaluations[obl.k].due) }}
                                    </span>
                                </div>
                                <div v-if="evaluations[obl.k]?.label" class="text-[10px] text-slate-400 mt-1 line-clamp-1" :title="evaluations[obl.k].label">
                                    {{ evaluations[obl.k].label }}
                                </div>
                            </div>

                            <div class="lg:col-span-8 flex flex-wrap gap-2 items-center">
                                
                                <select :value="form.items[obl.k]?.s || ''" @change="changeItemState(obl.k, $event.target.value)" :disabled="!canEdit" class="w-32 rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 p-1.5 text-xs dark:text-white disabled:opacity-60">
                                    <option value="">Girilmedi</option>
                                    <option value="tarih">Tarih</option>
                                    <option value="muaf">Kapsam Dışı</option>
                                </select>

                                <!-- DÜZELTME: Kütle Denge dahil her şey için artık her zaman tam tarih (type="date") kullanılacak -->
                                <input v-if="form.items[obl.k]?.s === 'tarih' || form.items[obl.k]?.s === 'muaf'" 
                                       type="date" 
                                       v-model="form.items[obl.k].d" 
                                       :disabled="!canEdit" 
                                       class="rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 p-1.5 text-xs dark:text-white disabled:opacity-60 w-36" 
                                       :title="form.items[obl.k]?.s === 'muaf' ? 'Opsiyonel Tarih' : 'Tarih'">
                                
                                <input type="text" v-model="form.items[obl.k].note" :disabled="!canEdit" placeholder="Not ekle..." class="flex-1 min-w-[150px] rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 p-1.5 text-xs dark:text-white disabled:opacity-60">
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>