<script setup>
import { ref, onMounted, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const isDark = ref(false);
const page = usePage();

// Kullanıcı verisini al
const user = computed(() => page.props.auth?.user || null);

const currentUrl = computed(() => page.url);
// Admin kontrolü (Büyük/küçük harf duyarsız)
const isAdmin = computed(() => user.value?.role?.toLowerCase() === 'admin');

onMounted(() => {
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
                { name: 'Rapor İndir', href: '/rapor-indir', icon: 'M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
            ]
        }
    ];

    // Eğer admin ise Yönetim menüsünü ekle
    if (isAdmin.value) {
        groups.push({
            title: 'Yönetim',
            items: [
                { name: 'Kullanıcı Yönetimi', href: '/kullanicilar', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
                { name: 'Sistem Logları', href: '/islem-gecmisi', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
                { name: 'Sistem Ayarları', href: '/ayarlar', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' }, 
                { name: 'Yedekler', href: '/yedekler', icon: 'M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4' },
            ]
        });
    }

    return groups;
});
</script>

<template>
    <div class="min-h-screen bg-[#f8fafc] dark:bg-[#0f172a] text-slate-600 dark:text-slate-200 flex transition-colors duration-300 font-sans text-sm">
        
        <aside class="w-64 bg-white dark:bg-[#1e293b] border-r border-slate-200 dark:border-slate-700 hidden lg:flex flex-col shadow-sm">
            <div class="h-16 flex items-center px-6 border-b border-slate-100 dark:border-slate-700/50 shrink-0">
                <img src="/logo.png" alt="Logo" class="h-8 mr-3 object-contain">
                <span class="text-lg font-bold text-slate-800 dark:text-white tracking-tight">
                    Ulus Çevre
                </span>
            </div>
            
            <nav class="flex-1 p-4 space-y-6 overflow-y-auto custom-scrollbar">
                <div v-for="(group, index) in menuGroups" :key="index" class="space-y-1">
                    <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest px-3 mb-2">{{ group.title }}</p>
                    <template v-for="item in group.items" :key="item.name">
                        <Link 
                            :href="item.href"
                            :class="[
                                'flex items-center space-x-3 px-3 py-2 rounded-lg transition-all duration-200 group text-sm',
                                currentUrl === item.href 
                                    ? 'bg-brand-50 dark:bg-brand-500/10 text-brand-700 dark:text-brand-400 font-semibold shadow-sm' 
                                    : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
                            ]"
                        >
                            <svg :class="['w-4 h-4 transition-colors', currentUrl === item.href ? 'text-brand-700 dark:text-brand-400' : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-300']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon"></path>
                            </svg>
                            <span>{{ item.name }}</span>
                        </Link>
                    </template>
                </div>
            </nav>

            <div class="p-4 border-t border-slate-100 dark:border-slate-700/50 shrink-0">
                <Link href="/logout" method="post" as="button" class="w-full flex items-center justify-center space-x-2 px-3 py-2 text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/10 hover:bg-rose-100 dark:hover:bg-rose-500/20 rounded-lg transition-colors font-medium text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    <span>Güvenli Çıkış</span>
                </Link>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
            <header class="h-16 bg-white/90 dark:bg-[#1e293b]/90 backdrop-blur-xl border-b border-slate-200 dark:border-slate-700 flex items-center justify-between px-6 shadow-sm z-10 shrink-0">
                <div>
                    <h2 class="text-base font-bold text-slate-800 dark:text-white">Sistem Yönetimi</h2>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Hoş geldiniz, bugün harika bir gün!</p>
                </div>
                
                <div class="flex items-center space-x-4">
                    <button @click="toggleTheme" class="relative p-1.5 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-full transition-colors">
                        <svg v-if="!isDark" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </button>
                    
                    <div class="flex items-center space-x-3 pl-4 border-l border-slate-200 dark:border-slate-700">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-r from-brand-500 to-brand-600 flex items-center justify-center text-white font-bold text-xs shadow-sm ring-2 ring-white dark:ring-slate-800">
                            {{ user?.name ? user.name.charAt(0) : 'U' }}
                        </div>
                        <div class="hidden md:block">
                            <p class="text-xs font-bold text-slate-700 dark:text-slate-200">{{ user?.name || 'Kullanıcı' }}</p>
                            <p class="text-[10px] font-medium text-brand-700 dark:text-brand-400">{{ isAdmin ? 'Sistem Yöneticisi' : 'Danışman' }}</p>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6 flex-1 overflow-y-auto bg-slate-100 dark:bg-[#0f172a]">
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