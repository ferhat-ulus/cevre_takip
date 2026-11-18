<script setup>
import { Head } from '@inertiajs/vue3';
import PortalAnnouncement from '../components/PortalAnnouncement.vue';

defineProps({ announcements: { type: Array, default: () => [] } });
</script>

<template>
    <Head title="Duyurular" />
    <section class="space-y-4">
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">Duyurular</h1>
        <p v-if="!announcements.length" class="text-sm text-slate-500">Henüz yayınlanmış duyuru yok.</p>
        <PortalAnnouncement v-for="announcement in announcements" :key="announcement.key" :announcement="announcement" />
    </section>
</template>
