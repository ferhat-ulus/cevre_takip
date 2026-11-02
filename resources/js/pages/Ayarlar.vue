<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    config: Object,
    obligations: Array
});

const activeTab = ref('general');

const isSaving = ref(false);
const successMsg = ref('');
const errorMsg = ref('');

const form = ref({
    surecDays: props.config?.surecDays || 90,
    staleDays: props.config?.staleDays || 30,
    reviewDays: props.config?.reviewDays || 30,
    viewAll: props.config?.viewAll || false,
    ob: JSON.parse(JSON.stringify(props.config?.ob || {}))
});

const newObForm = ref({
    k: '', n: '', g: 'Şirket Belgeleri', custom_g: '', p: 365, red: 30, amber: 60
});

// Statik mevzuatları bulmak için (Silme butonunu gizlemek için kullanacağız)
const staticObligationKeys = [
    'kapasite', 'police', 'izin', 'ilUygunluk', 'emisyonOlcum', 'emisyonRapor', 
    'atiksuIzleme', 'atiksuGorus', 'aracLisans', 'eayp', 'tayp', 'sifirAtik', 
    'madenAtik', 'kutleDenge', 'bekra', 'ekomvet', 'kstk', 'gekap', 
    'yesilDonusum', 'suVerimlilik', 'icTetkik', 'egitim'
];

props.obligations.forEach(ob => {
    if (!form.value.ob[ob.k]) form.value.ob[ob.k] = {};
    if (form.value.ob[ob.k].p === undefined && ob.p !== null) form.value.ob[ob.k].p = ob.p;
    if (form.value.ob[ob.k].red === undefined) form.value.ob[ob.k].red = ob.red;
    if (form.value.ob[ob.k].amber === undefined) form.value.ob[ob.k].amber = ob.amber;
});

const saveSettings = async () => {
    isSaving.value = true; successMsg.value = ''; errorMsg.value = '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        const response = await fetch('/api/settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify(form.value)
        });

        if (response.ok) {
            successMsg.value = 'Ayarlar başarıyla kaydedildi.';
            setTimeout(() => successMsg.value = '', 3000);
        } else {
            const data = await response.json();
            errorMsg.value = data.error || data.message || 'Ayarlar kaydedilemedi.';
        }
    } catch (err) { errorMsg.value = 'Sunucu hatası oluştu.'; } 
    finally { isSaving.value = false; }
};

const addNewObligation = async () => {
    if (!newObForm.value.k || !newObForm.value.n) {
        errorMsg.value = 'Lütfen mevzuat adı ve kısa kodu girin.'; return;
    }
    let finalGroup = newObForm.value.g;
    if (finalGroup === 'Yeni Kategori') {
        if (!newObForm.value.custom_g) { errorMsg.value = 'Lütfen yeni kategori adını girin.'; return; }
        finalGroup = newObForm.value.custom_g;
    }
    
    isSaving.value = true; successMsg.value = ''; errorMsg.value = '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        const response = await fetch('/api/settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ new_obligation: { ...newObForm.value, g: finalGroup } })
        });

        if (response.ok) {
            successMsg.value = 'Yeni mevzuat başarıyla eklendi.';
            setTimeout(() => router.reload(), 2000);
        } else {
            const data = await response.json();
            errorMsg.value = data.error || data.message || 'Mevzuat eklenemedi.';
        }
    } catch (err) { errorMsg.value = 'Sunucu hatası oluştu.'; } 
    finally { isSaving.value = false; }
};

// YENİ: Mevzuat Silme
const deleteObligation = async (key, name) => {
    if (!confirm(`"${name}" mevzuatını sistemden kalıcı olarak silmek istediğinize emin misiniz?\n\nNot: Bu işlem, bu mevzuata ait girilmiş tüm verileri de görünmez yapacaktır.`)) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    try {
        const response = await fetch(`/api/settings/obligation/${key}`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        });
        
        if (response.ok) {
            router.reload();
        } else {
            const data = await response.json();
            alert(data.error || 'Mevzuat silinemedi.');
        }
    } catch (err) { alert('Sunucu hatası.'); }
};
</script>

