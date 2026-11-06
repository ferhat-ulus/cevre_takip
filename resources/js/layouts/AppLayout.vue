<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const isDark = ref(false);
const page = usePage();

// Kullanıcı verisini al
const user = computed(() => page.props.auth?.user || null);

const currentUrl = computed(() => page.url);
// Admin kontrolü (Büyük/küçük harf duyarsız)
const isAdmin = computed(() => user.value?.role?.toLowerCase() === 'admin');
const isCollapsed = ref(false);
const isMobileMenuOpen = ref(false);
const openMenuGroup = ref('');

onMounted(() => {
    isCollapsed.value = localStorage.getItem('sidebar-collapsed-v2') === 'true';

    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        isDark.value = true;
        document.documentElement.classList.add('dark');
    } else {
        isDark.value = false;
        document.documentElement.classList.remove('dark');
    }
});

const toggleTheme = () => {
    isDark.value = !isDark.value;
    if (isDark.value) {
        document.documentElement.classList.add('dark');
        localStorage.theme = 'dark';
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.theme = 'light';
    }
};

const menuGroups = computed(() => {
    const groups = [
        {
            title: 'Genel',
            items: [
                { name: 'Genel Bakış', href: '/', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
                { name: 'Tesisler', href: '/tesisler', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
            ]
        },
        {
            title: 'Süreçler',
            items: [
                { name: 'İzin & Lisans', href: '/izin-lisans', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
                { name: 'Emisyon Süreçleri', href: '/emisyon', icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10' },
                { name: 'Ziyaret Takvimi', href: '/ziyaret-takvimi', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
            ]
        },
        {
            title: 'Raporlama & Veri',
            items: [
                { name: 'Görsel Raporlar', href: '/raporlar', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
                { name: 'Excel İşlemleri', href: '/excel-aktar', icon: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12' },
            ]
        }
    ];

    // Eğer admin ise Yönetim menüsünü ekle
    if (isAdmin.value) {
        groups.push({
            title: 'Yönetim',
            items: [
                { name: 'Kullanıcı Yönetimi', href: '/kullanicilar', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
                { name: 'Danışman Takibi', href: '/danisman-takibi', icon: 'M17 20h5v-2a3 3 0 00-5.196-2.04M17 20H7m10 0v-2c0-.653-.125-1.277-.352-1.84M7 20H2v-2a3 3 0 015.196-2.04M7 20v-2c0-.653.125-1.277.352-1.84m0 0a5.002 5.002 0 019.296 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0z' },
                { name: 'Sistem Logları', href: '/islem-gecmisi', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
                { name: 'Sistem Ayarları', href: '/ayarlar', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' }, 
                { name: 'Yedekler', href: '/yedekler', icon: 'M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4' },
            ]
        });
    }

    return groups;
});

const activeMenuGroup = computed(() => menuGroups.value.find((group) =>
    group.items.some((item) =>
        item.href === currentUrl.value
        || (item.href !== '/' && currentUrl.value.startsWith(`${item.href}/`))
        || (item.href === '/tesisler' && currentUrl.value.startsWith('/firma/')),
    ),
)?.title ?? '');

watch(activeMenuGroup, (group) => {
    if (group) openMenuGroup.value = group;
}, { immediate: true });

const pageTitle = computed(() => {
    for (const group of menuGroups.value) {
        const item = group.items.find((entry) => currentUrl.value === entry.href);
        if (item) return item.name;
    }

    return currentUrl.value.startsWith('/firma/') ? 'Tesis Detayı' : 'Çevre Takip';
});

const toggleSidebar = () => {
    isCollapsed.value = !isCollapsed.value;
    localStorage.setItem('sidebar-collapsed-v2', String(isCollapsed.value));
};

const toggleMenuGroup = (group) => {
    openMenuGroup.value = openMenuGroup.value === group ? '' : group;
};
</script>

<template>
    <div class="flex h-screen min-h-0 overflow-hidden bg-[#f8fafc] text-sm text-slate-600 transition-colors duration-300 dark:bg-[#0f172a] dark:text-slate-200">
        <a href="#main-content" class="sr-only z-50 rounded-md bg-white p-3 text-slate-900 focus:not-sr-only focus:fixed focus:left-3 focus:top-3">
            Ana içeriğe geç
        </a>
        <div
            v-if="isMobileMenuOpen"
            aria-hidden="true"
            class="fixed inset-0 z-30 bg-slate-950/40 lg:hidden"
            @click="isMobileMenuOpen = false"
        ></div>

        <aside
            :class="[
                'fixed inset-y-0 left-0 z-40 flex h-full min-h-0 w-64 flex-col border-r border-slate-200 bg-white shadow-sm transition-[width,transform] duration-200 dark:border-slate-700 dark:bg-[#1e293b] lg:static lg:translate-x-0',
                isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full',
                isCollapsed ? 'lg:w-14' : 'lg:w-52',
            ]"
        >
            <div class="flex h-14 shrink-0 items-center justify-between border-b border-slate-100 px-3 dark:border-slate-700/50">
                <Link href="/" class="flex min-w-0 items-center gap-3" aria-label="Genel bakış">
                    <img src="/logo.png" alt="" class="h-8 w-8 shrink-0 object-contain">
                    <span v-show="!isCollapsed || isMobileMenuOpen" class="truncate text-base font-bold tracking-tight text-slate-800 dark:text-white">Ulus Çevre</span>
                </Link>
                <button
                    type="button"
                    class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white lg:hidden"
                    aria-label="Menüyü kapat"
                    @click="isMobileMenuOpen = false"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
                <button
                    type="button"
                    class="hidden rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white lg:inline-flex"
                    :aria-label="isCollapsed ? 'Menüyü genişlet' : 'Menüyü daralt'"
                    :aria-expanded="!isCollapsed"
                    @click="toggleSidebar"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="isCollapsed ? 'M9 5l7 7-7 7' : 'M15 5l-7 7 7 7'"></path>
                    </svg>
                </button>
            </div>
            
            <nav aria-label="Ana menü" class="custom-scrollbar flex-1 space-y-4 overflow-y-auto px-2 py-3">
                <div v-for="(group, index) in menuGroups" :key="index" class="space-y-1">
                    <button
                        v-if="!isCollapsed || isMobileMenuOpen"
                        type="button"
                        class="flex min-h-9 w-full items-center justify-between rounded-md px-3 text-left text-[10px] font-bold uppercase tracking-widest text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                        :aria-expanded="openMenuGroup === group.title"
                        :aria-controls="`menu-group-${index}`"
                        @click="toggleMenuGroup(group.title)"
                    >
                        <span>{{ group.title }}</span>
                        <svg class="h-3.5 w-3.5 transition-transform" :class="openMenuGroup === group.title ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div v-show="isCollapsed || openMenuGroup === group.title" :id="`menu-group-${index}`" class="space-y-1">
                    <Link
                            v-for="item in group.items"
                            :key="item.name"
                            :href="item.href"
                            :title="isCollapsed && !isMobileMenuOpen ? item.name : undefined"
                            :aria-label="item.name"
                            :aria-current="currentUrl === item.href ? 'page' : undefined"
                            @click="isMobileMenuOpen = false"
                            :class="[
                                'group flex min-h-9 items-center gap-3 rounded-lg px-3 py-1.5 text-[13px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600',
                                isCollapsed && !isMobileMenuOpen ? 'lg:justify-center lg:px-0' : '',
                                currentUrl === item.href 
                                    ? 'bg-brand-50 font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-400'
                                    : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
                            ]"
                        >
                            <svg :class="['h-4 w-4 shrink-0 transition-colors', currentUrl === item.href ? 'text-brand-700 dark:text-brand-400' : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-300']" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon"></path>
                            </svg>
                            <span v-show="!isCollapsed || isMobileMenuOpen" class="truncate">{{ item.name }}</span>
                        </Link>
                    </div>
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
                        <p class="truncate text-[10px] text-slate-500 dark:text-slate-400">{{ isAdmin ? 'Sistem Yöneticisi' : 'Danışman' }}</p>
                    </div>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        aria-label="Güvenli çıkış"
                        title="Güvenli çıkış"
                        class="rounded-md p-2 text-slate-500 hover:bg-rose-50 hover:text-rose-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 dark:text-slate-300 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </Link>
                </div>
            </div>
        </aside>

        <div class="flex h-full min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
            <header class="z-10 flex h-14 shrink-0 items-center justify-between border-b border-slate-200 bg-white/90 px-3 shadow-sm backdrop-blur-xl dark:border-slate-700 dark:bg-[#1e293b]/90 md:px-5">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        aria-label="Menüyü aç"
                        :aria-expanded="isMobileMenuOpen"
                        class="rounded-md p-2 text-slate-600 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 dark:text-slate-300 dark:hover:bg-slate-700 lg:hidden"
                        @click="isMobileMenuOpen = true"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <div class="truncate text-sm font-semibold text-slate-800 dark:text-white">{{ pageTitle }}</div>
                </div>
                
                <div class="flex items-center">
                    <button @click="toggleTheme" :aria-label="isDark ? 'Açık temaya geç' : 'Koyu temaya geç'" class="rounded-md p-2 text-slate-500 transition-colors hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 dark:text-slate-400 dark:hover:bg-slate-700">
                        <svg v-if="!isDark" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                        <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </button>
                </div>
            </header>

            <main id="main-content" tabindex="-1" class="relative min-h-0 min-w-0 flex-1 overflow-y-auto bg-slate-100 p-3 dark:bg-[#0f172a] md:p-4">
                <slot />
            </main>
        </div>
    </div>
</template>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
</style>