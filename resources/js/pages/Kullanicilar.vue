<script setup>
import { computed, ref, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import PlatformLayout from '@/layouts/PlatformLayout.vue';

defineOptions({ layout: PlatformLayout });

const props = defineProps({
    users: Array,
    firmCounts: Object,
    consultantFirms: { type: Array, default: () => [] },
    consultantHistory: Array,
    isAdmin: Boolean,
    modules: { type: Array, default: () => [] }
});

const page = usePage();
const currentUser = page.props.auth?.user;

// Modallar
const showAddModal = ref(false);
const showEditModal = ref(false);
const showPasswordModal = ref(false);
const showStatusModal = ref(false); // YENİ
const showHandoverModal = ref(false);

// Seçili Kullanıcı (Durum değişikliği için)
const selectedUserForStatus = ref(null); // YENİ
const selectedConsultant = ref(null);
const handoverForm = ref({ replacement_user_id: '', reason: '', deactivate: false });
const selectedFirmIds = ref([]);
const firmSearch = ref('');

// Form Değişkenleri
const form = ref({
    id: null,
    name: '',
    username: '',
    email: '',
    role: 'employee',
    password: '',
    active: true,
    new_password: '',
    module_accesses: {}
});

const isSaving = ref(false);
const errorMsg = ref('');
const successMsg = ref('');

// Arama
const search = ref('');
const filteredUsers = computed(() => {
    if (!search.value) return props.users;
    const q = search.value.toLowerCase();
    return props.users.filter(u => 
        u.name.toLowerCase().includes(q) || 
        u.username.toLowerCase().includes(q) || 
        (u.email && u.email.toLowerCase().includes(q))
    );
});
const activeConsultants = computed(() => props.users.filter(user =>
    user.role === 'employee' && user.active && user.id !== selectedConsultant.value?.id
));
const consultantFirmCount = (user) => Number(props.firmCounts?.[user.id] || 0);
const consultantFirmList = computed(() => props.consultantFirms.filter((firm) => Number(firm.consultant_user_id) === Number(selectedConsultant.value?.id)));
const visibleConsultantFirms = computed(() => {
    const query = firmSearch.value.trim().toLocaleLowerCase('tr-TR');
    if (!query) return consultantFirmList.value;

    return consultantFirmList.value.filter((firm) => firm.name.toLocaleLowerCase('tr-TR').includes(query));
});
const allFirmsSelected = computed(() =>
    consultantFirmList.value.length > 0 && selectedFirmIds.value.length === consultantFirmList.value.length
);
const emptyModuleAccesses = () => Object.fromEntries(props.modules.map((module) => [module.id, '']));

const openHandoverModal = (user, deactivate = false) => {
    selectedConsultant.value = user;
    const firmIds = props.consultantFirms.filter((firm) => Number(firm.consultant_user_id) === Number(user.id)).map((firm) => firm.id);
    selectedFirmIds.value = firmIds;
    firmSearch.value = '';
    handoverForm.value = { replacement_user_id: '', reason: '', deactivate: deactivate && firmIds.length > 0 };
    errorMsg.value = '';
    showStatusModal.value = false;
    showHandoverModal.value = true;
};

const toggleFirm = (firmId) => {
    selectedFirmIds.value = selectedFirmIds.value.includes(firmId)
        ? selectedFirmIds.value.filter((id) => id !== firmId)
        : [...selectedFirmIds.value, firmId];
};

const toggleAllFirms = () => {
    selectedFirmIds.value = allFirmsSelected.value ? [] : consultantFirmList.value.map((firm) => firm.id);
};

watch(allFirmsSelected, (selected) => {
    if (!selected) handoverForm.value.deactivate = false;
});

const openAddModal = () => {
    form.value = { name: '', username: '', email: '', role: 'employee', password: '', active: true, module_accesses: emptyModuleAccesses() };
    errorMsg.value = '';
    successMsg.value = '';
    showAddModal.value = true;
};

const openEditModal = (user) => {
    form.value = { 
        id: user.id, 
        name: user.name, 
        username: user.username, 
        email: user.email || '', 
        role: user.role, 
        active: Boolean(user.active),
        module_accesses: { ...emptyModuleAccesses(), ...(user.module_accesses || {}) }
    };
    errorMsg.value = '';
    successMsg.value = '';
    showEditModal.value = true;
};

const openPasswordModal = (user) => {
    form.value = { id: user.id, name: user.name, new_password: '' };
    errorMsg.value = '';
    successMsg.value = '';
    showPasswordModal.value = true;
};

// YENİ: Durum Değiştirme Modalını Açar
const openStatusModal = (user) => {
    if (user.id === currentUser.id) {
        alert('Kendi hesabınızı pasife alamazsınız.');
        return;
    }
    if (user.active && user.role === 'employee' && consultantFirmCount(user) > 0) {
        openHandoverModal(user, true);
        return;
    }
    selectedUserForStatus.value = user;
    showStatusModal.value = true;
};

// Yeni Kullanıcı Kaydet
const saveUser = async () => {
    if (!form.value.name) {
        errorMsg.value = 'Ad soyad zorunludur.';
        return;
    }

    isSaving.value = true;
    errorMsg.value = '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        const response = await fetch('/api/users', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify(form.value)
        });

        const data = await response.json();

        if (response.ok) {
            showAddModal.value = false;
            successMsg.value = `Kullanıcı başarıyla eklendi. Geçici Şifre: ${data.password}`;
            setTimeout(() => window.location.reload(), 5000);
        } else {
            errorMsg.value = data.message || data.error || 'Kullanıcı eklenemedi.';
        }
    } catch (err) {
        errorMsg.value = 'Sunucu hatası oluştu.';
    } finally {
        isSaving.value = false;
    }
};

