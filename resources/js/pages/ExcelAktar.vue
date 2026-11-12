<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({ auth: Object, errors: Object, hasTemplate: Boolean, isAdmin: Boolean });

const fileInput = ref(null);
const file = ref(null);
const isUploading = ref(false);
const previewData = ref(null);
const errorMsg = ref('');
const successMsg = ref('');

// Şablon Yükleme State'leri
const templateInput = ref(null);
const templateFile = ref(null);
const isUploadingTemplate = ref(false);
const localHasTemplate = ref(props.hasTemplate);
const templateSuccessMsg = ref('');

const selectedRows = ref({});
const itemSelections = ref({});
const expandedRow = ref(null);

const handleFileChange = (e) => {
    const selected = e.target.files[0];
    if (selected) {
        file.value = selected;
        previewData.value = null;
        errorMsg.value = '';
        successMsg.value = '';
    }
};

// ŞABLON YÜKLEME FONKSİYONU
const handleTemplateChange = (e) => {
    const selected = e.target.files[0];
    if (selected) templateFile.value = selected;
};

const uploadTemplate = async () => {
    if (!templateFile.value) return;
    isUploadingTemplate.value = true;
    templateSuccessMsg.value = '';
    errorMsg.value = '';

    const formData = new FormData();
    formData.append('template_file', templateFile.value);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        const response = await fetch('/api/excel/upload-template', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: formData
        });
        const data = await response.json();
        
        if (response.ok) {
            templateSuccessMsg.value = 'Ana Şablon başarıyla yüklendi! Artık tüm indirmeler bu orijinal dosya üzerinden yapılacak.';
            localHasTemplate.value = true;
            templateFile.value = null;
            if (templateInput.value) templateInput.value.value = '';
        } else {
            errorMsg.value = data.error || 'Şablon yüklenemedi.';
        }
    } catch (err) {
        errorMsg.value = 'Sunucu hatası.';
    } finally {
        isUploadingTemplate.value = false;
    }
};

const submitPreview = async () => {
    if (!file.value) return;
    isUploading.value = true;
    errorMsg.value = '';
    
    const formData = new FormData();
    formData.append('file', file.value);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        const response = await fetch('/api/excel/preview', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: formData
        });
        
        const textData = await response.text();
        let data;
        try { data = JSON.parse(textData); } catch (e) { throw new Error("Sunucu JSON yerine HTML döndürdü."); }

        if (response.ok && data && Array.isArray(data.rows)) {
            previewData.value = data;
            data.rows.forEach(r => {
                selectedRows.value[r.row] = true;
                itemSelections.value[r.row] = {};
                if (r.items) Object.keys(r.items).forEach(k => { itemSelections.value[r.row][k] = true; });
            });
        } else {
            errorMsg.value = data.error || data.message || 'Dosya okundu ancak işlenecek geçerli bir veri bulunamadı.';
        }
    } catch (err) {
        errorMsg.value = err.message;
    } finally {
        isUploading.value = false;
    }
};

const toggleRow = (row) => { selectedRows.value[row] = !selectedRows.value[row]; };
const toggleItem = (row, key) => { itemSelections.value[row][key] = !itemSelections.value[row][key]; };
const toggleExpand = (row) => { expandedRow.value = expandedRow.value === row ? null : row; };

const confirmImport = async () => {
    if (!file.value || !previewData.value || !Array.isArray(previewData.value.rows)) return;
    isUploading.value = true;
    errorMsg.value = '';
    
    const selectedData = previewData.value.rows.map(r => ({
        ...r,
        selected: selectedRows.value[r.row] || false,
        item_selections: itemSelections.value[r.row] || {}
    }));

    const formData = new FormData();
    formData.append('file', file.value);
    formData.append('selected_data', JSON.stringify(selectedData));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        const response = await fetch('/api/excel/import', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: formData
        });
        
        const data = await response.json();
        if (response.ok) {
            successMsg.value = `${data.imported} tesis başarıyla güncellendi/eklendi!`;
            previewData.value = null; file.value = null;
            if (fileInput.value) fileInput.value.value = '';
        } else { errorMsg.value = data.error || 'İçe aktarma başarısız.'; }
    } catch (err) { errorMsg.value = 'Sunucu hatası oluştu.'; } finally { isUploading.value = false; }
};

</script>

