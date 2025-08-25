<script setup lang="ts">
import { useLang } from '@/composables/useLang';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';

interface FollowedStreamer {
    broadcasterId: string;
    broadcasterLogin: string;
    broadcasterName: string;
    followedAt: string;
}

const { __ } = useLang();

const title = __('app.twitch');

const props = defineProps<{
    redirect: string;
    followedStreamers: FollowedStreamer[];
}>();

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

onMounted(() => {
    if (import.meta.env.DEV) console.log('Given redirect uri %s', props.redirect);
});
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
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
        <h1 class="mt-6 mb-2 px-4 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Mes suivis</h1>

        <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 2xl:grid-cols-9">
            <div
                v-for="streamer in props.followedStreamers"
                :key="streamer.broadcasterId"
                class="flex cursor-pointer flex-col overflow-hidden rounded-lg bg-white shadow-md transition-all hover:scale-105 dark:bg-[#121212]"
            >
                <div class="relative h-28">
                    <!--                    <img
                        :src="streamer._profileImageUrl ?? 'http://placebeard.it/640/480'"
                        :alt="streamer.broadcasterName"
                        class="h-full w-full object-cover"
                    />-->
                    <div class="absolute top-0 h-28 w-full bg-black object-cover"></div>
                    <div
                        v-if="streamer.isLive"
                        class="absolute top-2 right-2 flex items-center gap-1 rounded-full bg-red-500 px-2 py-1 text-xs text-white"
                    >
                        <div class="h-2 w-2 animate-pulse rounded-full bg-white"></div>
                        LIVE
                    </div>
                </div>
                <div class="p-4">
                    <h3 class="text-sm font-semibold">{{ streamer.broadcasterName }}</h3>
                    <div v-if="streamer.isLive" class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        <p>Playing {{ streamer.game }}</p>
                        <p>- viewers</p>
                    </div>
                    <div v-else class="mt-2 text-xs text-gray-500">Offline</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
