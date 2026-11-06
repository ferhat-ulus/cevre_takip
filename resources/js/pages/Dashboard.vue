<script setup>
import { computed, ref } from 'vue';
import { Link, usePage, Head } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
    firmSummary: Object,
    consultants: Array
});

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');
const openConsultant = ref(null);

const ozet = computed(() => ({
    gecikmis: props.stats?.overdue || 0,
    kritik: props.stats?.crit || 0,
    yaklasan: props.stats?.soon || 0,
    veriYok: props.stats?.missing || 0,
}));

const danismanlar = computed(() => props.consultants || []);
const kendiIsleri = computed(() => danismanlar.value[0]?.tasks || []);

const toggleConsultant = (name) => {
    openConsultant.value = openConsultant.value === name ? null : name;
};

const getStatusColor = (state) => {
    if (state === 'overdue') return 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20';
    if (state === 'crit') return 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-500/10 dark:text-orange-400 dark:border-orange-500/20';
    if (state === 'soon') return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20';
    return 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-700/50 dark:text-gray-400 dark:border-gray-600';
};

const kalanSure = (days) => (days === null || days === undefined ? 'Belirsiz' : `${days} gün`);
</script>

<template>
    <Head title="Genel Bakış" />

    <div class="w-full space-y-4">
        <div>
            <h1 class="text-xl font-extrabold text-slate-900 dark:text-white tracking-tight">Genel Bakış</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-0.5 text-xs">Çalışılan tesislerin kapsamı ve danışmanların tarihi belli iş yükü.</p>
        </div>

        <section aria-label="Tesis dağılımı" class="grid grid-cols-2 xl:grid-cols-4 gap-2">
            <div class="flex items-center justify-between gap-2 bg-white dark:bg-[#1e293b] rounded-lg px-3 py-2 border border-slate-200 dark:border-slate-700">
                <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Toplam Tesis</p>
                <p class="text-lg font-bold text-slate-800 dark:text-white">{{ firmSummary?.total ?? 0 }}</p>
            </div>
            <div class="flex items-center justify-between gap-2 bg-white dark:bg-[#1e293b] rounded-lg px-3 py-2 border border-slate-200 dark:border-slate-700">
                <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Ek-1</p>
                <p class="text-lg font-bold text-indigo-600 dark:text-indigo-400">{{ firmSummary?.ek1 ?? 0 }}</p>
            </div>
            <div class="flex items-center justify-between gap-2 bg-white dark:bg-[#1e293b] rounded-lg px-3 py-2 border border-slate-200 dark:border-slate-700">
                <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Ek-2</p>
                <p class="text-lg font-bold text-sky-600 dark:text-sky-400">{{ firmSummary?.ek2 ?? 0 }}</p>
            </div>
            <div class="flex items-center justify-between gap-2 bg-white dark:bg-[#1e293b] rounded-lg px-3 py-2 border border-slate-200 dark:border-slate-700">
                <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Kapsam Dışı</p>
                <p class="text-lg font-bold text-slate-600 dark:text-slate-300">{{ firmSummary?.scopeOutside ?? 0 }}</p>
            </div>
        </section>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="bg-white dark:bg-[#1e293b] rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Gecikmiş İşler</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-white">{{ ozet.gecikmis }}</p>
            </div>
            <div class="bg-white dark:bg-[#1e293b] rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Kritik</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-white">{{ ozet.kritik }}</p>
            </div>
            <div class="bg-white dark:bg-[#1e293b] rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Yaklaşan</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-white">{{ ozet.yaklasan }}</p>
            </div>
            <div class="bg-white dark:bg-[#1e293b] rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Veri Yok</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-white">{{ ozet.veriYok }}</p>
            </div>
        </div>

        <div v-if="isAdmin" class="bg-white dark:bg-[#1e293b] rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800 dark:text-white">Danışman yükü</h2>
                        <p class="mt-0.5 text-[11px] text-slate-500">Satıra tıklayınca o danışmanın tarihi belli en acil işleri açılır. Veri yok kayıtları bu listeye girmez.</p>
                    </div>
                    <Link href="/raporlar" class="shrink-0 text-xs font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">Tüm işler</Link>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider font-semibold">
                            <th class="px-4 py-2.5">Danışman</th>
                            <th class="px-4 py-2.5 text-right">Tesis</th>
                            <th class="px-4 py-2.5 text-right">Gecikmiş</th>
                            <th class="px-4 py-2.5 text-right">Kritik</th>
                            <th class="px-4 py-2.5 text-right">Yaklaşan</th>
                            <th class="px-4 py-2.5 text-right">Veri yok</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <template v-for="danisman in danismanlar" :key="danisman.name">
                            <tr class="cursor-pointer text-xs hover:bg-slate-50/80 dark:hover:bg-slate-800/50" @click="toggleConsultant(danisman.name)">
                                <td class="px-4 py-2.5 font-semibold text-slate-800 dark:text-slate-200">
                                    <button type="button" class="text-left" :aria-expanded="openConsultant === danisman.name">
                                        {{ danisman.name }}
                                    </button>
                                </td>
                                <td class="px-4 py-2.5 text-right text-slate-600 dark:text-slate-300">{{ danisman.firmCount }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold text-rose-700 dark:text-rose-400">{{ danisman.overdue }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold text-orange-700 dark:text-orange-400">{{ danisman.crit }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold text-amber-700 dark:text-amber-400">{{ danisman.soon }}</td>
                                <td class="px-4 py-2.5 text-right text-slate-600 dark:text-slate-300">{{ danisman.missing }}</td>
                            </tr>
                            <tr v-if="openConsultant === danisman.name">
                                <td colspan="6" class="bg-slate-50 px-4 py-3 dark:bg-slate-800/40">
                                    <p v-if="danisman.tasks.length === 0" class="text-xs text-slate-500">Tarihi belli öncelikli iş yok.</p>
                                    <table v-else class="w-full text-left text-xs">
                                        <thead>
                                            <tr class="text-[11px] uppercase tracking-wider text-slate-500">
                                                <th class="py-1 pr-3 font-semibold">Tesis</th>
                                                <th class="py-1 pr-3 font-semibold">Konu</th>
                                                <th class="py-1 pr-3 font-semibold">Kalan süre</th>
                                                <th class="py-1 pr-3 font-semibold">Durum</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="is in danisman.tasks" :key="`${is.firm_id}-${is.konu}`" class="border-t border-slate-200 dark:border-slate-700">
                                                <td class="py-2 pr-3 font-semibold text-slate-800 dark:text-slate-200">
                                                    <Link :href="`/firma/${is.firm_id}`" class="hover:text-indigo-600 dark:hover:text-indigo-400">{{ is.firma }}</Link>
                                                </td>
                                                <td class="py-2 pr-3 text-slate-600 dark:text-slate-300">{{ is.konu }}</td>
                                                <td class="py-2 pr-3 font-semibold text-slate-700 dark:text-slate-200">{{ kalanSure(is.days) }}</td>
                                                <td class="py-2">
                                                    <span :class="['rounded-full border px-2 py-0.5 text-[10px] font-bold', getStatusColor(is.state)]">{{ is.durum }}</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="danismanlar.length === 0">
                            <td colspan="6" class="px-4 py-6 text-center text-xs text-slate-500">Çalışılan tesis yok.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-else class="bg-white dark:bg-[#1e293b] rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800 dark:text-white">Öncelikli işler</h2>
                        <p class="mt-0.5 text-[11px] text-slate-500">Tarihi belli en acil işler. Veri yok kayıtları sayı olarak üstte durur.</p>
                    </div>
                    <Link href="/raporlar" class="shrink-0 text-xs font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">Tüm işler</Link>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider font-semibold">
                            <th class="px-4 py-2.5">Tesis</th>
                            <th class="px-4 py-2.5">Konu</th>
                            <th class="px-4 py-2.5">Kalan süre</th>
                            <th class="px-4 py-2.5">Durum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="is in kendiIsleri" :key="`${is.firm_id}-${is.konu}`" class="text-xs">
                            <td class="px-4 py-2.5 font-semibold text-slate-800 dark:text-slate-200">
                                <Link :href="`/firma/${is.firm_id}`" class="hover:text-indigo-600 dark:hover:text-indigo-400">{{ is.firma }}</Link>
                            </td>
                            <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ is.konu }}</td>
                            <td class="px-4 py-2.5 font-semibold text-slate-700 dark:text-slate-200">{{ kalanSure(is.days) }}</td>
                            <td class="px-4 py-2.5">
                                <span :class="['rounded-full border px-2 py-0.5 text-[10px] font-bold', getStatusColor(is.state)]">{{ is.durum }}</span>
                            </td>
                        </tr>
                        <tr v-if="kendiIsleri.length === 0">
                            <td colspan="4" class="px-4 py-6 text-center text-xs text-slate-500">Tarihi belli öncelikli iş yok.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
