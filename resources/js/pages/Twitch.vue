<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { useLang } from '@/composables/useLang'

const { __ } = useLang()

const title = __('app.twitch')

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: title,
        href: '/twitch',
    },
];
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
<!--            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
            </div>-->
            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border p-4">
                <button
                    class="flex items-center px-5 py-2 bg-[#9146FF] hover:bg-[#772CE8] text-white font-semibold rounded-lg transition-all cursor-pointer hover:scale-105"
                >
                    <!-- Vous pouvez remplacer cet SVG par votre icône Twitch préférée -->
                    <svg
                        class="w-5 h-5 mr-2 fill-current"
                        viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path d="M4.64 0L1 6v17h7V24l2.75-2.75h4.75L23 14.5V0H4.64zM20 13.5L15 18H10.25L8 20.25V18H4V2h16v11.5z"/>
                        <path d="M17 4h-2v6h2V4zm-4 0h-2v6h2V4z"/>
                    </svg>

                    <span>{{ __('app.cta.twitch_connect') }}</span>
                </button>
            </div>
        </div>
    </AppLayout>
</template>
