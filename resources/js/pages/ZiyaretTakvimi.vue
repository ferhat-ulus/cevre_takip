<script setup>
import { facilityDisplayName } from '../lib/facilityDisplayName';
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';

const props = defineProps({
    firms: { type: Array, default: () => [] },
    consultants: { type: Array, default: () => [] },
    holidays: { type: Array, default: () => [] },
    approvalRequests: { type: Array, default: () => [] },
    timezone: { type: String, default: 'Europe/Istanbul' },
    isAdmin: Boolean
});

const page = usePage();
const user = page.props.auth?.user;

const calendarEvents = ref([]);
const loading = ref(true);
const isUpdatingTransfer = ref(false);
const approvalRequests = ref([...props.approvalRequests]);
const reviewingApprovalId = ref(null);

const showAddModal = ref(false);
const isSaving = ref(false);
const errorMsg = ref('');
const sameDayConflict = ref(false);

const showEventModal = ref(false);
const selectedEvent = ref({ id: null, title: '', consultant: '', notes: '', ministryTransferStatus: 'untracked' });
const isDeleting = ref(false);
const showDeleteConfirm = ref(false); 
const currentTime = ref(Date.now());
let clockInterval;

const selectedConsultant = ref('all');

const form = ref({
    firm_id: '',
    visit_date: '',
    title: 'Tesis Ziyareti',
    notes: '',
    user_id: ''
});