// Kullanıcı Güncelle
const updateUser = async () => {
    isSaving.value = true;
    errorMsg.value = '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        const response = await fetch(`/api/users/${form.value.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({
                email: form.value.email,
                role: form.value.role,
                active: form.value.active,
                module_accesses: form.value.module_accesses
            })
        });

        if (response.ok) {
            showEditModal.value = false;
            window.location.reload();
        } else {
            const data = await response.json();
            errorMsg.value = data.error || data.message || 'Güncellenemedi.';
        }
    } catch (err) {
        errorMsg.value = 'Sunucu hatası oluştu.';
    } finally {
        isSaving.value = false;
    }
};

// Şifre Sıfırla
const resetPassword = async () => {
    isSaving.value = true;
    errorMsg.value = '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        const response = await fetch(`/api/users/${form.value.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({
                resetPassword: true,
                new_password: form.value.new_password
            })
        });

        const data = await response.json();

        if (response.ok) {
            showPasswordModal.value = false;
            successMsg.value = `Şifre başarıyla sıfırlandı. Yeni Şifre: ${data.password}`;
            setTimeout(() => window.location.reload(), 5000);
        } else {
            errorMsg.value = data.error || data.message || 'Şifre sıfırlanamadı.';
        }
    } catch (err) {
        errorMsg.value = 'Sunucu hatası oluştu.';
    } finally {
        isSaving.value = false;
    }
};

