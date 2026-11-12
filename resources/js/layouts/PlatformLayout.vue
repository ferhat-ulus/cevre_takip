<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import LogoutButton from '@/components/LogoutButton.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user || null);
const isModuleSelectionPage = computed(() => page.url.split('?')[0] === '/moduller');
const pageTitle = computed(() => isModuleSelectionPage.value ? 'Modül Seçimi' : 'Kullanıcılar ve Erişimler');
const pageDescription = computed(() => isModuleSelectionPage.value ? 'Çalışma alanınızı seçin' : 'Ortak sistem yönetimi');
const isDark = ref(false);
const isCollapsed = ref(false);
const isMobileMenuOpen = ref(false);

onMounted(() => {
    isCollapsed.value = localStorage.getItem('sidebar-collapsed-v2') === 'true';
    isDark.value = localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', isDark.value);
});

const toggleSidebar = () => {
    isCollapsed.value = !isCollapsed.value;
    localStorage.setItem('sidebar-collapsed-v2', String(isCollapsed.value));
};

const toggleTheme = () => {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);
    localStorage.theme = isDark.value ? 'dark' : 'light';
};
</script>

<template>
    <div class="flex h-screen min-h-0 overflow-hidden bg-[#f8fafc] text-sm text-slate-600 transition-colors duration-300 dark:bg-[#0f172a] dark:text-slate-200">
        <div v-if="isMobileMenuOpen" aria-hidden="true" class="fixed inset-0 z-30 bg-slate-950/40 lg:hidden" @click="isMobileMenuOpen = false"></div>
        <aside :class="['fixed inset-y-0 left-0 z-40 flex h-full min-h-0 w-64 flex-col border-r border-slate-200 bg-white shadow-sm transition-[width,transform] duration-200 dark:border-slate-700 dark:bg-[#1e293b] lg:static lg:translate-x-0', isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full', isCollapsed ? 'lg:w-14' : 'lg:w-52']">
            <div class="flex h-14 shrink-0 items-center justify-between border-b border-slate-100 px-3 dark:border-slate-700/50">
                <Link href="/moduller" class="flex min-w-0 items-center gap-3" aria-label="Modül seçimi" @click="isMobileMenuOpen = false">
                    <img src="/logo.png" alt="" class="h-8 w-8 shrink-0 object-contain">
                <span v-show="!isCollapsed || isMobileMenuOpen" class="truncate text-base font-bold tracking-tight text-slate-800 dark:text-white">Ulus Çevre</span>
                </Link>
                <button type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700 lg:hidden" aria-label="Menüyü kapat" @click="isMobileMenuOpen = false">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
                <button type="button" class="hidden rounded-md p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700 lg:inline-flex" :aria-label="isCollapsed ? 'Menüyü genişlet' : 'Menüyü daralt'" @click="toggleSidebar">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="isCollapsed ? 'M9 5l7 7-7 7' : 'M15 5l-7 7 7 7'" /></svg>
                </button>
            </div>
            <nav class="custom-scrollbar flex-1 space-y-4 overflow-y-auto px-2 py-3" aria-label="Yönetim menüsü">
                <div class="space-y-1">
                    <p v-show="!isCollapsed || isMobileMenuOpen" class="flex min-h-9 items-center px-3 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">Genel</p>
                    <Link href="/moduller" title="Modül Seçimi" :class="['group flex min-h-9 items-center gap-3 rounded-lg px-3 py-1.5 text-[13px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600', isCollapsed && !isMobileMenuOpen ? 'lg:justify-center lg:px-0' : '', isModuleSelectionPage ? 'bg-brand-50 font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-400' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white']" @click="isMobileMenuOpen = false">
                        <svg :class="['h-4 w-4 shrink-0 transition-colors', isModuleSelectionPage ? 'text-brand-700 dark:text-brand-400' : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-300']" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M6 4v6m12-6v6M5 11h14v9H5z" /></svg>
                        <span v-show="!isCollapsed || isMobileMenuOpen" class="truncate">Modül Seçimi</span>
                    </Link>
                </div>
                <div class="space-y-1">
                    <p v-show="!isCollapsed || isMobileMenuOpen" class="flex min-h-9 items-center px-3 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">Yönetim</p>
                    <Link href="/yonetim/kullanicilar" title="Kullanıcılar ve Erişimler" :class="['group flex min-h-9 items-center gap-3 rounded-lg px-3 py-1.5 text-[13px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600', isCollapsed && !isMobileMenuOpen ? 'lg:justify-center lg:px-0' : '', page.url.startsWith('/yonetim/kullanicilar') ? 'bg-brand-50 font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-400' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white']" @click="isMobileMenuOpen = false">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.196-2.04M17 20H7m10 0v-2c0-.653-.125-1.277-.352-1.84M7 20H2v-2a3 3 0 015.196-2.04M7 20v-2c0-.653.125-1.277.352-1.84m0 0a5.002 5.002 0 019.296 0M15 7a3 3 0 11-6 0 3 3 0 011-6 0 3 3 0 010 6" /></svg>
                        <span v-show="!isCollapsed || isMobileMenuOpen" class="truncate">Kullanıcılar ve Erişimler</span>
                    </Link>
                </div>
            </nav>
            <div class="shrink-0 border-t border-slate-100 p-2 dark:border-slate-700/50">
                <div :class="['flex items-center gap-2 rounded-lg bg-slate-50 p-2 dark:bg-slate-800/60', isCollapsed && !isMobileMenuOpen ? 'lg:justify-center lg:px-0' : '']">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white" :title="user?.name || 'Kullanıcı'">
                        {{ user?.name ? user.name.charAt(0).toLocaleUpperCase('tr-TR') : 'K' }}
                    </div>
                    <div v-show="!isCollapsed || isMobileMenuOpen" class="min-w-0 flex-1">
                        <p class="truncate text-[10px] text-slate-500 dark:text-slate-400">Hoş geldiniz</p>
                        <p class="truncate text-xs font-semibold text-slate-800 dark:text-white">{{ user?.name || 'Kullanıcı' }}</p>
                        <p class="truncate text-[10px] text-slate-500 dark:text-slate-400">Sistem Yöneticisi</p>
                    </div>
                    <LogoutButton />
                </div>
            </div>
        </aside>

        <div class="flex h-full min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
        <header class="z-10 flex h-14 shrink-0 items-center justify-between border-b border-slate-200 bg-white/90 px-3 shadow-sm backdrop-blur-xl dark:border-slate-700 dark:bg-[#1e293b]/90 md:px-5">
            <div class="flex w-full items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" aria-label="Menüyü aç" :aria-expanded="isMobileMenuOpen" class="rounded-md p-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700 lg:hidden" @click="isMobileMenuOpen = true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>
                    <div class="truncate text-sm font-semibold text-slate-800 dark:text-white">{{ pageTitle }}</div>
                </div>

                <div class="flex items-center gap-2">
                    <Link href="/moduller" class="hidden rounded-md border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700 dark:border-slate-600 dark:text-slate-300 sm:inline-flex">Modüller</Link>
                    <button type="button" :aria-label="isDark ? 'Açık temaya geç' : 'Koyu temaya geç'" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700" @click="toggleTheme">
                        <svg v-if="!isDark" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                        <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    </button>
                </div>
            </div>
        </header>
        <main class="min-h-0 flex-1 overflow-y-auto"><div class="mx-auto w-full max-w-7xl p-4 sm:p-6"><slot /></div></main>
        </div>
    </div>
</template>