<template>
    <Head title="Excel İçe Aktar" />
    <div class="w-full space-y-6 pb-20">
        
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Excel İşlemleri</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-1 text-xs">Excel veri aktarımı ve dışa aktarma işlemleri.</p>
            </div>
            <a href="/api/export-excel" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a2 2 0 01.707.293l5.414 5.414A2 2 0 0119 9.414V19a2 2 0 01-2 2z"></path>
                </svg>
                Excel Dışa Aktar
            </a>
        </div>

        <div v-if="errorMsg" class="p-4 bg-rose-50 text-rose-700 border border-rose-200 rounded-xl text-sm dark:bg-rose-900/20 dark:text-rose-400">
            <strong>Hata:</strong> {{ errorMsg }}
        </div>
        <div v-if="successMsg" class="p-4 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl text-sm dark:bg-emerald-900/20 dark:text-emerald-400">
            {{ successMsg }}
        </div>

        <!-- ANA ŞABLON YÜKLEME ALANI -->
        <div v-if="!previewData && isAdmin" class="bg-blue-50/50 dark:bg-blue-900/10 p-6 rounded-xl border border-blue-200 dark:border-blue-900/50">
            <h2 class="text-lg font-bold text-blue-800 dark:text-blue-400 mb-2 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Ana Şablon Yönetimi
            </h2>
            <p class="text-sm text-blue-700/80 dark:text-blue-300/80 mb-4">
                Sistemden indireceğiniz Excel dosyalarının <strong>birebir sizin orijinal dosyanız</strong> (formülleriniz, renkleriniz) olması için buraya bir kez şablonunuzu yükleyin.
            </p>

            <div v-if="localHasTemplate" class="mb-4 inline-flex items-center px-3 py-1 bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400 rounded-full text-xs font-bold">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                Sistemde kayıtlı bir şablon mevcut.
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-3">
                <input type="file" ref="templateInput" @change="handleTemplateChange" accept=".xlsx, .xls" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200 dark:file:bg-blue-900/30 dark:file:text-blue-400 cursor-pointer">
                <button type="button" @click="uploadTemplate" :disabled="!templateFile || isUploadingTemplate" class="shrink-0 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold shadow-sm transition disabled:opacity-50">
                    {{ isUploadingTemplate ? 'Yükleniyor...' : 'Şablonu Yükle' }}
                </button>
            </div>
            <div v-if="templateSuccessMsg" class="mt-3 text-sm text-emerald-600 font-medium">{{ templateSuccessMsg }}</div>
        </div>

        <!-- VERİ YÜKLEME (İÇE AKTARMA) ALANI -->
        <div v-if="!previewData" class="bg-white dark:bg-[#1e293b] p-8 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 text-center border-dashed border-2">
            <h2 class="text-lg font-bold text-slate-800 dark:text-white mb-2">Veri İçe Aktar</h2>
            <p class="text-xs text-slate-500 mb-4">Güncel verilerinizi içeren Excel dosyasını buraya yükleyin.</p>
            <input type="file" ref="fileInput" @change="handleFileChange" accept=".xlsx, .xls" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 mx-auto max-w-xs cursor-pointer">
            <button type="button" @click="submitPreview" :disabled="!file || isUploading" class="mt-6 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-semibold shadow-sm transition disabled:opacity-50 flex items-center space-x-2 mx-auto">
                <span>{{ isUploading ? 'Analiz Ediliyor...' : 'Analiz Et' }}</span>
            </button>
        </div>

        <!-- Önizleme Alanı (Değişmedi) -->
        <div v-else class="bg-white dark:bg-[#1e293b] rounded-xl shadow-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="bg-indigo-50 dark:bg-indigo-900/20 p-5 border-b border-indigo-100 dark:border-indigo-800 flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold text-indigo-800 dark:text-indigo-300">İnceleme ve Onay</h3>
                    <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-1">Toplam {{ previewData.total || 0 }} tesis bulundu.</p>
                </div>
                <button @click="previewData = null" class="text-sm font-semibold text-slate-500 hover:text-slate-700">İptal Et</button>
            </div>

            <div v-if="previewData.unread_cells && previewData.unread_cells.length > 0" class="m-4 p-4 bg-orange-50 border border-orange-200 rounded-lg dark:bg-orange-900/20 dark:border-orange-800">
                <h4 class="font-bold text-orange-800 dark:text-orange-400 text-sm mb-2">⚠️ Dikkat: Okunamayan Hücreler Var!</h4>
                <ul class="list-disc list-inside text-xs text-orange-700 dark:text-orange-300 space-y-1">
                    <li v-for="(cell, idx) in previewData.unread_cells" :key="idx">
                        <strong>Satır {{ cell.row }}, {{ cell.col }} Sütunu ({{ cell.topic }}):</strong>
                        {{ cell.message || `"${cell.value}" değeri anlaşılamadı.` }}
                    </li>
                </ul>
            </div>

            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                <div v-for="row in (previewData.rows || [])" :key="row.row" class="bg-white dark:bg-[#1e293b]">
                    <div class="p-4 flex items-center hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer" @click="toggleExpand(row.row)">
                        <input type="checkbox" :checked="selectedRows[row.row]" @click.stop="toggleRow(row.row)" class="w-5 h-5 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500 mr-4">
                        <div class="flex-1">
                            <div class="font-bold text-slate-800 dark:text-slate-200 flex items-center space-x-2">
                                <span>{{ row.name }}</span>
                                <span v-if="row.is_new" class="px-2 py-0.5 text-[10px] bg-emerald-100 text-emerald-700 rounded-full font-bold">YENİ KAYIT</span>
                                <span v-else class="px-2 py-0.5 text-[10px] bg-amber-100 text-amber-700 rounded-full font-bold">{{ (row.changes && row.changes.length) ? row.changes.length : 0 }} GÜNCELLEME</span>
                            </div>
                            <div class="text-xs text-slate-500 mt-1">{{ row.consultant }} - {{ row.sector }}</div>
                        </div>
                        <svg :class="['w-5 h-5 text-slate-400 transition-transform', expandedRow === row.row ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </div>

                    <div v-if="expandedRow === row.row" class="px-12 pb-4 bg-slate-50 dark:bg-slate-800/30">
                        <table v-if="row.items || (row.changes && row.changes.length)" class="w-full text-xs text-left mt-2">
                            <thead>
                                <tr class="text-slate-500 border-b dark:border-slate-700">
                                    <th class="py-2 w-10">Seç</th>
                                    <th class="py-2">Konu</th>
                                    <th class="py-2 text-rose-500">Eski Veri</th>
                                    <th class="py-2 text-emerald-500">Yeni Veri (Excel)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                <template v-if="row.is_new">
                                    <tr v-for="(item, key) in row.items" :key="key" class="hover:bg-white dark:hover:bg-slate-800">
                                        <td class="py-2"><input type="checkbox" :checked="itemSelections[row.row]?.[key]" @change="toggleItem(row.row, key)" class="w-4 h-4 rounded"></td>
                                        <td class="py-2 font-medium text-slate-700 dark:text-slate-300">{{ key }}</td>
                                        <td class="py-2 text-slate-400">-</td>
                                        <td :class="['py-2 font-semibold', item.d || (item.s && item.s !== 'surec') ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-700 dark:text-red-300']">{{ item.d || item.s || 'Girilmedi' }}</td>
                                    </tr>
                                </template>
                                <template v-else>
                                    <tr v-for="change in row.changes" :key="change.key" class="hover:bg-white dark:hover:bg-slate-800">
                                        <td class="py-2"><input type="checkbox" :checked="itemSelections[row.row]?.[change.key]" @change="toggleItem(row.row, change.key)" class="w-4 h-4 rounded"></td>
                                        <td class="py-2 font-medium text-slate-700 dark:text-slate-300">{{ change.name }}</td>
                                        <td class="py-2 text-slate-500 line-through">{{ change.old }}</td>
                                        <td :class="['py-2 font-semibold', ['Girilmedi', 'Veri Yok', 'Tarih Yok'].includes(change.new) ? 'text-red-700 dark:text-red-300' : 'text-emerald-600 dark:text-emerald-400']">{{ change.new }}</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="p-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 flex justify-end">
                <button @click="confirmImport" :disabled="isUploading" class="bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-2.5 rounded-lg font-bold shadow-md transition disabled:opacity-50">
                    {{ isUploading ? 'İşleniyor...' : 'Seçilenleri Onayla ve Aktar' }}
                </button>
            </div>
        </div>

    </div>
</template>
