<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PlatformLayout from '@/layouts/PlatformLayout.vue';

defineOptions({ layout: PlatformLayout });
defineProps({ weekCount: Number, weekRecords: Array, weekUrl: String, monthUrl: String });
const formatDay = value => new Intl.DateTimeFormat('tr-TR', { day: 'numeric', month: 'long' }).format(new Date(`${String(value).slice(0, 10)}T12:00:00`));
</script>

<template>
    <Head title="Atık Takip" />

    <div class="mx-auto max-w-5xl space-y-5">
        <section class="flex flex-col gap-3 rounded-xl border border-brand-200 bg-white px-4 py-3 shadow-sm dark:border-brand-500/30 dark:bg-[#1e293b] sm:flex-row sm:items-center sm:justify-between sm:px-5 sm:py-3">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-widest text-brand-700 dark:text-brand-400">Ulus Çevre</p>
                <h1 class="mt-1 text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">Atık Takip</h1>
                <p class="text-sm leading-5 text-slate-600 dark:text-slate-300">Günlük taşıma planı ve kayıt yönetimi.</p>
            </div>
            <Link href="/atik-takip/kayitlar?yeni=1" class="rounded-lg bg-brand-600 px-4 py-2 text-center text-sm font-semibold text-white shadow-sm sm:shrink-0">Yeni taşıma kaydı</Link>
        </section>
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-[#1e293b]">
            <h2 class="font-bold text-slate-900 dark:text-white">Bu hafta yapılacak taşımalar</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ weekCount }} taşıma · Pazartesi–pazar</p>
            <ul v-if="weekRecords.length" class="mt-3 divide-y divide-slate-100 dark:divide-slate-700">
                <li v-for="day in weekRecords" :key="day.waste_date">
                    <details class="group rounded-md">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-md px-2 py-3 text-sm text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:text-slate-200 dark:hover:bg-slate-800">
                            <span class="flex min-w-0 items-center gap-2">
                                <span aria-hidden="true" class="text-slate-400 transition group-open:rotate-90">›</span>
                                <span class="font-semibold">{{ formatDay(day.waste_date) }}</span>
                            </span>
                            <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200">{{ day.transport_count }} taşıma</span>
                        </summary>
                        <div class="px-8 pb-3 pt-1">
                            <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ day.company_count }} firma · {{ day.waste_type_count }} farklı atık türü</p>
                            <ul class="mt-2 flex flex-wrap gap-2">
                                <li v-for="company in day.companies" :key="company" class="rounded-md bg-slate-100 px-2.5 py-1 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ company }}</li>
                            </ul>
                        </div>
                    </details>
                </li>
            </ul>
            <p v-else class="mt-3 text-sm text-slate-500 dark:text-slate-400">Bu hafta için planlanmış taşıma yok.</p>
        </section>
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-[#1e293b]">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h2 class="font-bold text-slate-900 dark:text-white">Tüm taşıma kayıtları</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">İstediğiniz dönem için kayıtları hazır filtrelerle açın.</p>
                </div>
                <nav aria-label="Taşıma kayıtları dönemi" class="flex flex-wrap gap-2">
                    <Link :href="weekUrl" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-brand-500 hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-brand-300">Bu hafta</Link>
                    <Link :href="monthUrl" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-brand-500 hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-brand-300">Bu ay</Link>
                    <Link href="/atik-takip/kayitlar" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-brand-500 hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-brand-300">Tüm kayıtlar</Link>
                </nav>
            </div>
        </section>
    </div>
</template>
