<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    firms: Array,
    consultants: Array
});

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

// GÜVENLİK UYARISI (TOAST) İÇİN
const showToast = ref(false);
const toastMessage = ref('');

onMounted(() => {
    if (page.props.flash && page.props.flash.error) {
        toastMessage.value = page.props.flash.error;
        showToast.value = true;
        setTimeout(() => { showToast.value = false; }, 5000);
    }
});

const search = ref('');
const selectedConsultant = ref('all');
const workFilter = ref('aktif');
const currentPage = ref(1);
const itemsPerPage = 20;

// YENİ TESİS MODALI
const showAddModal = ref(false);
const isSaving = ref(false);
const errorMsg = ref('');
const newFirm = ref({ name: '', sector: '', consultant: '' });

// GEÇMİŞ (LOG) MODALI
const showEndModal = ref(false);
const showLogModal = ref(false);
const endingFirm = ref(null);
const endDate = ref('');
const endError = ref('');

const isClosed = (firm) => firm.status === 'sonlandi' || firm.status === 'arsiv';
const selectedFirmLogs = ref([]);
const isLoadingLogs = ref(false);
const selectedFirmName = ref('');

const filteredFirms = computed(() => {
    let result = props.firms;
    if (selectedConsultant.value !== 'all') {
        result = result.filter(f => f.consultant === selectedConsultant.value);
    }
    if (workFilter.value === 'aktif') result = result.filter((firm) => !isClosed(firm));
    if (workFilter.value === 'sonlandi') result = result.filter((firm) => isClosed(firm));
    if (search.value) {
        const q = search.value.toLocaleLowerCase('tr');
        result = result.filter((firm) => {
            const name = String(firm.name || '').toLocaleLowerCase('tr');
            const sector = String(firm.sector || '').toLocaleLowerCase('tr');
            return name.includes(q) || sector.includes(q);
        });
    }
    return result;
});

const totalPages = computed(() => Math.ceil(filteredFirms.value.length / itemsPerPage));
const paginatedFirms = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredFirms.value.slice(start, start + itemsPerPage);
});

const resetPage = () => { currentPage.value = 1; };

const saveFirm = async () => {
    if (!newFirm.value.name) {
        errorMsg.value = 'Tesis adı zorunludur.';
        return;
    }

    isSaving.value = true;
    errorMsg.value = '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        const response = await fetch('/api/firms', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify(newFirm.value)
        });

        const data = await response.json();

        if (response.ok) {
            router.visit(`/firma/${data.id}`);
        } else {
            errorMsg.value = data.message || data.error || 'Tesis eklenemedi.';
        }
    } catch (err) {
        errorMsg.value = 'Sunucu hatası oluştu.';
    } finally {
        isSaving.value = false;
    }
};

const saveEngagement = async (firm, dates) => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const response = await fetch(`/api/firms/${firm.id}/engagement`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({
            contract_date: firm.contractDate || null,
            started_at: firm.serviceStartedAt || null,
            ...dates,
        }),
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Çalışma dönemi kaydedilemedi.');
    router.reload({ only: ['firms'] });
};

const openEndModal = (firm) => {
    endingFirm.value = firm;
    endDate.value = new Date().toISOString().slice(0, 10);
    endError.value = '';
    showEndModal.value = true;
};

const submitEnd = async () => {
    if (!endDate.value) {
        endError.value = 'Çalışmanın bittiği tarihi girin.';
        return;
    }
    if (endingFirm.value.serviceStartedAt && endDate.value < endingFirm.value.serviceStartedAt) {
        endError.value = 'Bitiş tarihi, çalışmaya başlama tarihinden önce olamaz.';
        return;
    }

    isSaving.value = true;
    endError.value = '';
    try {
        await saveEngagement(endingFirm.value, { ended_at: endDate.value });
        showEndModal.value = false;
    } catch (err) {
        endError.value = err.message;
    } finally {
        isSaving.value = false;
    }
};

const restartFirm = async (firm) => {
    if (!confirm(`${firm.name} ile çalışmayı yeniden başlatmak istiyor musunuz? Bitiş tarihi kaldırılır, tesis kaydı yerinde kalır.`)) return;
    try {
        await saveEngagement(firm, { ended_at: null });
    } catch (err) {
        alert(err.message);
    }
};

const formatDay = (value) => {
    if (!value) return '—';
    const [year, month, day] = String(value).slice(0, 10).split('-');
    return day && month && year ? `${day}.${month}.${year}` : value;
};

const deleteFirm = async (firm) => {
    if (!confirm(`DİKKAT: ${firm.name} tesisini ve bu tesise ait TÜM ziyaret kayıtlarını kalıcı olarak silmek üzeresiniz. Bu işlem geri alınamaz! Onaylıyor musunuz?`)) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    try {
        const response = await fetch(`/api/firms/${firm.id}`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        });
        if (response.ok) {
            router.reload({ only: ['firms'] });
        } else {
            alert('Silme işlemi başarısız.');
        }
    } catch (err) { alert('Sunucu hatası.'); }
};

