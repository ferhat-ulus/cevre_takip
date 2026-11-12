<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link
        href="/logout"
        method="post"
        as="button"
        aria-label="Güvenli çıkış"
        title="Güvenli çıkış"
        class="rounded-md p-2 text-slate-500 hover:bg-rose-50 hover:text-rose-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 dark:text-slate-300 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
    >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4m-5-4l5-5m0 0l-5-5m5 5H3" />
        </svg>
    </Link>
</template>