<template>
    <Head title="Sistem Ayarları" />

    <div class="max-w-7xl mx-auto space-y-6 pb-20">
        
        <div class="flex justify-between items-end">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Sistem Ayarları</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-1 text-xs">Mevzuat sürelerini ve sistem eşiklerini yönetin.</p>
            </div>
        </div>

        <div v-if="successMsg" class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg text-sm font-bold flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ successMsg }}
        </div>
        <div v-if="errorMsg" class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-lg text-sm font-bold flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            {{ errorMsg }}
        </div>

        <div class="flex border-b border-slate-200 dark:border-slate-700">
            <button @click="activeTab = 'general'" :class="['px-4 py-2 text-sm font-semibold border-b-2 transition', activeTab === 'general' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400']">Genel Eşikler</button>
            <button @click="activeTab = 'obligations'" :class="['px-4 py-2 text-sm font-semibold border-b-2 transition', activeTab === 'obligations' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400']">Mevzuat Süreleri</button>
            <button @click="activeTab = 'add_new'" :class="['px-4 py-2 text-sm font-semibold border-b-2 transition', activeTab === 'add_new' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400']">Yeni Mevzuat Ekle</button>
        </div>

        <!-- SEKME 1: GENEL EŞİKLER -->
        <div v-if="activeTab === 'general'" class="bg-white dark:bg-[#1e293b] rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-xs font-medium mb-2 dark:text-slate-300">Süreç Açık Kalma Limiti (Gün)</label>
                    <input type="number" v-model="form.surecDays" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-2 dark:text-slate-300">Durum Güncellenmeme Limiti (Gün)</label>
                    <input type="number" v-model="form.staleDays" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-2 dark:text-slate-300">Genel Kritik Uyarı (Gün)</label>
                    <input type="number" v-model="form.reviewDays" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                </div>
            </div>
            <div class="pt-4 border-t border-slate-100 dark:border-slate-700">
                <button @click="saveSettings" :disabled="isSaving" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg text-sm font-semibold shadow-sm transition disabled:opacity-50">
                    {{ isSaving ? 'Kaydediliyor...' : 'Genel Ayarları Kaydet' }}
                </button>
            </div>
        </div>

        <!-- SEKME 2: MEVZUAT SÜRELERİ -->
        <div v-if="activeTab === 'obligations'" class="bg-white dark:bg-[#1e293b] rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto max-h-[600px] overflow-y-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead class="sticky top-0 bg-slate-50 dark:bg-slate-800 shadow-sm z-10">
                        <tr class="text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider font-semibold">
                            <th class="px-4 py-3">Mevzuat Adı</th>
                            <th class="px-4 py-3">Grup</th>
                            <th class="px-4 py-3 w-24">Periyot</th>
                            <th class="px-4 py-3 w-24">Kırmızı</th>
                            <th class="px-4 py-3 w-24">Sarı</th>
                            <th class="px-4 py-3 text-right">İşlem</th> <!-- SİL BUTONU İÇİN -->
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-xs">
                        <tr v-for="ob in obligations" :key="ob.k" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                            <td class="px-4 py-2 font-bold text-slate-800 dark:text-slate-200">{{ ob.n }}</td>
                            <td class="px-4 py-2 text-slate-500">{{ ob.g }}</td>
                            <td class="px-4 py-2">
                                <input v-if="ob.p !== null" type="number" v-model="form.ob[ob.k].p" class="w-full rounded border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-1 dark:text-white text-xs">
                                <span v-else class="text-slate-400 italic text-[10px]">Esnek</span>
                            </td>
                            <td class="px-4 py-2"><input type="number" v-model="form.ob[ob.k].red" class="w-full rounded border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-1 dark:text-white text-xs"></td>
                            <td class="px-4 py-2"><input type="number" v-model="form.ob[ob.k].amber" class="w-full rounded border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-1 dark:text-white text-xs"></td>
                            <td class="px-4 py-2 text-right">
                                <!-- Sadece özel mevzuatlar için silme butonu -->
                                <button v-if="!staticObligationKeys.includes(ob.k)" @click="deleteObligation(ob.k, ob.n)" class="text-rose-600 hover:bg-rose-50 p-1.5 rounded transition" title="Sil">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/30">
                <button @click="saveSettings" :disabled="isSaving" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg text-sm font-semibold shadow-sm transition disabled:opacity-50">
                    {{ isSaving ? 'Kaydediliyor...' : 'Mevzuat Sürelerini Kaydet' }}
                </button>
            </div>
        </div>

        <!-- SEKME 3: YENİ MEVZUAT EKLE -->
        <div v-if="activeTab === 'add_new'" class="bg-white dark:bg-[#1e293b] rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-medium mb-2 dark:text-slate-300">Mevzuat Adı <span class="text-rose-500">*</span></label>
                    <input type="text" v-model="newObForm.n" placeholder="Örn: Karbon Ayak İzi Raporu" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm uppercase">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-2 dark:text-slate-300">Kısa Kod (Sistem İçi Benzersiz) <span class="text-rose-500">*</span></label>
                    <input type="text" v-model="newObForm.k" placeholder="Örn: karbon" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm lowercase">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium mb-2 dark:text-slate-300">Kategori (Grup)</label>
                    <select v-model="newObForm.g" class="w-full md:w-1/2 rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                        <option value="Şirket Belgeleri">Şirket Belgeleri</option>
                        <option value="İzin ve kapasite">İzin ve Kapasite</option>
                        <option value="Hava">Hava</option>
                        <option value="Su">Su</option>
                        <option value="Atık">Atık</option>
                        <option value="Bildirim ve sistemler">Bildirim ve Sistemler</option>
                        <option value="Sanayi dönüşümü">Sanayi Dönüşümü</option>
                        <option value="Yönetim sistemi">Yönetim Sistemi</option>
                        <option value="Yeni Kategori">Yeni Kategori Ekle...</option>
                    </select>
                    <div v-if="newObForm.g === 'Yeni Kategori'" class="mt-3">
                        <input type="text" v-model="newObForm.custom_g" placeholder="Yeni kategori adı (Örn: İklim Değişikliği)" class="w-full md:w-1/2 rounded-md border border-indigo-300 bg-indigo-50 p-2 dark:text-white text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-2 dark:text-slate-300">Periyot (Gün)</label>
                    <input type="number" v-model="newObForm.p" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-2 dark:text-slate-300">Kritik Eşik (Gün)</label>
                    <input type="number" v-model="newObForm.red" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-2 dark:text-slate-300">Yaklaşan Eşik (Gün)</label>
                    <input type="number" v-model="newObForm.amber" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                </div>
            </div>
            <div class="pt-4 border-t border-slate-100 dark:border-slate-700">
                <button @click="addNewObligation" :disabled="isSaving" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2 rounded-lg text-sm font-semibold shadow-sm transition disabled:opacity-50">
                    {{ isSaving ? 'Ekleniyor...' : 'Yeni Mevzuatı Sisteme Ekle' }}
                </button>
            </div>
        </div>

    </div>
</template>