<script setup lang="ts">
import { useLang } from '@/composables/useLang';

const props = defineProps<{
    redirect: string;
    fill?: boolean;
}>();

const { __ } = useLang();
</script>
<template>
    <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 p-4 md:min-h-min dark:border-sidebar-border">
        <a
            :href="props.redirect"
            class="cursor-pointer items-center rounded-lg bg-[#9146FF] px-5 py-2 font-semibold text-white transition-all hover:scale-105 hover:bg-[#772CE8]"
            :class="props?.fill ? 'flex' : 'inline-flex'"
        >
            <svg class="mr-2 h-5 w-5 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M4.64 0L1 6v17h7V24l2.75-2.75h4.75L23 14.5V0H4.64zM20 13.5L15 18H10.25L8 20.25V18H4V2h16v11.5z" />
                <path d="M17 4h-2v6h2V4zm-4 0h-2v6h2V4z" />
            </svg>

            <span>{{ __('app.cta.twitch_connect') }}</span>
        </a>
    </div>
</template>
