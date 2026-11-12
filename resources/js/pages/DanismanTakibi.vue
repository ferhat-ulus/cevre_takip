<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    consultants: { type: Array, default: () => [] },
    unassignedFirms: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
});

const search = ref('');
const statusFilter = ref('active');
const assignmentFilter = ref('all');
const selectedConsultantId = ref(
    props.consultants.find((consultant) => consultant.active && !consultant.historical)?.id
    ?? props.consultants.find((consultant) => !consultant.historical)?.id
    ?? null
);
const showUnmatched = ref(false);
const historySearch = ref('');
const historyConsultantFilter = ref('');
const historyMovementFilter = ref('all');
const historyFrom = ref('');
const historyTo = ref('');

const consultantNameKey = (name) => (name ?? '')
    .trim()
    .toLocaleUpperCase('tr-TR')
    .replace(/İ/g, 'I')
    .replace(/[^\p{L}\p{N}]+/gu, ' ')
    .replace(/\s+/g, ' ')
    .trim();

const unknownConsultantKeys = new Set(['', 'BILINMIYOR', 'ATANMAMIS']);

const matchesSearch = (consultant, query) => {
    if (!query) return true;

    return [consultant.name, ...(consultant.aliases ?? [])].some((value) => value.toLocaleLowerCase('tr-TR').includes(query))
        || consultant.assignmentHistory.some((assignment) => [
            assignment.firm_name,
            assignment.received_from,
            assignment.handed_to,
        ].some((value) => value?.toLocaleLowerCase('tr-TR').includes(query)));
};

const accountConsultants = computed(() => {
    const query = search.value.trim().toLocaleLowerCase('tr-TR');

    return props.consultants.filter((consultant) => {
        if (consultant.historical) return false;
        if (statusFilter.value === 'active' && !consultant.active) return false;
        if (statusFilter.value === 'inactive' && consultant.active) return false;

        return matchesSearch(consultant, query);
    });
});

const unmatchedConsultants = computed(() => {
    const query = search.value.trim().toLocaleLowerCase('tr-TR');

    return props.consultants.filter((consultant) => consultant.historical && matchesSearch(consultant, query));
});

const selectedConsultant = computed(() =>
    props.consultants.find((consultant) => consultant.id === selectedConsultantId.value)
    ?? accountConsultants.value[0]
    ?? null
);

const selectedAssignments = computed(() => {
    const query = search.value.trim().toLocaleLowerCase('tr-TR');
    const assignments = selectedConsultant.value?.assignmentHistory ?? [];

    return assignments.filter((assignment) => {
        if (assignmentFilter.value === 'current' && !assignment.is_current) return false;
        if (assignmentFilter.value === 'past' && assignment.is_current) return false;
        if (!query) return true;

        return [
            assignment.firm_name,
            assignment.received_from,
            assignment.handed_to,
        ].some((value) => value?.toLocaleLowerCase('tr-TR').includes(query));
    });
});

const historyConsultantOptions = computed(() =>
    [...props.consultants]
        .sort((a, b) => a.name.localeCompare(b.name, 'tr-TR'))
        .map((consultant) => consultant.name)
);

const filteredHistory = computed(() => {
    const query = historySearch.value.trim().toLocaleLowerCase('tr-TR');

    return props.history.filter((entry) => {
        if (query && ![
            entry.firm_name,
            entry.from,
            entry.to,
            entry.reason,
            entry.actor,
            formatReason(entry),
        ].some((value) => value?.toLocaleLowerCase('tr-TR').includes(query))) return false;

        const selectedKey = consultantNameKey(historyConsultantFilter.value);
        const fromConsultant = consultantNameKey(entry.from);
        const toConsultant = consultantNameKey(entry.to);
        if (selectedKey && fromConsultant !== selectedKey && toConsultant !== selectedKey) return false;
        if (historyMovementFilter.value === 'received'
            && (selectedKey ? toConsultant !== selectedKey : unknownConsultantKeys.has(toConsultant))) return false;
        if (historyMovementFilter.value === 'left'
            && (selectedKey ? fromConsultant !== selectedKey : unknownConsultantKeys.has(fromConsultant))) return false;

        const month = (entry.history_date || entry.created_at?.slice(0, 7) || '').slice(0, 7);
        if ((historyFrom.value || historyTo.value) && !month) return false;
        if (historyFrom.value && month < historyFrom.value) return false;
        if (historyTo.value && month > historyTo.value) return false;

        return true;
    });
});