const getAppDateParts = (date = new Date()) => {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: props.timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        hourCycle: 'h23'
    }).formatToParts(date);
    return Object.fromEntries(parts.map(part => [part.type, part.value]));
};
const weekdayOfDateString = (dateString) => new Date(`${dateString}T12:00:00Z`).getUTCDay();
const addDaysToDateString = (dateString, days) => {
    const date = new Date(`${dateString}T12:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);
    return date.toISOString().slice(0, 10);
};
const todayInAppTimezone = computed(() => {
    const parts = getAppDateParts(new Date(currentTime.value));
    return `${parts.year}-${parts.month}-${parts.day}`;
});
const tomorrowInAppTimezone = computed(() => {
    return addDaysToDateString(todayInAppTimezone.value, 1);
});
const canDeleteSelectedVisit = computed(() => {
    if (!selectedEvent.value.date || selectedEvent.value.date < todayInAppTimezone.value) return false;
    if (selectedEvent.value.date > todayInAppTimezone.value) return true;
    return Number(getAppDateParts(new Date(currentTime.value)).hour) < 16;
});

const showToast = ref(false);
const toastMessage = ref('');
const toastType = ref('error');

const triggerToast = (msg, type = 'error') => {
    toastMessage.value = msg;
    toastType.value = type;
    showToast.value = true;
    setTimeout(() => { showToast.value = false; }, 5000);
};
const visitDateLabel = (date) => new Intl.DateTimeFormat('tr-TR', { dateStyle: 'long' }).format(new Date(`${date}T12:00:00`));
const transferDateLabel = (date) => date
    ? new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeStyle: 'short', timeZone: props.timezone }).format(new Date(date))
    : '';
const transferStatusLabel = (status) => ({
    pending: 'Bakanlığa aktarılacak',
    stale: 'Güncelleme gerekli',
    entered: 'Bakanlığa aktarıldı',
    untracked: 'Eski kayıt · aktarım bilgisi yok',
}[status] || 'Bakanlığa aktarılacak');
const pendingTransferVisits = computed(() => props.isAdmin
    ? calendarEvents.value
        .filter(event => ['pending', 'stale'].includes(event.extendedProps?.ministry_transfer_status))
        .sort((a, b) => String(a.start).localeCompare(String(b.start)))
    : []);

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    locale: 'tr',
    firstDay: 1, 
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: props.isAdmin ? 'dayGridMonth,dayGridWeek' : ''
    },
    buttonText: { today: 'Bugün', month: 'Ay', week: 'Hafta' },
    events: calendarEvents.value,
    dateClick: handleDateClick,
    eventClick: handleEventClick,
    dayCellClassNames: (arg) => {
        const dateStr = arg.dateStr;
        const isHoliday = props.holidays.includes(dateStr);
        const isWeekend = weekdayOfDateString(dateStr) === 0 || weekdayOfDateString(dateStr) === 6;
        
        if (isHoliday) return ['bg-rose-100', 'text-rose-700', 'font-bold'];
        if (isWeekend) return ['bg-slate-100', 'text-slate-400'];
        return [];
    }
}));

const fetchEvents = async () => {
    loading.value = true;
    try {
        const url = selectedConsultant.value === 'all' 
            ? '/api/visits' 
            : `/api/visits?consultant=${selectedConsultant.value}`;
            
        const response = await fetch(url);
        const data = await response.json();
        calendarEvents.value = data.events || [];
    } catch (err) {
        console.error('Ziyaretler yüklenemedi', err);
    } finally {
        loading.value = false;
    }
};

watch(selectedConsultant, () => {
    fetchEvents();
});

watch(() => props.approvalRequests, (requests) => {
    approvalRequests.value = [...(requests || [])];
});

watch(() => [form.value.visit_date, form.value.firm_id], () => {
    sameDayConflict.value = false;
});

const handleDateClick = (arg) => {
    if (arg.dateStr <= todayInAppTimezone.value) {
        triggerToast('Ziyaret yalnızca yarın ve sonraki tarihler için planlanabilir.', 'error');
        return;
    }

    const weekday = weekdayOfDateString(arg.dateStr);
    const isWeekend = weekday === 0 || weekday === 6;
    
    if (isWeekend) {
        triggerToast('Hafta sonları ziyaret planlanamaz.', 'error');
        return;
    }
    
    if (props.holidays.includes(arg.dateStr)) {
        triggerToast('Seçtiğiniz gün resmi tatildir, ziyaret planlanamaz.', 'error');
        return;
    }

    openNewVisitModal(arg.dateStr);
};

const openNewVisitModal = (dateStr = '') => {
    const selectedDate = dateStr || tomorrowInAppTimezone.value;
    if (selectedDate <= todayInAppTimezone.value) {
        triggerToast('Ziyaret yalnızca yarın ve sonraki tarihler için planlanabilir.', 'error');
        return;
    }

    form.value = { 
        firm_id: '', 
        visit_date: selectedDate,
        title: 'Tesis Ziyareti', 
        notes: '',
        user_id: props.isAdmin ? '' : user.id
    };
    errorMsg.value = '';
    sameDayConflict.value = false;
    showAddModal.value = true;
};

const openVisitDetails = (event) => {
    const date = event.startStr?.slice(0, 10) || String(event.start || '').slice(0, 10);
    const extendedProps = event.extendedProps || {};
    selectedEvent.value = {
        id: event.id,
        title: event.title,
        consultant: extendedProps.consultant,
        notes: extendedProps.notes,
        date,
        ministryTransferStatus: extendedProps.ministry_transfer_status || 'untracked',
        ministryEnteredAt: extendedProps.ministry_entered_at || null,
        ministryEnteredBy: extendedProps.ministry_entered_by || null,
    };
    showDeleteConfirm.value = false; 
    showEventModal.value = true;
};

const handleEventClick = (info) => openVisitDetails(info.event);

const setMinistryTransfer = async (transferred) => {
    if (!props.isAdmin || !selectedEvent.value.id || isUpdatingTransfer.value) return;
    isUpdatingTransfer.value = true;
    try {
        const response = await fetch(`/api/visits/${selectedEvent.value.id}/ministry-transfer`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
            },
            body: JSON.stringify({ transferred }),
        });
        const data = await response.json();
        if (!response.ok) {
            triggerToast(data.error || data.message || 'Aktarım durumu kaydedilemedi.', 'error');
            return;
        }
        selectedEvent.value.ministryTransferStatus = data.status;
        selectedEvent.value.ministryEnteredAt = data.ministry_entered_at;
        selectedEvent.value.ministryEnteredBy = data.ministry_entered_by;
        await fetchEvents();
        triggerToast(transferred ? 'Bakanlık aktarımı kaydedildi.' : 'Kayıt yeniden aktarım listesine alındı.', 'success');
    } catch (err) {
        triggerToast('Sunucu hatası oluştu.', 'error');
    } finally {
        isUpdatingTransfer.value = false;
    }
};

const askDelete = () => {
    if (!canDeleteSelectedVisit.value) {
        triggerToast(
            selectedEvent.value.date < todayInAppTimezone.value
                ? 'Geçmiş tarihli ziyaretler silinemez.'
                : 'Bugün tarihli ziyaretler saat 16.00’dan sonra silinemez.',
            'error'
        );
        return;
    }
    showDeleteConfirm.value = true;
};

const confirmDelete = async () => {
    if (!canDeleteSelectedVisit.value) {
        showDeleteConfirm.value = false;
        triggerToast(
            selectedEvent.value.date < todayInAppTimezone.value
                ? 'Geçmiş tarihli ziyaretler silinemez.'
                : 'Bugün tarihli ziyaretler saat 16.00’dan sonra silinemez.',
            'error'
        );
        return;
    }

    isDeleting.value = true;
    try {
        const response = await fetch(`/api/visits/${selectedEvent.value.id}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        });
        if (response.ok) {
            showEventModal.value = false;
            fetchEvents();
            triggerToast('Ziyaret başarıyla silindi.', 'success');
        } else {
            triggerToast('Ziyaret silinemedi.', 'error');
        }
    } catch (err) {
        triggerToast('Sunucu hatası oluştu.', 'error');
    } finally {
        isDeleting.value = false;
        showDeleteConfirm.value = false;
    }
};

