<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router, Head } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
    tasks: Array,
    consultants: Array
});

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

const showAddModal = ref(false);
const isSaving = ref(false);
const errorMsg = ref('');

const newFirm = ref({
    name: '',
    consultant: '',
    sector: ''
});

const openAddModal = () => {
    newFirm.value = { name: '', consultant: '', sector: '' };
    errorMsg.value = '';
    showAddModal.value = true;
};

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
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(newFirm.value)
        });

        const data = await response.json();

        if (response.ok) {
            showAddModal.value = false;
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

// 'surecte' kaldırıldı
const ozet = computed(() => ({
    gecikmis: props.stats?.overdue || 0,
    kritik: props.stats?.crit || 0,
    yaklasan: props.stats?.soon || 0
}));

const isler = computed(() => props.tasks || []);

const getStatusColor = (state) => {
    if (state === 'overdue') return 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20';
    if (state === 'crit') return 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-500/10 dark:text-orange-400 dark:border-orange-500/20';
    if (state === 'soon') return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20';
    return 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-700/50 dark:text-gray-400 dark:border-gray-600';
};
</script>

<template>
    <Head title="Genel Bakış" />

    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Başlık ve Yeni Tesis Butonu -->
        <div class="flex flex-col md:flex-row justify-between md:items-end gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Genel Bakış</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-1 text-xs">Sistemdeki güncel mevzuat durumunuz ve yaklaşan işleriniz.</p>
            </div>
            <button @click="openAddModal" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition flex items-center justify-center space-x-2 w-full md:w-auto">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Yeni Tesis Ekle</span>
            </button>
        </div>
        
        <!-- Özet Kartları (Grid 4'ten 3'e düşürüldü) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Gecikmiş İşler -->
            <div class="bg-white dark:bg-[#1e293b] rounded-xl p-4 border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden">
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Gecikmiş İşler</p>
                <p class="text-3xl font-extrabold text-slate-800 dark:text-white">{{ ozet.gecikmis }}</p>
                <div class="absolute top-2 right-2 w-10 h-10 rounded-full bg-rose-100 dark:bg-rose-500/20 flex items-center justify-center text-rose-600 dark:text-rose-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
            </div>
            
            <!-- Kritik -->
            <div class="bg-white dark:bg-[#1e293b] rounded-xl p-4 border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden">
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Kritik (14 Gün)</p>
                <p class="text-3xl font-extrabold text-slate-800 dark:text-white">{{ ozet.kritik }}</p>
                <div class="absolute top-2 right-2 w-10 h-10 rounded-full bg-orange-100 dark:bg-orange-500/20 flex items-center justify-center text-orange-600 dark:text-orange-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
            </div>
            
            <!-- Yaklaşan -->
            <div class="bg-white dark:bg-[#1e293b] rounded-xl p-4 border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden">
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Yaklaşan (30 Gün)</p>
                <p class="text-3xl font-extrabold text-slate-800 dark:text-white">{{ ozet.yaklasan }}</p>
                <div class="absolute top-2 right-2 w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-500/20 flex items-center justify-center text-amber-600 dark:text-amber-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
        </div>

        <!-- Öncelikli İşler Tablosu -->
        <div class="bg-white dark:bg-[#1e293b] rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/30">
                <h2 class="text-base font-bold text-slate-800 dark:text-white">Öncelikli İşler</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider font-semibold">
                            <th class="px-4 py-2.5">Tesis Adı</th>
                            <th class="px-4 py-2.5">Danışman</th>
                            <th class="px-4 py-2.5">Konu</th>
                            <th class="px-4 py-2.5">Kalan Süre</th>
                            <th class="px-4 py-2.5">Durum</th>
                            <th class="px-4 py-2.5 text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="(is, index) in isler" :key="index" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors text-xs">
                            <td class="px-4 py-2.5 font-semibold text-slate-800 dark:text-slate-200">
                                <Link :href="`/firma/${is.firm_id}`" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                    {{ is.firma }}
                                </Link>
                            </td>
                            <td class="px-4 py-2.5 text-slate-600 dark:text-slate-400 font-medium">
                                {{ is.consultant }}
                            </td>
                            <td class="px-4 py-2.5 text-slate-600 dark:text-slate-400">{{ is.konu }}</td>
                            <td class="px-4 py-2.5 font-bold text-slate-700 dark:text-slate-300">
                                {{ is.days !== null && is.days !== undefined ? is.days + ' gün' : 'Belirsiz' }}
                            </td>
                            <td class="px-4 py-2.5">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-bold border', getStatusColor(is.state)]">
                                    {{ is.durum }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <Link :href="`/firma/${is.firm_id}`" class="inline-block text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 p-1.5 rounded-md transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="isler.length === 0">
                            <td colspan="6" class="px-4 py-6 text-center text-xs text-slate-500">Harika! Şu an öncelikli bir işiniz bulunmuyor.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Yeni Tesis Ekleme Modalı -->
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
    </div>
</template>