const formatDate = (value) => {
    if (!value) return '-';
    return new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
};

const formatHistoryDate = (entry) => {
    if (/^\d{4}-\d{2}$/.test(entry.history_date ?? '')) {
        const [year, month] = entry.history_date.split('-');
        return `${month}/${year}`;
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(entry.history_date ?? '')) {
        return new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeZone: 'UTC' })
            .format(new Date(`${entry.history_date}T00:00:00Z`));
    }

    if (entry.reason?.startsWith('CSV_IMPORT:')) return 'Tarih belirtilmemiş';

    return formatDate(entry.created_at);
};

const formatTenureDate = (value) => {
    if (!value) return 'Bilinmiyor';
    if (/^\d{4}-\d{2}$/.test(value)) {
        const [year, month] = value.split('-');
        return `${month}/${year}`;
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeZone: 'UTC' })
            .format(new Date(`${value}T00:00:00Z`));
    }

    return formatDate(value);
};

const formatReason = (entry) => {
    if (entry.reason?.startsWith('CSV_IMPORT:')) {
        return entry.history_date
            ? 'CSV dosyasından aktarıldı'
            : 'CSV dosyasından aktarıldı; kaynakta tarih belirtilmemiş';
    }

    return entry.reason || 'Belirtilmemiş';
};

const clearHistoryFilters = () => {
    historySearch.value = '';
    historyConsultantFilter.value = selectedConsultant.value?.name ?? '';
    historyMovementFilter.value = 'all';
    historyFrom.value = '';
    historyTo.value = '';
};

const selectConsultant = (consultant) => {
    selectedConsultantId.value = consultant.id;
    historyConsultantFilter.value = consultant.name;
};

watch(selectedConsultant, (consultant) => {
    if (consultant) historyConsultantFilter.value = consultant.name;
}, { immediate: true });

watch(search, (value) => {
    if (value.trim() && unmatchedConsultants.value.length) showUnmatched.value = true;
});
</script>