const saveVisit = async ({ requestApproval = false, adminOverride = false } = {}) => {
    if (!form.value.firm_id) {
        errorMsg.value = 'Lütfen bir tesis seçin.';
        return;
    }
    if (!form.value.visit_date) {
        errorMsg.value = 'Lütfen bir tarih seçin.';
        return;
    }
    if (form.value.visit_date <= todayInAppTimezone.value) {
        errorMsg.value = 'Ziyaret yalnızca yarın ve sonraki tarihler için planlanabilir.';
        return;
    }

    if (requestApproval && !props.isAdmin && !form.value.notes.trim()) {
        errorMsg.value = 'Yöneticiye göndermeden önce açıklama notu yazın.';
        return;
    }

    isSaving.value = true;
    errorMsg.value = '';

    try {
        const response = await fetch('/api/visits', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({
                ...form.value,
                request_approval: requestApproval && !props.isAdmin,
                admin_override: adminOverride && props.isAdmin,
            })
        });

        const data = await response.json();

        if (data.same_day_conflict) {
            sameDayConflict.value = true;
            errorMsg.value = '';
        } else if (data.conflict_cleared) {
            sameDayConflict.value = false;
            errorMsg.value = data.error || '';
        } else if (data.approval_requested) {
            showAddModal.value = false;
            triggerToast('Onay isteğiniz yöneticiye iletildi. Atama, onaylandıktan sonra takvime eklenecek.', 'success');
        } else if (response.ok) {
            showAddModal.value = false;
            fetchEvents();
            triggerToast('Ziyaret başarıyla planlandı.', 'success');
        } else {
            errorMsg.value = data.error || data.message || 'Ziyaret eklenemedi.';
        }
    } catch (err) {
        errorMsg.value = 'Sunucu hatası oluştu.';
    } finally {
        isSaving.value = false;
    }
};

