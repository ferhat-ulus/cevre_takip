<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PlatformLayout from '@/layouts/PlatformLayout.vue';

defineOptions({ layout: PlatformLayout });

defineProps({
    modules: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <Head title="Modül Seçimi" />

    <div class="mx-auto max-w-5xl space-y-5">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-[#1e293b]">
            <p class="text-xs font-bold uppercase tracking-widest text-brand-700 dark:text-brand-400">Ulus Çevre</p>
            <h1 class="mt-2 text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">Çalışmak istediğiniz modülü seçin</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Devam etmek için kullanmak istediğiniz uygulamayı seçebilirsiniz.</p>
        </section>

        <section aria-label="Modüller" class="grid gap-3 md:grid-cols-3">
            <template v-for="module in modules" :key="module.name">
                <Link
                    v-if="module.available"
                    :href="module.href"
                    method="post"
                    as="button"
                    class="group flex min-h-44 flex-col rounded-xl border border-brand-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-400 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 dark:border-brand-500/30 dark:bg-[#1e293b] dark:hover:border-brand-400"
                >
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M6 4v6m12-6v6M5 11h14v9H5z"></path>
                        </svg>
                    </span>
                    <span class="mt-4 flex items-center justify-between gap-2">
                        <span class="text-base font-bold text-slate-900 dark:text-white">{{ module.name }}</span>
                        <span class="text-xs font-semibold text-brand-700 group-hover:translate-x-0.5 dark:text-brand-400" aria-hidden="true">→</span>
                    </span>
                    <span class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ module.description }}</span>
                    <span class="mt-auto pt-4 text-xs font-semibold text-emerald-700 dark:text-emerald-400">Kullanıma hazır</span>
                </Link>

                <div
                    v-else
                    aria-disabled="true"
                    class="flex min-h-44 flex-col rounded-xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-800/50"
                >
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-400" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M6 4v6m12-6v6M5 11h14v9H5z"></path>
                        </svg>
                    </span>
                    <span class="mt-4 flex items-center justify-between gap-2">
                        <span class="text-base font-bold text-slate-700 dark:text-slate-200">{{ module.name }}</span>
                        <span class="rounded-full bg-slate-200 px-2 py-1 text-[10px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">Yakında</span>
                    </span>
                    <span class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ module.description }}</span>
                </div>
            </template>
        </section>
    </div>
</template>
