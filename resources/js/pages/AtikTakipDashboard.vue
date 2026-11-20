<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PlatformLayout from '@/layouts/PlatformLayout.vue';

defineOptions({ layout: PlatformLayout });
defineProps({ weekCount: Number, weekRecords: Array, monthCount: Number, weekUrl: String, monthUrl: String });
const formatDay = value => new Intl.DateTimeFormat('tr-TR', { day: 'numeric', month: 'long' }).format(new Date(`${String(value).slice(0, 10)}T12:00:00`));
const dayUrl = value => {
    const date = String(value).slice(0, 10);
    return `/atik-takip/kayitlar?from=${date}&to=${date}`;
};
</script>

<template>
    <Head title="Atık Takip" />

    <div class="mx-auto max-w-5xl space-y-5">
        <section class="flex flex-col gap-4 rounded-xl border border-brand-200 bg-white p-4 shadow-sm dark:border-brand-500/30 dark:bg-[#1e293b] sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-widest text-brand-700 dark:text-brand-400">Ulus Çevre</p>
                <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Atık Takip</h1>
                <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">Günlük taşıma planı ve kayıt yönetimi.</p>
            </div>
            <Link href="/atik-takip/kayitlar?yeni=1" class="rounded-lg bg-brand-600 px-4 py-3 text-center text-sm font-semibold text-white shadow-sm sm:shrink-0">Yeni taşıma kaydı</Link>
        </section>
        <section class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-[#1e293b]">
                <h2 class="font-bold text-slate-900 dark:text-white">Bu hafta yapılacak taşımalar</h2>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ weekCount }} planlanan taşıma · Bugünden pazar gününe</p>
                <ul v-if="weekRecords.length" class="mt-3 divide-y divide-slate-100 dark:divide-slate-700">
                    <li v-for="day in weekRecords" :key="day.waste_date">
                        <Link :href="dayUrl(day.waste_date)" class="flex items-center justify-between gap-3 rounded-md py-2 text-sm text-slate-700 hover:bg-slate-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-brand-300">
                            <span class="font-semibold">{{ formatDay(day.waste_date) }}</span>
                            <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200">{{ day.count }} taşıma</span>
                        </Link>
                    </li>
                </ul>
                <Link v-if="weekCount" :href="weekUrl" class="mt-3 inline-block text-sm font-semibold text-brand-700 dark:text-brand-400">Haftanın kayıtlarını aç</Link>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-[#1e293b]">
                <h2 class="font-bold text-slate-900 dark:text-white">Aylık taşıma kayıtları</h2>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ monthCount }} taşıma kaydı</p>
                <Link :href="monthUrl" class="mt-3 inline-block text-sm font-semibold text-brand-700 dark:text-brand-400">Bu ayın kayıtlarını aç</Link>
            </div>
        </section>
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-[#1e293b]">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h2 class="font-bold text-slate-900 dark:text-white">Tüm taşıma kayıtları</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Filtreleyin, sıralayın ve geçmiş kayıtlara ulaşın.</p>
                </div>
                <Link href="/atik-takip/kayitlar" class="self-start whitespace-nowrap py-1 text-sm font-semibold text-brand-700 dark:text-brand-400 sm:self-center">Listeyi aç</Link>
            </div>
        </section>
    </div>
</template>
