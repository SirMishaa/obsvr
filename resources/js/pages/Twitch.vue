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

/**
 * Twitch API response for a streamer's followed streams'
 */
interface FollowedStreamerStream {
    id: string;
    userId: string;
    userLogin: string;
    userName: string;
    gameId: string;
    gameName: string;
    type: 'live' | '';
    tags: string[];
    title: string;
    viewerCount: number;
    /** The UTC date and time (in RFC3339 format) of when the broadcast began */
    startedAt: string;
    thumbnailUrl: string;
    isMature: boolean;
    language: string;
}

const { __ } = useLang();

const title = __('app.twitch');

const props = defineProps<{
    redirect: string;
    followedStreamers: FollowedStreamer[];
    statusOfFollowedStreamers: FollowedStreamerStream[];
    /** List of 'userId' streamers that has been marked as favorites */
    favoriteStreamers: string[];
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

const formatTwitchThumbnailUrl = (url: string, width: number, height: number): string => {
    return url.replace('{width}', width.toString()).replace('{height}', height.toString());
};

const redirectToTwitch = (username: string) => {
    window.open(`https://www.twitch.tv/${username}`, '_blank');
};

const formatRelativeDate = (startedAt: string) => {
    return new Intl.RelativeTimeFormat('fr', { numeric: 'auto' }).format(
        -Math.floor((new Date().getTime() - new Date(startedAt).getTime()) / 60000),
        'minute',
    );
};
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

        <h1 class="mt-6 mb-2 px-4 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Mes suivis en live</h1>
        <div>
            <section class="card-inner grid grid-cols-1 gap-x-4 gap-y-6 p-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8">
                <div
                    v-for="streamer in props.statusOfFollowedStreamers"
                    :key="streamer.userId"
                    @click="() => redirectToTwitch(streamer.userName)"
                    class="flex cursor-pointer flex-col overflow-hidden rounded-lg bg-white shadow-md transition-all hover:scale-105 dark:bg-[#121212]"
                    :class="{
                        'favorite-border': props.favoriteStreamers.includes(streamer.userId),
                    }"
                >
                    <div class="relative h-28">
                        <img
                            :src="streamer.thumbnailUrl ? formatTwitchThumbnailUrl(streamer.thumbnailUrl, 480, 270) : 'http://placebeard.it/640/480'"
                            :alt="streamer.userName"
                            class="h-full w-full object-cover"
                        />
                        <div class="absolute top-0 h-28 w-full object-cover"></div>
                        <div
                            v-if="streamer.type === 'live'"
                            class="absolute top-2 right-2 flex items-center gap-1 rounded-full bg-red-500 px-2 py-1 text-xs text-white"
                        >
                            <div class="h-2 w-2 animate-pulse rounded-full bg-white"></div>
                            LIVE
                        </div>
                    </div>
                    <div class="px-4 py-6">
                        <div class="space-between flex flex-wrap items-center justify-between">
                            <h3 class="flex-1 text-base font-bold">{{ streamer.userName }}</h3>
                            <small class="text-xs text-gray-400">{{ streamer.viewerCount }} viewers</small>
                        </div>
                        <div
                            v-if="streamer.type === 'live'"
                            class="flex items-center justify-between gap-2 text-xs font-semibold text-gray-400 dark:text-gray-200"
                        >
                            <p>{{ streamer.gameName }}</p>
                            <p class="hidden text-[.65rem] text-gray-500 dark:text-gray-400">{{ streamer.userId }}</p>
                        </div>
                        <p class="text-xs/4 text-gray-500 dark:text-gray-400">Commencé {{ formatRelativeDate(streamer.startedAt) }}</p>
                    </div>
                </div>
            </section>
        </div>
        <h1 class="mt-6 mb-2 px-4 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Mes suivis</h1>
        <section class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8">
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
        </section>
    </AppLayout>
</template>

<style>
.favorite-border {
    position: relative;
    border-radius: 0.5rem;
}

.favorite-border::before {
    content: '';
    position: absolute;
    inset: -2px; /* épaisseur */
    border-radius: inherit;
    padding: 5px;
    background: linear-gradient(270deg, #ff0080, #7928ca, #2afadf, #ff8c00);
    background-size: 600% 600%;
    animation: gradientBorder 8s ease infinite;
    -webkit-mask:
        linear-gradient(#fff 0 0) content-box,
        linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
}

@keyframes gradientBorder {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}
</style>