const decideApproval = async (approval, decision) => {
    reviewingApprovalId.value = approval.id;
    try {
        const response = await fetch(`/api/visits/approvals/${approval.id}/${decision}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
            },
            body: JSON.stringify({}),
        });
        const data = await response.json();
        if (data.status === 'expired') {
            approvalRequests.value = approvalRequests.value.filter(item => item.id !== approval.id);
        }
        if (!response.ok) {
            triggerToast(data.error || data.message || 'İstek sonuçlandırılamadı.', 'error');
            return;
        }
        approvalRequests.value = approvalRequests.value.filter(item => item.id !== approval.id);
        if (decision === 'approve') fetchEvents();
        triggerToast(decision === 'approve' ? 'İstek onaylandı ve takvime eklendi.' : 'İstek reddedildi.', 'success');
    } catch (err) {
        triggerToast('Sunucu hatası oluştu.', 'error');
    } finally {
        reviewingApprovalId.value = null;
    }
};

onMounted(() => {
    currentTime.value = Date.now();
    clockInterval = window.setInterval(() => {
        currentTime.value = Date.now();
    }, 30_000);
    fetchEvents();
});

onUnmounted(() => {
    window.clearInterval(clockInterval);
});
</script>

<template>
    <Head title="Ziyaret Takvimi" />

    <div class="w-full space-y-4 pb-20 relative">
        
        <div v-if="showToast" class="fixed top-4 right-4 z-[100] animate-fade-in-down">
            <div :class="['px-6 py-4 rounded-xl shadow-lg border text-sm font-bold flex items-center space-x-3', 
                toastType === 'error' ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700']">
                <svg v-if="toastType === 'error'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>{{ toastMessage }}</span>
            </div>
        </div>

        <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Ziyaret Takvimi</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-1 text-xs">Tesis ziyaretlerinizi planlayın ve takip edin.</p>
            </div>
            
            <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
                <div v-if="isAdmin" class="flex items-center w-full sm:w-auto bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 shadow-sm">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 mr-2 whitespace-nowrap">Danışman:</span>
                    <select v-model="selectedConsultant" class="w-full bg-transparent border-none text-sm font-medium text-indigo-600 dark:text-indigo-400 outline-none cursor-pointer">
                        <option value="all" class="dark:bg-gray-800">Tümü (Hepsi)</option>
                        <option v-for="c in consultants" :key="c.id" :value="c.id" class="dark:bg-gray-800">{{ c.name }}</option>
                    </select>
                </div>

                <button @click="openNewVisitModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition w-full sm:w-auto whitespace-nowrap">
                    + Yeni Ziyaret
                </button>
            </div>
        </div>

        <section v-if="isAdmin && approvalRequests.length" class="space-y-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-bold">Aynı gün atama için yönetici onayı bekliyor</h2>
                <span class="rounded-full bg-amber-200 px-2.5 py-1 text-xs font-bold text-amber-900 dark:bg-amber-400/20 dark:text-amber-100">{{ approvalRequests.length }} bekleyen</span>
            </div>
            <article v-for="approval in approvalRequests" :key="approval.id" class="rounded-lg border border-amber-200 bg-white/80 p-3 dark:border-amber-500/30 dark:bg-slate-900/30">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-1 text-sm">
                        <p class="font-semibold">{{ visitDateLabel(approval.visit_date) }} · {{ facilityDisplayName(approval.firm_name) }}</p>
                        <p class="text-xs opacity-80">Atanacak danışman: {{ approval.assigned_user }}<template v-if="approval.requested_by !== approval.assigned_user"> · İsteği gönderen: {{ approval.requested_by }}</template></p>
                        <p class="pt-1 text-xs font-semibold">Yöneticiye iletilen not</p>
                        <p class="whitespace-pre-wrap text-sm">{{ approval.notes }}</p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button type="button" :disabled="reviewingApprovalId === approval.id" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800 disabled:opacity-50" @click="decideApproval(approval, 'approve')">Onayla</button>
                        <button type="button" :disabled="reviewingApprovalId === approval.id" class="rounded-lg border border-rose-300 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-500/40 dark:text-rose-300 dark:hover:bg-rose-500/10 disabled:opacity-50" @click="decideApproval(approval, 'reject')">Reddet</button>
                    </div>
                </div>
            </article>
        </section>

        <details v-if="isAdmin" class="rounded-xl border border-rose-300 bg-rose-50 text-rose-950 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-100">
            <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 px-4 py-3 font-bold">
                <span>Bakanlık sistemine girilecek veya güncellenecek kayıtlar</span>
                <span class="rounded-full bg-rose-200 px-2.5 py-1 text-xs font-bold text-rose-900 dark:bg-rose-400/20 dark:text-rose-100">{{ pendingTransferVisits.length }} kayıt</span>
            </summary>
            <div v-if="pendingTransferVisits.length" class="max-h-64 space-y-1 overflow-y-auto border-t border-rose-200 px-3 py-2 dark:border-rose-500/30">
                <button v-for="event in pendingTransferVisits" :key="event.id" type="button"
                        class="flex w-full flex-col gap-0.5 rounded-lg px-2 py-2 text-left text-sm hover:bg-rose-100 dark:hover:bg-rose-500/10 sm:flex-row sm:items-center sm:justify-between"
                        @click="openVisitDetails(event)">
                    <span><strong>{{ visitDateLabel(String(event.start).slice(0, 10)) }}</strong> · {{ facilityDisplayName(event.extendedProps.firm_name) }} · {{ event.extendedProps.consultant }}</span>
                    <span :class="event.extendedProps.ministry_transfer_status === 'stale' ? 'font-bold text-orange-700 dark:text-orange-300' : 'font-semibold'">
                        {{ transferStatusLabel(event.extendedProps.ministry_transfer_status) }}
                    </span>
                </button>
            </div>
            <p v-else class="border-t border-rose-200 px-4 py-3 text-sm dark:border-rose-500/30">Bekleyen güncelleme yok; takvime eklenen yeni kayıtlar burada listelenecek.</p>
        </details>

        <div class="bg-white dark:bg-[#1e293b] p-6 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="mb-3 flex flex-wrap items-center gap-2 text-xs text-slate-600 dark:text-slate-300" aria-label="Takvim açıklaması">
                <template v-if="isAdmin">
                    <span class="inline-block h-3 w-3 rounded-sm bg-amber-600"></span>
                    <span>Not eklenmiş</span>
                    <span class="ml-2 inline-block h-3 w-3 rounded-sm bg-indigo-600"></span>
                    <span>Not yok</span>
                    <span class="ml-2 inline-block h-3 w-3 rounded-sm border-2 border-rose-700 bg-white dark:bg-slate-800"></span>
                    <span>Aktarılacak</span>
                    <span class="ml-2 inline-block h-3 w-3 rounded-sm border-2 border-orange-800 bg-white dark:bg-slate-800"></span>
                    <span>Güncelleme gerekli</span>
                    <span class="ml-2 inline-block h-3 w-3 rounded-sm border-2 border-emerald-700 bg-white dark:bg-slate-800"></span>
                    <span>Aktarıldı</span>
                </template>
                <template v-else>
                    <span class="inline-block h-3 w-3 rounded-sm bg-amber-600"></span>
                    <span>Not eklenmiş ziyaret</span>
                </template>
            </div>
            <div v-if="loading" class="text-center py-10 text-slate-500">Takvim Yükleniyor...</div>
            <FullCalendar v-else :options="calendarOptions" class="custom-calendar" />
        </div>

        <!-- 1. YENİ ZİYARET MODALI -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-[#1e293b] rounded-xl p-6 w-full max-w-md shadow-xl border border-slate-200 dark:border-slate-700 text-sm">
                <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-700 pb-3 mb-4">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white">Yeni Ziyaret Planla</h3>
                    <button @click="showAddModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div v-if="errorMsg" class="mb-4 p-3 bg-rose-50 text-rose-700 text-xs rounded-lg border border-rose-200">{{ errorMsg }}</div>
                <div v-if="sameDayConflict" class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-950 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
                    <p class="font-bold">Takvim ataması aynı güne denk geliyor.</p>
                    <p v-if="isAdmin" class="mt-1">Yönetici olarak istisnayı onaylayıp atamayı kaydedebilirsiniz.</p>
                    <p v-else class="mt-1">Bu istisna için yönetici onayı gerekiyor. İstek gönderilirken açıklama notu zorunludur.</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-slate-300">Tarih <span class="text-rose-500">*</span></label>
                        <input type="date" v-model="form.visit_date" :min="tomorrowInAppTimezone" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                    </div>
                    
                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-slate-300">Tesis Seçin <span class="text-rose-500">*</span></label>
                        <select v-model="form.firm_id" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                            <option value="">Bir tesis seçin...</option>
                            <option v-for="f in firms" :key="f.id" :value="f.id">{{ facilityDisplayName(f.name) }} ({{ f.consultant }})</option>
                        </select>
                    </div>
                    
                    <div v-if="isAdmin">
                        <label class="block text-xs font-medium mb-1 dark:text-slate-300">Ziyareti Yapacak Danışman</label>
                        <select v-model="form.user_id" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                            <option value="">Kendim (Yönetici)</option>
                            <!-- FORMDAN ID GÖNDERİYORUZ -->
                            <option v-for="c in consultants" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-slate-300">Başlık</label>
                        <input type="text" v-model="form.title" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm">
                    </div>
                    
                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-slate-300">Notlar<span v-if="sameDayConflict && !isAdmin" class="text-rose-500"> *</span></label>
                        <textarea v-model="form.notes" rows="2" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-2 dark:text-white text-sm"></textarea>
                        <p v-if="sameDayConflict && !isAdmin" class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Yöneticiye neden aynı gün için ek atama gerektiğini açıklayın.</p>
                    </div>
                </div>

                <div class="flex justify-end space-x-2 mt-6">
                    <button @click="showAddModal = false" class="px-4 py-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition">İptal</button>
                    <button v-if="!sameDayConflict" @click="saveVisit()" :disabled="isSaving" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition disabled:opacity-50">
                        {{ isSaving ? 'Kaydediliyor...' : 'Kaydet' }}
                    </button>
                    <button v-else @click="isAdmin ? saveVisit({ adminOverride: true }) : saveVisit({ requestApproval: true })" :disabled="isSaving || (!isAdmin && !form.notes.trim())" class="px-4 py-2 bg-amber-700 hover:bg-amber-800 text-white rounded-lg transition disabled:opacity-50">
                        {{ isSaving ? 'İşleniyor...' : isAdmin ? 'Yönetici onayıyla kaydet' : 'Yöneticiye Sor' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- 2. ZİYARET DETAY VE İKİ AŞAMALI SİLME MODALI -->
        <div v-if="showEventModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-[#1e293b] rounded-xl p-6 w-full max-w-md shadow-xl border border-slate-200 dark:border-slate-700 text-sm">
                <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-700 pb-3 mb-4">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white">Ziyaret Detayı</h3>
                    <button @click="showEventModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="space-y-4 mb-6">
                    <div class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-lg border border-slate-100 dark:border-slate-700">
                        <div class="mb-2">
                            <span class="text-xs text-slate-500 dark:text-slate-400">Tesis / Başlık:</span>
                            <div class="font-bold text-slate-800 dark:text-white text-sm">{{ selectedEvent.title }}</div>
                        </div>
                        <div class="mb-2">
                            <span class="text-xs text-slate-500 dark:text-slate-400">Danışman:</span>
                            <div class="font-medium text-slate-700 dark:text-slate-300">{{ selectedEvent.consultant }}</div>
                        </div>
                        <div>
                            <span class="text-xs text-slate-500 dark:text-slate-400">Notlar:</span>
                            <div :class="['mt-1 rounded-md p-2 italic', selectedEvent.notes ? 'border border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100' : 'text-slate-600 dark:text-slate-400']">{{ selectedEvent.notes || 'Not girilmemiş.' }}</div>
                        </div>
                    </div>

                    <div v-if="isAdmin" :class="[
                        'rounded-lg border p-3',
                        selectedEvent.ministryTransferStatus === 'entered'
                            ? 'border-emerald-300 bg-emerald-50 text-emerald-950 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100'
                            : selectedEvent.ministryTransferStatus === 'stale'
                                ? 'border-orange-300 bg-orange-50 text-orange-950 dark:border-orange-500/40 dark:bg-orange-500/10 dark:text-orange-100'
                                : selectedEvent.ministryTransferStatus === 'untracked'
                                    ? 'border-slate-300 bg-slate-50 text-slate-900 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100'
                                    : 'border-rose-300 bg-rose-50 text-rose-950 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-100'
                    ]">
                        <p class="font-bold">{{ transferStatusLabel(selectedEvent.ministryTransferStatus) }}</p>
                        <p v-if="selectedEvent.ministryTransferStatus === 'entered' || selectedEvent.ministryTransferStatus === 'stale'" class="mt-1 text-xs">
                            Son aktarım: {{ transferDateLabel(selectedEvent.ministryEnteredAt) }} · {{ selectedEvent.ministryEnteredBy || 'Kullanıcı bilgisi yok' }}
                        </p>
                        <p v-else-if="selectedEvent.ministryTransferStatus === 'untracked'" class="mt-1 text-xs">Bu kayıt özellik eklenmeden önce oluşturulmuş; aktarım durumu bilinmiyor.</p>
                        <p v-else class="mt-1 text-xs">Yeni takvim kayıtları Bakanlık sistemine aktarıldıktan sonra burada işaretlenmelidir.</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button v-if="selectedEvent.ministryTransferStatus !== 'entered'" type="button" :disabled="isUpdatingTransfer"
                                    class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
                                    @click="setMinistryTransfer(true)">
                                {{ isUpdatingTransfer ? 'Kaydediliyor...' : selectedEvent.ministryTransferStatus === 'stale' ? 'Güncel hali aktarıldı' : 'Bakanlığa aktarıldı olarak işaretle' }}
                            </button>
                            <button v-if="selectedEvent.ministryTransferStatus === 'entered' || selectedEvent.ministryTransferStatus === 'stale'" type="button" :disabled="isUpdatingTransfer"
                                    class="rounded-lg border border-current/30 px-3 py-2 text-xs font-semibold hover:bg-white/50 disabled:opacity-50 dark:hover:bg-slate-900/30"
                                    @click="setMinistryTransfer(false)">
                                Yeniden aktarım listesine al
                            </button>
                            <button v-if="selectedEvent.ministryTransferStatus === 'untracked'" type="button" :disabled="isUpdatingTransfer"
                                    class="rounded-lg border border-current/30 px-3 py-2 text-xs font-semibold hover:bg-white/50 disabled:opacity-50 dark:hover:bg-slate-900/30"
                                    @click="setMinistryTransfer(false)">
                                Aktarılacak olarak takibe al
                            </button>
                        </div>
                    </div>
                    
                    <!-- DURUM 1: İlk Ekran (Silme İsteği) -->
                    <div v-if="!showDeleteConfirm" class="text-center pt-2">
                        <p class="text-xs text-slate-500 mb-3">Bu ziyaretle ilgili işlem yapmak istiyor musunuz?</p>
                        <button v-if="canDeleteSelectedVisit" @click="askDelete" class="w-full py-2 bg-rose-50 text-rose-600 hover:bg-rose-100 border border-rose-200 rounded-lg transition font-medium text-xs">
                            Ziyareti İptal Et / Sil
                        </button>
                        <p v-else class="text-xs text-slate-500">
                            {{ selectedEvent.date < todayInAppTimezone ? 'Geçmiş tarihli ziyaretler silinemez.' : 'Bugün tarihli ziyaretler saat 16.00’dan sonra silinemez.' }}
                        </p>
                    </div>

                    <!-- DURUM 2: İkinci Ekran (Kesin Onay) -->
                    <div v-if="showDeleteConfirm" class="bg-rose-50 dark:bg-rose-900/20 p-4 rounded-lg border border-rose-200 dark:border-rose-800/50 text-center">
                        <svg class="w-8 h-8 text-rose-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <p class="text-xs font-bold text-rose-700 dark:text-rose-400 mb-1">Emin misiniz?</p>
                        <p class="text-[11px] text-rose-600 dark:text-rose-300 mb-4">Bu işlem geri alınamaz. Ziyaret kalıcı olarak silinecektir.</p>
                        
                        <div class="flex space-x-2">
                            <button @click="showDeleteConfirm = false" class="flex-1 py-2 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-600 rounded-lg transition text-xs font-medium">
                                Vazgeç
                            </button>
                            <button @click="confirmDelete" :disabled="isDeleting || !canDeleteSelectedVisit" class="flex-1 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg transition disabled:opacity-50 text-xs font-medium flex items-center justify-center space-x-1">
                                <svg v-if="isDeleting" class="animate-spin h-3 w-3 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span>{{ isDeleting ? 'Siliniyor...' : 'Evet, Sil' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button @click="showEventModal = false" class="px-4 py-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition text-xs">Kapat</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
.custom-calendar {
    --fc-border-color: #e2e8f0;
    --fc-button-bg-color: #4f46e5;
    --fc-button-border-color: #4f46e5;
    --fc-button-hover-bg-color: #4338ca;
    --fc-button-hover-border-color: #4338ca;
    --fc-today-bg-color: #eff6ff;
}
.dark .custom-calendar {
    --fc-border-color: #334155;
    --fc-page-bg-color: #1e293b;
    --fc-neutral-bg-color: #0f172a;
    --fc-neutral-text-color: #94a3b8;
    --fc-today-bg-color: #1e1b4b;
    color: #e2e8f0;
}
.dark .fc-col-header-cell-cushion { color: #cbd5e1; }
.dark .fc-daygrid-day-number { color: #e2e8f0; }
.custom-calendar .fc-event.visit-has-note .fc-event-title::after { content: ' ✎'; font-weight: 700; }
.custom-calendar .fc-event[class*='visit-ministry-'] { border-width: 1px !important; border-left-width: 4px !important; }

@keyframes fade-in-down {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-down {
    animation: fade-in-down 0.3s ease-out forwards;
}
</style>