// YENİ: Durumu Onayla ve API'ye Gönder
const confirmStatusChange = async () => {
    if (!selectedUserForStatus.value) return;
    
    isSaving.value = true;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    try {
        const response = await fetch(`/api/users/${selectedUserForStatus.value.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ active: !selectedUserForStatus.value.active })
        });
        
        if (response.ok) {
            showStatusModal.value = false;
            window.location.reload();
        } else {
            const data = await response.json();
            alert(data.error || 'Durum değiştirilemedi.');
        }
    } catch (err) { 
        alert('Sunucu hatası.'); 
    } finally {
        isSaving.value = false;
    }
};

const submitHandover = async () => {
    if (!selectedConsultant.value || !handoverForm.value.replacement_user_id || !handoverForm.value.reason.trim()) {
        errorMsg.value = 'Devir yapılacak kişiyi ve gerekçeyi belirtin.';
        return;
    }
    if (!selectedFirmIds.value.length) {
        errorMsg.value = 'Devredilecek en az bir tesis seçin.';
        return;
    }

    isSaving.value = true;
    errorMsg.value = '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        const response = await fetch(`/api/users/${selectedConsultant.value.id}/consultant-handover`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({
                replacement_user_id: handoverForm.value.replacement_user_id === '__unassigned__'
                    ? null
                    : Number(handoverForm.value.replacement_user_id),
                reason: handoverForm.value.reason.trim(),
                deactivate: handoverForm.value.deactivate && allFirmsSelected.value,
                firm_ids: selectedFirmIds.value
            })
        });

        const data = await response.json();
        if (response.ok) {
            showHandoverModal.value = false;
            successMsg.value = `${data.transferred} tesis devredildi.`;
            window.location.reload();
        } else {
            errorMsg.value = data.message || data.error || 'Tesis devri tamamlanamadı.';
        }
    } catch (err) {
        errorMsg.value = 'Sunucu hatası oluştu.';
    } finally {
        isSaving.value = false;
    }
};
</script>

<template>
    <Head title="Kullanıcılar ve Erişimler" />

    <div class="w-full space-y-4 pb-20">
        
        <!-- Başarı Mesajı -->
        <div v-if="successMsg" class="fixed top-4 right-4 z-[100] bg-emerald-50 border border-emerald-200 text-emerald-800 px-6 py-4 rounded-xl shadow-lg font-bold flex items-center space-x-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>{{ successMsg }}</span>
        </div>

        <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Kullanıcılar ve Erişimler</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-1 text-xs">Sistemdeki danışmanları ve yöneticileri yönetin.</p>
            </div>
            
            <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
                <div class="relative w-full sm:w-64">
                    <svg class="w-4 h-4 absolute left-3 top-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input v-model="search" type="text" placeholder="İsim veya kullanıcı adı ara..." class="w-full pl-9 pr-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                </div>

                <button @click="openAddModal" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition w-full sm:w-auto whitespace-nowrap">
                    + Yeni Kullanıcı
                </button>
            </div>
        </div>

        <div class="bg-white dark:bg-[#1e293b] rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden flex flex-col">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider font-semibold">
                            <th class="px-4 py-3">Ad Soyad</th>
                            <th class="px-4 py-3">Kullanıcı Adı</th>
                            <th class="px-4 py-3">E-Posta</th>
                            <th class="px-4 py-3">Rol</th>
                            <th class="px-4 py-3 text-center">Durum</th>
                            <th class="px-4 py-3 text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="user in filteredUsers" :key="user.id" :class="['hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors text-xs', !user.active ? 'opacity-50 bg-slate-50 dark:bg-slate-800/20' : '']">
                            <td class="px-4 py-3 font-bold text-slate-800 dark:text-slate-200">{{ user.name }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 font-mono text-[11px]">{{ user.username }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ user.email || '-' }}</td>
                            <td class="px-4 py-3">
                                <span v-if="user.role === 'admin'" class="px-2 py-1 rounded text-[10px] font-bold bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">Yönetici</span>
                                <span v-else class="px-2 py-1 rounded text-[10px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">Danışman</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="!user.active" class="px-2 py-1 rounded text-[10px] font-bold bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300">Pasif (Arşiv)</span>
                                <span v-else class="px-2 py-1 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">Aktif</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <button v-if="user.role === 'employee' && consultantFirmCount(user) > 0" @click="openHandoverModal(user)" class="text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-500/10 px-2 py-1 rounded-md transition-colors text-[10px] font-semibold whitespace-nowrap" :title="`${consultantFirmCount(user)} tesisi devret`">
                                        Devir ({{ consultantFirmCount(user) }})
                                    </button>
                                    <button @click="openPasswordModal(user)" class="text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 p-1.5 rounded-md transition-colors" title="Şifre Sıfırla">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                                    </button>
                                    <button @click="openEditModal(user)" class="text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 p-1.5 rounded-md transition-colors" title="Düzenle">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </button>
                                    <button @click="openStatusModal(user)" :disabled="user.id === currentUser.id" class="text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 p-1.5 rounded-md transition-colors disabled:opacity-30 disabled:cursor-not-allowed" :title="user.active ? 'Pasife Al (Arşivle)' : 'Aktifleştir'">
                                        <svg v-if="user.active" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredUsers.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">Kullanıcı bulunamadı.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <section class="bg-white dark:bg-[#1e293b] rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700">
                <h2 class="text-sm font-bold text-slate-800 dark:text-white">Danışman Görev Geçmişi</h2>
            </div>
            <div v-if="consultantHistory?.length" class="divide-y divide-slate-100 dark:divide-slate-700/50">
                <div v-for="entry in consultantHistory" :key="entry.id" class="px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 text-xs">
                    <p class="text-slate-700 dark:text-slate-300">{{ entry.description }}</p>
                    <p class="shrink-0 text-[10px] text-slate-500">{{ entry.actor }} · {{ new Date(entry.created_at).toLocaleString('tr-TR') }}</p>
                </div>
            </div>
            <p v-else class="px-4 py-4 text-xs text-slate-500">Henüz danışman görevlendirme kaydı bulunmuyor.</p>
        </section>

        <!-- YENİ KULLANICI MODALI -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
            <div class="max-h-[calc(100dvh-2rem)] w-full max-w-md overflow-y-auto overscroll-contain rounded-xl border border-slate-200 bg-white p-6 text-sm shadow-xl dark:border-slate-700 dark:bg-[#1e293b]">
                <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-700 pb-3 mb-4">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white">Yeni Kullanıcı Ekle</h3>
                    <button @click="showAddModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div v-if="errorMsg" class="mb-4 p-3 bg-rose-50 text-rose-700 text-xs rounded-lg border border-rose-200">{{ errorMsg }}</div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-slate-300">Ad Soyad <span class="text-rose-500">*</span></label>
                        <input type="text" v-model="form.name" placeholder="Örn: Ahmet Yılmaz" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm uppercase">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-slate-300">Kullanıcı Adı</label>
                        <input type="text" v-model="form.username" placeholder="Boş bırakırsanız ad soyaddan oluşturulur" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm lowercase">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-slate-300">E-Posta (Opsiyonel)</label>
                        <input type="email" v-model="form.email" placeholder="ahmet@uluscevre.com" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-slate-300">Rol</label>
                        <select v-model="form.role" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                            <option value="employee">Danışman</option>
                            <option value="admin">Sistem Yöneticisi</option>
                        </select>
                    </div>
                    <div v-if="form.role === 'employee'" class="space-y-2 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <label class="block text-xs font-semibold dark:text-slate-300">Modül erişimleri</label>
                        <p class="text-[10px] text-slate-500">Erişimi olmayan kullanıcı ilgili modülü hiç görmez.</p>
                        <div v-for="module in modules" :key="module.id" class="grid grid-cols-[1fr_auto] items-center gap-3 border-t border-slate-100 pt-2 first:border-t-0 first:pt-0 dark:border-slate-700">
                            <div>
                                <p class="text-xs font-medium dark:text-slate-200">{{ module.name }}</p>
                                <p v-if="!module.is_active" class="text-[10px] text-amber-600">Henüz kullanıma açılmadı</p>
                            </div>
                            <select v-if="module.key === 'environment'" v-model="form.module_accesses[module.id]" class="rounded-md border border-slate-300 bg-white p-1.5 text-xs dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                                <option value="">Erişim yok</option>
                                <option value="advisor">Danışman</option>
                                <option value="viewer">Görüntüleyici</option>
                            </select>
                            <select v-else :value="form.module_accesses[module.id] ? 'granted' : ''" class="rounded-md border border-slate-300 bg-white p-1.5 text-xs dark:border-slate-600 dark:bg-slate-700 dark:text-white" @change="form.module_accesses[module.id] = $event.target.value === 'granted' ? 'advisor' : ''">
                                <option value="">Erişim yok</option>
                                <option value="granted">Erişim var</option>
                            </select>
                        </div>
                    </div>
                    <p v-else class="rounded-lg bg-indigo-50 p-2 text-[10px] text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">Sistem yöneticileri tüm modüllere tam erişime sahiptir.</p>
                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-slate-300">Şifre (Boş bırakılırsa otomatik atanır)</label>
                        <input type="text" v-model="form.password" placeholder="En az 6 karakter" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                    </div>
                </div>

                <div class="flex justify-end space-x-2 mt-6">
                    <button @click="showAddModal = false" class="px-4 py-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition">İptal</button>
                    <button @click="saveUser" :disabled="isSaving" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition disabled:opacity-50">
                        {{ isSaving ? 'Ekleniyor...' : 'Kullanıcı Ekle' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- KULLANICI DÜZENLEME MODALI -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
            <div class="max-h-[calc(100dvh-2rem)] w-full max-w-md overflow-y-auto overscroll-contain rounded-xl border border-slate-200 bg-white p-6 text-sm shadow-xl dark:border-slate-700 dark:bg-[#1e293b]">
                <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-700 pb-3 mb-4">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white">Kullanıcıyı Düzenle</h3>
                    <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div v-if="errorMsg" class="mb-4 p-3 bg-rose-50 text-rose-700 text-xs rounded-lg border border-rose-200">{{ errorMsg }}</div>

                <div class="space-y-4">
                    <div class="bg-slate-50 dark:bg-slate-800/50 p-3 rounded-lg mb-4">
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ form.name }}</p>
                        <p class="text-[10px] text-slate-500">{{ form.username }}</p>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-slate-300">E-Posta</label>
                        <input type="email" v-model="form.email" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-slate-300">Rol</label>
                        <select v-model="form.role" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm" :disabled="form.id === currentUser.id">
                            <option value="employee">Danışman</option>
                            <option value="admin">Sistem Yöneticisi</option>
                        </select>
                        <p v-if="form.id === currentUser.id" class="text-[10px] text-amber-600 mt-1">Kendi rolünüzü değiştiremezsiniz.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-slate-300">Durum</label>
                        <select v-model="form.active" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm" :disabled="form.id === currentUser.id">
                            <option :value="true">Aktif (Giriş Yapabilir)</option>
                            <option :value="false">Pasif (Giriş Yapamaz)</option>
                        </select>
                    </div>
                    <div v-if="form.role === 'employee'" class="space-y-2 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <label class="block text-xs font-semibold dark:text-slate-300">Modül erişimleri</label>
                        <p class="text-[10px] text-slate-500">Erişimi olmayan kullanıcı ilgili modülü hiç görmez.</p>
                        <div v-for="module in modules" :key="module.id" class="grid grid-cols-[1fr_auto] items-center gap-3 border-t border-slate-100 pt-2 first:border-t-0 first:pt-0 dark:border-slate-700">
                            <div>
                                <p class="text-xs font-medium dark:text-slate-200">{{ module.name }}</p>
                                <p v-if="!module.is_active" class="text-[10px] text-amber-600">Henüz kullanıma açılmadı</p>
                            </div>
                            <select v-if="module.key === 'environment'" v-model="form.module_accesses[module.id]" class="rounded-md border border-slate-300 bg-white p-1.5 text-xs dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                                <option value="">Erişim yok</option>
                                <option value="advisor">Danışman</option>
                                <option value="viewer">Görüntüleyici</option>
                            </select>
                            <select v-else :value="form.module_accesses[module.id] ? 'granted' : ''" class="rounded-md border border-slate-300 bg-white p-1.5 text-xs dark:border-slate-600 dark:bg-slate-700 dark:text-white" @change="form.module_accesses[module.id] = $event.target.value === 'granted' ? 'advisor' : ''">
                                <option value="">Erişim yok</option>
                                <option value="granted">Erişim var</option>
                            </select>
                        </div>
                    </div>
                    <p v-else class="rounded-lg bg-indigo-50 p-2 text-[10px] text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">Sistem yöneticileri tüm modüllere tam erişime sahiptir.</p>
                </div>

                <div class="flex justify-end space-x-2 mt-6">
                    <button @click="showEditModal = false" class="px-4 py-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition">İptal</button>
                    <button @click="updateUser" :disabled="isSaving" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition disabled:opacity-50">
                        {{ isSaving ? 'Kaydediliyor...' : 'Değişiklikleri Kaydet' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ŞİFRE SIFIRLAMA MODALI -->
        <div v-if="showPasswordModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
            <div class="max-h-[calc(100dvh-2rem)] w-full max-w-md overflow-y-auto overscroll-contain rounded-xl border border-slate-200 bg-white p-6 text-sm shadow-xl dark:border-slate-700 dark:bg-[#1e293b]">
                <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-700 pb-3 mb-4">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white">Şifre Sıfırla</h3>
                    <button @click="showPasswordModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div v-if="errorMsg" class="mb-4 p-3 bg-rose-50 text-rose-700 text-xs rounded-lg border border-rose-200">{{ errorMsg }}</div>

                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-3 rounded-lg mb-4">
                    <p class="text-xs text-amber-800 dark:text-amber-400"><strong>{{ form.name }}</strong> kullanıcısının şifresi sıfırlanacak. Kullanıcı bir sonraki girişinde bu şifreyi kullanmak zorunda kalacaktır.</p>
                </div>

                <div>
                    <label class="block text-xs font-medium mb-1 dark:text-slate-300">Yeni Şifre (Boş bırakılırsa otomatik atanır)</label>
                    <input type="text" v-model="form.new_password" placeholder="En az 6 karakter" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                </div>

                <div class="flex justify-end space-x-2 mt-6">
                    <button @click="showPasswordModal = false" class="px-4 py-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition">İptal</button>
                    <button @click="resetPassword" :disabled="isSaving" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg transition disabled:opacity-50">
                        {{ isSaving ? 'Sıfırlanıyor...' : 'Şifreyi Sıfırla' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- YENİ: DURUM DEĞİŞTİRME ONAY MODALI (Siyah uyarı yerine) -->
        <div v-if="showStatusModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-[#1e293b] rounded-xl p-6 w-full max-w-sm shadow-xl border border-slate-200 dark:border-slate-700 text-sm text-center">
                
                <div :class="['w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4', selectedUserForStatus?.active ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-600']">
                    <svg v-if="selectedUserForStatus?.active" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <svg v-else class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>

                <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-2">
                    {{ selectedUserForStatus?.active ? 'Kullanıcıyı Pasife Al' : 'Kullanıcıyı Aktifleştir' }}
                </h3>
                
                <p class="text-slate-600 dark:text-slate-400 mb-6 text-xs">
                    <strong>{{ selectedUserForStatus?.name }}</strong> adlı kullanıcının durumunu <strong>{{ selectedUserForStatus?.active ? 'Pasif (Arşiv)' : 'Aktif' }}</strong> olarak değiştirmek üzeresiniz. Bu işlem Sistem Loglarına kaydedilecektir.
                </p>

                <div class="flex space-x-2">
                    <button @click="showStatusModal = false" class="flex-1 py-2.5 text-slate-600 dark:text-slate-300 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 rounded-lg transition font-medium">
                        Vazgeç
                    </button>
                    <button @click="confirmStatusChange" :disabled="isSaving" :class="['flex-1 py-2.5 text-white rounded-lg transition font-medium disabled:opacity-50 flex items-center justify-center', selectedUserForStatus?.active ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700']">
                        <svg v-if="isSaving" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        {{ isSaving ? 'İşleniyor...' : 'Evet, Onayla' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showHandoverModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-[#1e293b] rounded-xl p-6 w-full max-w-lg shadow-xl border border-slate-200 dark:border-slate-700 text-sm max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-700 pb-3 mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800 dark:text-white">Danışman Görev Devri</h3>
                        <p class="text-xs text-slate-500 mt-1">{{ selectedConsultant?.name }} · {{ selectedFirmIds.length }} / {{ consultantFirmList.length }} tesis seçili</p>
                    </div>
                    <button @click="showHandoverModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" aria-label="Kapat">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div v-if="errorMsg" class="mb-4 p-3 bg-rose-50 text-rose-700 text-xs rounded-lg border border-rose-200">{{ errorMsg }}</div>
                <div class="space-y-4">
                    <div>
                        <div class="mb-1 flex items-center justify-between gap-2">
                            <label class="block text-xs font-medium dark:text-slate-300" for="handover-firm-search">Devredilecek tesisler</label>
                            <button type="button" @click="toggleAllFirms" class="text-[11px] font-semibold text-indigo-600 hover:underline dark:text-indigo-400">
                                {{ allFirmsSelected ? 'Seçimi kaldır' : 'Tümünü seç' }}
                            </button>
                        </div>
                        <input id="handover-firm-search" v-model="firmSearch" type="search" placeholder="Tesis ara" class="mb-2 w-full rounded-md border border-slate-300 bg-white p-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                        <div class="max-h-48 space-y-1 overflow-y-auto rounded-md border border-slate-200 p-2 dark:border-slate-600">
                            <label v-for="firm in visibleConsultantFirms" :key="firm.id" class="flex cursor-pointer items-start gap-2 rounded px-1 py-1.5 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800">
                                <input type="checkbox" class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" :checked="selectedFirmIds.includes(firm.id)" @change="toggleFirm(firm.id)">
                                <span>{{ firm.name }}</span>
                            </label>
                            <p v-if="!visibleConsultantFirms.length" class="px-1 py-3 text-center text-xs text-slate-500">Bu danışmana ait tesis bulunamadı.</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-slate-300">Yeni danışman</label>
                        <select v-model="handoverForm.replacement_user_id" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                            <option value="">Danışman seçin...</option>
                            <option v-for="consultant in activeConsultants" :key="consultant.id" :value="String(consultant.id)">{{ consultant.name }}</option>
                            <option value="__unassigned__">Atanmamış bırak</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-slate-300">Devir gerekçesi</label>
                        <textarea v-model="handoverForm.reason" rows="3" maxlength="500" placeholder="Örn: görev değişikliği veya işten ayrılma" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm"></textarea>
                    </div>
                    <label v-if="selectedConsultant?.active" :class="['flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300', allFirmsSelected ? '' : 'opacity-60']">
                        <input v-model="handoverForm.deactivate" :disabled="!allFirmsSelected" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 disabled:cursor-not-allowed">
                        Devir tamamlanınca danışmanı pasife al
                    </label>
                    <p v-if="selectedConsultant?.active && !allFirmsSelected" class="text-[11px] text-slate-500">Pasife almak için danışmanın bütün tesisleri bu devirde seçilmelidir. Seçilmeyen tesisler mevcut danışmanda kalır.</p>
                    <p v-if="handoverForm.deactivate" class="text-[11px] text-amber-700 dark:text-amber-400">Danışmanın hesabı kapatılacak; işlem ve tesis devirleri geçmişe kaydedilecek.</p>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button @click="showHandoverModal = false" class="px-4 py-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition">Vazgeç</button>
                    <button @click="submitHandover" :disabled="isSaving || !selectedFirmIds.length" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition disabled:opacity-50">
                        {{ isSaving ? 'Kaydediliyor...' : handoverForm.deactivate ? 'Devret ve Pasife Al' : allFirmsSelected ? 'Tesisleri Devret' : `Seçilen ${selectedFirmIds.length} tesisi devret` }}
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>
