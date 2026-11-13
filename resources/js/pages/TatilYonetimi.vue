<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    initialYear: Number,
    calendar: Object,
    reminder: Object,
});

const year = ref(props.initialYear);
const entries = ref(props.calendar?.entries || []);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const form = ref({ holiday_date: '', title: '' });

const fixedEntries = computed(() => entries.value.filter((entry) => !entry.managed));
const religiousEntries = computed(() => entries.value.filter((entry) => entry.managed));
const yearOptions = computed(() => Array.from({ length: 5 }, (_, index) => props.initialYear - 1 + index));

const formatDate = (date) => new Intl.DateTimeFormat('tr-TR', {
    timeZone: 'Europe/Istanbul', day: '2-digit', month: 'long', year: 'numeric', weekday: 'long',
}).format(new Date(`${date}T12:00:00Z`));

const loadYear = async () => {
    loading.value = true;
    error.value = '';
    try {
        const response = await fetch(`/api/calendar-holidays?year=${year.value}`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Takvim yüklenemedi.');
        const data = await response.json();
        entries.value = data.entries || [];
    } catch (err) {
        error.value = err.message || 'Takvim yüklenemedi.';
    } finally {
        loading.value = false;
    }
};

const addHoliday = async () => {
    if (!form.value.holiday_date || !form.value.title.trim()) {
        error.value = 'Tarih ve tatil adı zorunludur.';
        return;
    }
    saving.value = true;
    error.value = '';
    try {
        const response = await fetch('/api/calendar-holidays', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json', Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
            },
            body: JSON.stringify(form.value),
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || data.message || 'Tatil eklenemedi.');
        form.value = { holiday_date: '', title: '' };
        if (Number(data.calendar?.year) === year.value) entries.value = data.calendar.entries || [];
        else await loadYear();
    } catch (err) {
        error.value = err.message || 'Tatil eklenemedi.';
    } finally {
        saving.value = false;
    }
};

const removeHoliday = async (entry) => {
    if (!window.confirm(`${formatDate(entry.date)} tarihindeki “${entry.title}” kaydı silinsin mi?`)) return;
    error.value = '';
    try {
        const response = await fetch(`/api/calendar-holidays/${entry.id}`, {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') },
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Tatil silinemedi.');
        entries.value = data.calendar?.entries || [];
    } catch (err) {
        error.value = err.message || 'Tatil silinemedi.';
    }
};
</script>

<template>
    <Head title="Resmî Tatil Takvimi" />

    <div class="mx-auto max-w-5xl space-y-5 pb-12">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Resmî Tatil Takvimi</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Yalnızca tam gün resmî tatiller ziyaret planlamasını kapatır. Yarım günler iş günü olarak kalır.</p>
            </div>
            <Link href="/ziyaret-takvimi" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Ziyaret Takvimine Dön</Link>
        </div>

        <div v-if="reminder?.visible" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
            <p class="font-bold">{{ reminder.year }} yılı dini bayram tarihleri bekliyor</p>
            <p class="mt-1">{{ reminder.message }}</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-[#1e293b]">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <label for="calendar-year" class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Takvim yılı</label>
                    <select id="calendar-year" v-model.number="year" class="mt-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white" @change="loadYear">
                        <option v-for="option in yearOptions" :key="option" :value="option">{{ option }}</option>
                    </select>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Sabit resmî tatiller sistem tarafından otomatik hesaplanır.</p>
            </div>
        </div>

        <p v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">{{ error }}</p>

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-[#1e293b]">
                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-700">
                    <h2 class="font-bold text-slate-800 dark:text-white">Sabit resmî tatiller</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Bu tarihler değiştirilemez; sistem tarafından her yıl otomatik eklenir.</p>
                </div>
                <div v-if="loading" class="p-5 text-sm text-slate-500">Takvim yükleniyor...</div>
                <ul v-else class="divide-y divide-slate-100 dark:divide-slate-700">
                    <li v-for="entry in fixedEntries" :key="entry.date" class="px-4 py-3">
                        <p class="text-sm font-semibold text-slate-800 dark:text-white">{{ entry.title }}</p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ formatDate(entry.date) }}</p>
                    </li>
                </ul>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-[#1e293b]">
                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-700">
                    <h2 class="font-bold text-slate-800 dark:text-white">Dini bayram tatilleri</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Diyanet takvimi açıklandığında yalnızca tam günleri ekleyin. Arife günleri eklenmez.</p>
                </div>
                <form class="grid gap-2 border-b border-slate-100 p-4 dark:border-slate-700 sm:grid-cols-[1fr_1.4fr_auto]" @submit.prevent="addHoliday">
                    <input v-model="form.holiday_date" type="date" :min="`${year}-01-01`" :max="`${year}-12-31`" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white" aria-label="Tatil tarihi">
                    <input v-model="form.title" type="text" maxlength="120" placeholder="Örn. Ramazan Bayramı 1. Gün" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white" aria-label="Tatil adı">
                    <button type="submit" :disabled="saving" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60">{{ saving ? 'Ekleniyor...' : 'Ekle' }}</button>
                </form>
                <div v-if="loading" class="p-5 text-sm text-slate-500">Takvim yükleniyor...</div>
                <p v-else-if="religiousEntries.length === 0" class="p-5 text-sm text-slate-500 dark:text-slate-400">Bu yıl için dini bayram tatili eklenmemiş.</p>
                <ul v-else class="divide-y divide-slate-100 dark:divide-slate-700">
                    <li v-for="entry in religiousEntries" :key="entry.id" class="flex items-center justify-between gap-3 px-4 py-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-white">{{ entry.title }}</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ formatDate(entry.date) }}</p>
                        </div>
                        <button type="button" class="rounded-md px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10" @click="removeHoliday(entry)">Sil</button>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
