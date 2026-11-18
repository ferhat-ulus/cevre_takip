<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({ announcement: { type: Object, required: true } });
const form = useForm({});

function acknowledge() {
    form.post(`/duyurular/${props.announcement.key}/okudum`, { preserveScroll: true });
}
</script>

<template>
    <details class="rounded-xl border border-amber-300 bg-amber-50 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
        <summary class="cursor-pointer px-4 py-3 font-bold">
            {{ announcement.title }}
            <span class="ml-2 text-xs font-medium">{{ announcement.acknowledgedAt ? '✓ Okundu' : 'Yeni' }}</span>
        </summary>
        <div class="space-y-2 px-4 pb-4">
        <p v-for="paragraph in announcement.paragraphs" :key="paragraph">{{ paragraph }}</p>
        <button v-if="!announcement.acknowledgedAt" type="button" :disabled="form.processing" @click="acknowledge"
                class="rounded-lg bg-amber-900 px-3 py-1.5 font-semibold text-white disabled:opacity-60">
            ✓ Okudum, anladım
        </button>
        <details v-if="announcement.readers">
            <summary class="cursor-pointer font-semibold">{{ announcement.readers.length }} kullanıcı onayladı</summary>
            <ul class="mt-1 list-inside list-disc">
                <li v-for="reader in announcement.readers" :key="reader.username">{{ reader.name }} · {{ reader.username }}</li>
            </ul>
        </details>
        </div>
    </details>
</template>