<template>
    <Head title="Danışman Takibi" />

    <div class="w-full space-y-4 pb-10">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">Danışman Takibi</h1>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Danışmanların mevcut tesis portföyünü, önceki atamaları ve görev devirlerini tek yerden izleyin.
                </p>
            </div>
            <Link href="/yonetim/kullanicilar" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-700">
                Kullanıcı ve devir yönetimi
            </Link>
        </header>

        <section aria-label="Danışman özeti" class="grid grid-cols-2 gap-2 xl:grid-cols-5">
            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-[#1e293b]">
                <p class="text-[11px] font-medium text-slate-500">Danışman</p>
                <p class="mt-1 text-lg font-bold text-slate-800 dark:text-white">{{ summary.consultants ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-[#1e293b]">
                <p class="text-[11px] font-medium text-slate-500">Aktif</p>
                <p class="mt-1 text-lg font-bold text-emerald-600">{{ summary.activeConsultants ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-[#1e293b]">
                <p class="text-[11px] font-medium text-slate-500">Pasif / Ayrılmış</p>
                <p class="mt-1 text-lg font-bold text-slate-600 dark:text-slate-300">{{ summary.inactiveConsultants ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-[#1e293b]">
                <p class="text-[11px] font-medium text-slate-500">Toplam Tesis</p>
                <p class="mt-1 text-lg font-bold text-indigo-600 dark:text-indigo-400">{{ summary.firms ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-[#1e293b]">
                <p class="text-[11px] font-medium text-slate-500">Atanmamış Tesis</p>
                <p class="mt-1 text-lg font-bold text-amber-600">{{ summary.unassignedFirms ?? 0 }}</p>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-[#1e293b]">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-3 dark:border-slate-700 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-800 dark:text-white">Danışman portföyleri</h2>
                    <p class="mt-0.5 text-[11px] text-slate-500">Danışman seçerek tesisleri hangi dönemde aldığını ve bıraktığını görüntüleyin.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Danışman veya tesis ara"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs outline-none focus:border-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white sm:w-56"
                    >
                    <select v-model="statusFilter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                        <option value="active">Aktif hesaplar</option>
                        <option value="inactive">Pasif / ayrılmış</option>
                        <option value="all">Tüm hesaplar</option>
                    </select>
                </div>
            </div>

            <div class="grid min-h-64 lg:grid-cols-[260px_minmax(0,1fr)]">
                <nav aria-label="Danışman listesi" class="border-b border-slate-200 dark:border-slate-700 lg:border-b-0 lg:border-r">
                    <button
                        v-for="consultant in accountConsultants"
                        :key="consultant.id"
                        type="button"
                        @click="selectConsultant(consultant)"
                        :class="[
                            'flex w-full items-center justify-between gap-2 border-b border-slate-100 px-3 py-3 text-left transition dark:border-slate-700/60',
                            selectedConsultant?.id === consultant.id ? 'bg-indigo-50 dark:bg-indigo-500/10' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60',
                        ]"
                    >
                        <span class="min-w-0">
                            <span class="block truncate text-xs font-semibold text-slate-800 dark:text-slate-200">{{ consultant.name }}</span>
                            <span :class="['mt-1 inline-flex rounded px-1.5 py-0.5 text-[9px] font-semibold', consultant.active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300']">
                                {{ consultant.active ? 'Aktif' : 'Pasif / ayrılmış' }}
                            </span>
                            <span class="mt-1 block text-[10px] text-slate-500">{{ consultant.firmCount }} mevcut · {{ consultant.periodCount }} dönem</span>
                        </span>
                        <span class="shrink-0 rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ consultant.firmCount }}</span>
                    </button>
                    <p v-if="!accountConsultants.length" class="p-4 text-xs text-slate-500">Aramanızla eşleşen danışman hesabı yok.</p>
                    <div v-if="unmatchedConsultants.length" class="border-t border-slate-200 dark:border-slate-700">
                        <button type="button" @click="showUnmatched = !showUnmatched" class="flex w-full items-center justify-between px-3 py-2.5 text-left text-[11px] font-semibold text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/60">
                            Eşleşmeyen eski kayıtlar
                            <span>{{ showUnmatched ? 'Gizle' : unmatchedConsultants.length }}</span>
                        </button>
                        <button
                            v-for="consultant in (showUnmatched ? unmatchedConsultants : [])"
                            :key="consultant.id"
                            type="button"
                            @click="selectConsultant(consultant)"
                            :class="[
                                'flex w-full items-center justify-between gap-2 border-t border-slate-100 px-3 py-3 text-left transition dark:border-slate-700/60',
                                selectedConsultant?.id === consultant.id ? 'bg-indigo-50 dark:bg-indigo-500/10' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60',
                            ]"
                        >
                            <span class="min-w-0">
                                <span class="block truncate text-xs font-semibold text-slate-800 dark:text-slate-200">{{ consultant.name }}</span>
                                <span class="mt-1 inline-flex rounded bg-slate-200 px-1.5 py-0.5 text-[9px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">Eşleşmeyen kayıt</span>
                                <span class="mt-1 block text-[10px] text-slate-500">{{ consultant.pastFirmCount }} bıraktığı tesis · {{ consultant.periodCount }} dönem</span>
                            </span>
                            <span class="shrink-0 rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ consultant.pastFirmCount }}</span>
                        </button>
                    </div>
                </nav>

                <div class="min-w-0 p-3">
                    <template v-if="selectedConsultant">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-bold text-slate-800 dark:text-white">{{ selectedConsultant.name }}</h3>
                                <p class="mt-0.5 text-[11px] text-slate-500">
                                    <template v-if="selectedConsultant.historical">{{ selectedConsultant.pastFirmCount }} bıraktığı tesis · {{ selectedConsultant.periodCount }} dönem · Eşleşmeyen eski kayıt</template>
                                    <template v-else>{{ selectedConsultant.firmCount }} mevcut tesis · {{ selectedConsultant.periodCount }} dönem · {{ selectedConsultant.active ? 'Aktif danışman' : 'Pasif / ayrılmış danışman' }}</template>
                                </p>
                                <p v-if="selectedConsultant.aliases?.length" class="mt-1 text-[10px] text-slate-500">Diğer yazımlar: {{ selectedConsultant.aliases.join(', ') }}</p>
                            </div>
                            <Link v-if="!selectedConsultant.historical" href="/yonetim/kullanicilar" class="text-[11px] font-semibold text-indigo-600 hover:underline dark:text-indigo-400">
                                Görev devri / hesap işlemleri
                            </Link>
                        </div>

                        <div class="mb-3 flex flex-wrap items-center gap-2">
                            <label class="sr-only" :for="`assignment-filter-${selectedConsultant.id}`">Tesis dönemi filtresi</label>
                            <select :id="`assignment-filter-${selectedConsultant.id}`" v-model="assignmentFilter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                                <option value="all">Tüm tesis dönemleri</option>
                                <option value="current">Devam eden tesisler</option>
                                <option value="past">Bıraktığı tesisler</option>
                            </select>
                            <span class="text-[11px] text-slate-500">{{ selectedAssignments.length }} dönem</span>
                        </div>

                        <div v-if="selectedAssignments.length" class="rounded-lg border border-slate-200 dark:border-slate-700">
                            <table class="w-full table-fixed text-left">
                                <colgroup>
                                    <col class="w-[28%]">
                                    <col class="w-[14%]">
                                    <col class="w-[14%]">
                                    <col class="w-[16%]">
                                    <col class="w-[16%]">
                                    <col class="w-[12%]">
                                </colgroup>
                                <thead class="bg-slate-50 text-[10px] uppercase text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                                    <tr>
                                        <th class="px-3 py-2">Tesis</th>
                                        <th class="px-3 py-2">Aldığı tarih</th>
                                        <th class="px-3 py-2">Bıraktığı tarih</th>
                                        <th class="px-3 py-2">Kimden</th>
                                        <th class="px-3 py-2">Kime</th>
                                        <th class="px-3 py-2">Dönem</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                                    <tr v-for="(assignment, index) in selectedAssignments" :key="`${assignment.firm_id}-${assignment.received_at ?? 'unknown'}-${index}`" class="text-xs">
                                        <td class="break-words px-3 py-2.5 font-semibold text-slate-800 dark:text-slate-200">
                                            <Link :href="`/firma/${assignment.firm_id}`" class="hover:text-indigo-600 hover:underline dark:hover:text-indigo-400">{{ assignment.firm_name }}</Link>
                                        </td>
                                        <td class="break-words px-3 py-2.5 text-slate-600 dark:text-slate-300">{{ formatTenureDate(assignment.received_at) }}</td>
                                        <td class="break-words px-3 py-2.5 text-slate-600 dark:text-slate-300">{{ assignment.is_current ? '—' : formatTenureDate(assignment.left_at) }}</td>
                                        <td class="break-words px-3 py-2.5 text-slate-600 dark:text-slate-300">{{ assignment.received_from || '—' }}</td>
                                        <td class="break-words px-3 py-2.5 text-slate-600 dark:text-slate-300">{{ assignment.handed_to || '—' }}</td>
                                        <td class="px-3 py-2.5">
                                            <span :class="['rounded px-1.5 py-1 text-[10px] font-semibold', assignment.is_current ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : assignment.left_at ? 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400']">
                                                {{ assignment.is_current ? 'Devam ediyor' : assignment.left_at ? 'Bıraktı' : 'Bitiş bilinmiyor' }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-xs text-slate-500 dark:border-slate-600">
                            Bu filtreyle eşleşen tesis dönemi bulunmuyor.
                        </p>
                    </template>
                    <p v-else class="py-12 text-center text-xs text-slate-500">Görüntülenecek danışman yok.</p>
                </div>
            </div>
        </section>

        <section v-if="unassignedFirms.length" class="overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm dark:border-amber-900/50 dark:bg-[#1e293b]">
            <div class="border-b border-amber-100 px-4 py-3 dark:border-amber-900/40">
                <h2 class="text-sm font-bold text-slate-800 dark:text-white">Atanmamış tesisler <span class="ml-1 text-xs font-medium text-amber-600">({{ unassignedFirms.length }})</span></h2>
            </div>
            <div class="flex flex-wrap gap-2 p-3">
                <span v-for="firm in unassignedFirms" :key="firm.id" class="rounded-md bg-amber-50 px-2.5 py-1.5 text-[11px] text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">{{ firm.name }}</span>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-[#1e293b]">
            <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                <h2 class="text-sm font-bold text-slate-800 dark:text-white">Tüm danışman atama geçmişi</h2>
                <p class="mt-0.5 text-[11px] text-slate-500">Tesisin eski ve yeni danışmanı, değişiklik gerekçesi ve işlemi yapan yönetici.</p>
            </div>
            <div class="grid gap-2 border-b border-slate-200 p-3 dark:border-slate-700 sm:grid-cols-2 xl:grid-cols-5">
                <label class="sr-only" for="history-search">Geçmişte danışman veya tesis ara</label>
                <input id="history-search" v-model="historySearch" type="search" placeholder="Danışman, tesis veya gerekçe ara" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs outline-none focus:border-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                <label class="sr-only" for="history-consultant-filter">Danışmana göre filtrele</label>
                <select id="history-consultant-filter" v-model="historyConsultantFilter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                    <option value="">Tüm danışmanlar</option>
                    <option v-for="name in historyConsultantOptions" :key="name" :value="name">{{ name }}</option>
                </select>
                <label class="sr-only" for="history-movement-filter">Atama yönüne göre filtrele</label>
                <select id="history-movement-filter" v-model="historyMovementFilter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                    <option value="all">Alma ve bırakma</option>
                    <option value="received">Danışmanın aldığı tesisler</option>
                    <option value="left">Danışmanın bıraktığı tesisler</option>
                </select>
                <label class="flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-500 dark:border-slate-600 dark:text-slate-400">
                    Başlangıç
                    <input v-model="historyFrom" type="month" aria-label="Başlangıç ayı" class="min-w-0 bg-transparent text-xs text-slate-800 outline-none dark:text-white">
                </label>
                <label class="flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-500 dark:border-slate-600 dark:text-slate-400">
                    Bitiş
                    <input v-model="historyTo" type="month" aria-label="Bitiş ayı" class="min-w-0 bg-transparent text-xs text-slate-800 outline-none dark:text-white">
                </label>
                <div class="flex items-center justify-between gap-2 text-[11px] text-slate-500 sm:col-span-2 xl:col-span-5">
                    <span>{{ filteredHistory.length }} / {{ history.length }} kayıt</span>
                    <button type="button" @click="clearHistoryFilters" class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400">Filtreleri temizle</button>
                </div>
            </div>
            <div v-if="filteredHistory.length">
                <table class="w-full table-fixed text-left">
                    <colgroup>
                        <col class="w-[16%]">
                        <col class="w-[28%]">
                        <col class="w-[22%]">
                        <col class="w-[20%]">
                        <col class="w-[14%]">
                    </colgroup>
                    <thead class="bg-slate-50 text-[10px] uppercase text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                        <tr>
                            <th class="px-3 py-2">Tarih</th>
                            <th class="px-3 py-2">Tesis</th>
                            <th class="px-3 py-2">Danışman değişikliği</th>
                            <th class="px-3 py-2">Gerekçe</th>
                            <th class="px-3 py-2">İşlemi yapan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <tr v-for="entry in filteredHistory" :key="entry.id" class="text-xs">
                            <td class="break-words px-3 py-2.5 text-slate-500">{{ formatHistoryDate(entry) }}</td>
                            <td class="break-words px-3 py-2.5 font-semibold text-slate-800 dark:text-slate-200">{{ entry.firm_name }}</td>
                            <td class="break-words px-3 py-2.5 text-slate-600 dark:text-slate-300">{{ entry.from }} <span class="px-1 text-slate-400">→</span> {{ entry.to }}</td>
                            <td class="break-words px-3 py-2.5 text-slate-600 dark:text-slate-300">{{ formatReason(entry) }}</td>
                            <td class="break-words px-3 py-2.5 text-slate-500">{{ entry.actor }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="px-4 py-6 text-xs text-slate-500">Bu filtreyle eşleşen danışman atama geçmişi yok.</p>
        </section>
    </div>
</template>