// GEÇMİŞ GETİRME FONKSİYONU
const fetchLogs = async (firm) => {
    selectedFirmName.value = firm.name;
    showLogModal.value = true;
    isLoadingLogs.value = true;
    selectedFirmLogs.value = [];

    try {
        const response = await fetch(`/api/firms/${firm.id}/logs`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await response.json();
        if (response.ok) {
            selectedFirmLogs.value = data.logs;
        } else {
            alert('Loglar yüklenemedi.');
        }
    } catch (err) {
        alert('Sunucu hatası.');
    } finally {
        isLoadingLogs.value = false;
    }
};

// TARİH FORMATLAMA (LOG MODALI İÇİN)
const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('tr-TR', { 
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    }).format(date);
};
</script>

<template>
    <Head title="Tesisler" />

    <div class="w-full space-y-4 relative">
        
        <!-- GÜVENLİK UYARISI (TOAST) -->
        <div v-if="showToast" class="fixed top-20 right-4 z-[100] animate-fade-in-down">
            <div class="px-6 py-4 rounded-xl shadow-lg border text-sm font-bold flex items-center space-x-3 bg-rose-50 border-rose-200 text-rose-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <span>{{ toastMessage }}</span>
            </div>
        </div>

        <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Tesisler</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-1 text-xs">Sistemdeki tüm tesisleri listeleyin, arayın ve yeni tesis ekleyin.</p>
            </div>
            
            <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
                <div v-if="isAdmin" class="flex items-center w-full sm:w-auto bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 shadow-sm">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 mr-2 whitespace-nowrap">Danışman:</span>
                    <select v-model="selectedConsultant" @change="resetPage" class="w-full bg-transparent border-none text-sm font-medium text-indigo-600 dark:text-indigo-400 outline-none cursor-pointer">
                        <option value="all" class="dark:bg-gray-800">Tümü (Hepsi)</option>
                        <option v-for="c in consultants" :key="c" :value="c" class="dark:bg-gray-800">{{ c }}</option>
                    </select>
                </div>

                <select v-model="workFilter" @change="resetPage" class="w-full sm:w-auto rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                    <option value="aktif">Çalışılan tesisler</option>
                    <option value="sonlandi">Çalışması sonlananlar</option>
                    <option value="all">Tümü</option>
                </select>
                <div class="relative w-full sm:w-64">
                    <svg class="w-4 h-4 absolute left-3 top-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input v-model="search" @input="resetPage" type="text" placeholder="Tesis veya sektör ara..." class="w-full pl-9 pr-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                </div>

                <button @click="showAddModal = true" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition w-full sm:w-auto whitespace-nowrap">
                    + Yeni Tesis Ekle
                </button>
            </div>
        </div>

        <div class="bg-white dark:bg-[#1e293b] rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden flex flex-col">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider font-semibold">
                            <th class="px-4 py-3">Tesis Adı</th>
                            <th class="px-4 py-3">Danışman</th>
                            <th class="px-4 py-3">Sektör</th>
                            <th class="px-4 py-3">Çalışma aralığı</th>
                            <th class="px-4 py-3 text-center">Durum</th>
                            <th class="px-4 py-3 text-right w-36">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="firm in paginatedFirms" :key="firm.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors text-xs">
                            <td class="px-4 py-3 font-bold text-slate-800 dark:text-slate-200">
                                <Link :href="`/firma/${firm.id}`" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                    {{ firm.name }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ firm.consultant }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ firm.sector || '-' }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ formatDay(firm.serviceStartedAt) }} — {{ isClosed(firm) ? formatDay(firm.serviceEndedAt) : 'devam ediyor' }}</td>
                            
                            <td class="px-4 py-3 text-center">
                                <span v-if="isClosed(firm)" class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300 whitespace-nowrap">Sonlandı</span>
                                <span v-else class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 whitespace-nowrap">Aktif</span>
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="fetchLogs(firm)" class="text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 p-1.5 rounded-md transition-colors" title="Değişiklik Geçmişi">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </button>

                                    <Link :href="`/firma/${firm.id}`" class="text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 p-1.5 rounded-md transition-colors" title="Detaya Git / Düzenle">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </Link>
                                    
                                    <template v-if="isAdmin">
                                        <button v-if="!isClosed(firm)" @click="openEndModal(firm)" class="text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 p-1.5 rounded-md transition-colors" title="Çalışmayı sonlandır">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                                        </button>
                                        <button v-else @click="restartFirm(firm)" class="text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 p-1.5 rounded-md transition-colors" title="Çalışmayı yeniden başlat">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                        </button>
                                        <button @click="deleteFirm(firm)" class="text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 p-1.5 rounded-md transition-colors" title="Tamamen Sil">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredFirms.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">Arama kriterlerine uygun tesis bulunamadı.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row items-center justify-between bg-slate-50/50 dark:bg-slate-800/30 gap-4">
                <span class="text-xs text-slate-500 dark:text-slate-400">Toplam <strong>{{ filteredFirms.length }}</strong> tesis</span>
                <div v-if="totalPages > 1" class="flex space-x-2">
                    <button @click="currentPage--" :disabled="currentPage === 1" class="px-3 py-1.5 rounded bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-xs font-medium disabled:opacity-50 hover:bg-slate-50 dark:hover:bg-slate-600">Önceki</button>
                    <div class="flex items-center px-3 text-xs font-medium text-slate-600 dark:text-slate-300">{{ currentPage }} / {{ totalPages }}</div>
                    <button @click="currentPage++" :disabled="currentPage === totalPages" class="px-3 py-1.5 rounded bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-xs font-medium disabled:opacity-50 hover:bg-slate-50 dark:hover:bg-slate-600">Sonraki</button>
                </div>
            </div>
        </div>

        <!-- YENİ TESİS EKLEME MODALI -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-[#1e293b] rounded-xl p-6 w-full max-w-md shadow-xl border border-slate-200 dark:border-slate-700 text-sm">
                <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-700 pb-3 mb-4">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white">Yeni Tesis Ekle</h3>
                    <button @click="showAddModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div v-if="errorMsg" class="mb-4 p-3 bg-rose-50 text-rose-700 text-xs rounded-lg border border-rose-200">{{ errorMsg }}</div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-slate-300">Tesis Adı <span class="text-rose-500">*</span></label>
                        <input type="text" v-model="newFirm.name" placeholder="Örn: ABC Tekstil A.Ş." class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-slate-300">Sektör</label>
                        <input type="text" v-model="newFirm.sector" placeholder="Örn: TEKSTİL" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                    </div>
                    <div v-if="isAdmin">
                        <label class="block text-xs font-medium mb-1 dark:text-slate-300">Atanacak Danışman</label>
                        <select v-model="newFirm.consultant" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                            <option value="">Atanmadı</option>
                            <option v-for="c in consultants" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-2 mt-6">
                    <button @click="showAddModal = false" class="px-4 py-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition">İptal</button>
                    <button @click="saveFirm" :disabled="isSaving" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition disabled:opacity-50">
                        {{ isSaving ? 'Kaydediliyor...' : 'Tesis Ekle' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showEndModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 text-sm shadow-xl dark:border-slate-700 dark:bg-[#1e293b]">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Çalışmayı sonlandır</h3>
                <p class="mt-1 text-xs text-slate-500">{{ endingFirm?.name }} kaydı silinmez. Sözleşme ve yükümlülük bilgileri durur; yalnızca çalışma aralığı kapanır.</p>
                <p v-if="endError" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-2 text-xs text-rose-700">{{ endError }}</p>
                <label class="mt-4 block text-xs font-medium dark:text-slate-300" for="service-end-date">Çalışmanın bittiği tarih</label>
                <input id="service-end-date" v-model="endDate" type="date" class="mt-1 w-full rounded-md border border-slate-300 bg-white p-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="showEndModal = false" class="rounded-lg px-4 py-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Vazgeç</button>
                    <button type="button" @click="submitEnd" :disabled="isSaving" class="rounded-lg bg-amber-600 px-4 py-2 text-white hover:bg-amber-700 disabled:opacity-50">{{ isSaving ? 'Kaydediliyor...' : 'Sonlandır' }}</button>
                </div>
            </div>
        </div>

        <!-- GEÇMİŞ (LOG) MODALI -->
        <div v-if="showLogModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-[#1e293b] rounded-xl p-6 w-full max-w-lg shadow-xl border border-slate-200 dark:border-slate-700 text-sm flex flex-col max-h-[80vh]">
                <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-700 pb-3 mb-4 shrink-0">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white">{{ selectedFirmName }} - Geçmiş</h3>
                    <button @click="showLogModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="overflow-y-auto custom-scrollbar flex-1 pr-2">
                    <div v-if="isLoadingLogs" class="text-center py-8 text-slate-500">Yükleniyor...</div>
                    <div v-else-if="selectedFirmLogs.length === 0" class="text-center py-8 text-slate-500 italic">Bu tesis için henüz bir değişiklik kaydı bulunmuyor.</div>
                    
                    <div v-else class="space-y-4 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-slate-300 before:to-transparent dark:before:via-slate-600">
                        <div v-for="log in selectedFirmLogs" :key="log.id" class="relative flex items-start space-x-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-full border-2 border-white dark:border-[#1e293b] bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 shrink-0 z-10">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div class="flex-1 bg-slate-50 dark:bg-slate-800/50 p-3 rounded-lg border border-slate-100 dark:border-slate-700">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="font-bold text-slate-700 dark:text-slate-300 text-xs">{{ log.user?.name || 'Sistem' }}</span>
                                    <span class="text-[10px] text-slate-500">{{ formatDate(log.created_at) }}</span>
                                </div>
                                <p class="text-xs text-slate-600 dark:text-slate-400">{{ log.description }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@keyframes fade-in-down {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-down {
    animation: fade-in-down 0.3s ease-out forwards;
}
</style>