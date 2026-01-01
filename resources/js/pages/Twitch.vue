<script setup lang="ts">
import ScheduledStreamCard from '@/components/ScheduledStreamCard.vue';
import StreamHistoryDialog from '@/components/StreamHistoryDialog.vue';
import { useLang } from '@/composables/useLang';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, ScheduledStream } from '@/types';
import { levenshteinDistance, urlBase64ToUint8Array } from '@/utils';
import { Head, router } from '@inertiajs/vue3';
import { Clock, Eye } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

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

interface TwitchEventSubSubscriptionItem {
    id: string;
    status: string;
    type: string; // ex: 'stream.online'
    version: string; // ex: '1'
    condition: {
        broadcaster_user_id?: string | null;
    };
    createdAt: string; // ex: '2025-09-09T21:36:47.199265869Z'
    transport: {
        method: 'webhook' | 'eventsub';
        callback: string;
    };
    cost: number;
}

const { __ } = useLang();

const title = __('app.twitch');

const props = defineProps<{
    followedStreamers: FollowedStreamer[];
    statusOfFollowedStreamers: FollowedStreamerStream[];
    /** List of 'userId' streamers that has been marked as favorites */
    favoriteStreamers: string[];
    subscriptions: TwitchEventSubSubscriptionItem[] | null;
    scheduledStreams?: ScheduledStream[];
}>();
const countdown = ref<number>(120);
const cacheBust = ref<string>('');
const inputSearch = ref<string>('');

const statusOfFollowedStreamersComp = computed(() => {
    if (inputSearch.value)
        return props.statusOfFollowedStreamers.filter((streamer) => {
            const streamerName = streamer.userName
                .normalize('NFD')
                /** Remove accents and diacritics */
                .replace(/[\u0300-\u036f]/g, '')
                /** Replace _ and - with spaces */
                .replace(/[_-]/g, ' ')
                .toLowerCase();
            const searchTerm = inputSearch.value
                .normalize('NFD')
                /** Remove accents and diacritics */
                .replace(/[\u0300-\u036f]/g, '')
                /** Replace _ and - with spaces */
                .replace(/[_-]/g, ' ')
                .toLowerCase();

            return streamerName.includes(searchTerm) || levenshteinDistance(streamerName, searchTerm) <= 2;
        });
    return props.statusOfFollowedStreamers;
});

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
    countdown.value = 120;
    const countdownInterval = setInterval(() => {
        countdown.value--;
        if (countdown.value <= 0) {
            countdown.value = 120;
            cacheBust.value = String(Date.now());
            router.reload({
                only: ['statusOfFollowedStreamers', 'favoriteStreamers', 'subscriptions'],
            });
        }
    }, 1000);
});

const formatTwitchThumbnailUrl = (url: string, width: number, height: number): URL => {
    const fullUrl = url.replace('{width}', width.toString()).replace('{height}', height.toString());
    const formattedUrl = new URL(fullUrl);

    /** Add cache busting query parameter to avoid image caching */
    if (cacheBust.value) formattedUrl.searchParams.set('cb', cacheBust.value);

    return formattedUrl;
};

const redirectToTwitch = (username: string) => {
    window.open(`https://www.twitch.tv/${username}`, '_blank');
};

const formatRelativeDate = (startedAt: string) => {
    return new Intl.RelativeTimeFormat('fr', { numeric: 'auto', style: 'short' }).format(
        -Math.floor((new Date().getTime() - new Date(startedAt).getTime()) / 60000),
        'minute',
    );
};

const enableWebPush = async () => {
    const vapidPublicKey = import.meta.env.VITE_VAPID_PUBLIC_KEY;
    if (!vapidPublicKey) {
        console.error('VITE_VAPID_PUBLIC_KEY is not defined');
        return;
    }

    const perm = await Notification.requestPermission();
    if (perm !== 'granted') console.warn('Permission not granted');
    const reg = await navigator.serviceWorker.register('/service-worker.js');
    if (!reg.pushManager) throw new Error('PushManager not supported');
    const sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
    });

    router.post(
        route('push.subscription'),
        {
            ...sub.toJSON(),
        },
        {
            onFinish: (data) => {
                console.log('Local subscription saved', data);
            },
            onError: (errors) => {
                console.error('Error saving local subscription', errors);
            },
        },
    );
};

const toggleFavoriteStreamerRework = async ({ streamerId, streamerName }: { streamerId: string; streamerName: string }) => {
    try {
        router.post(
            route('twitch.favorite', streamerId),
            {
                streamerName,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                onError: (errors) => console.error('Toggle favorite failed', errors),
                onFinish: () => {
                    console.log('Toggling favorite streamer for userId %s', streamerId);
                    /*favoriteStreamersComp.value.includes(streamerId)
                        ? favoriteStreamersComp.value.splice(favoriteStreamersComp.value.indexOf(streamerId), 1)
                        : favoriteStreamersComp.value.push(streamerId);*/
                },
            },
        );
    } catch (e) {
        console.error('Unexpected error while toggling favorite', e);
    }
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

        <!-- Scheduled Streams Section -->
        <div v-if="props.scheduledStreams && props.scheduledStreams.length > 0" class="mt-6">
            <div class="mb-4 px-4">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Prochains streams planifiés</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Streams à venir de vos streamers favoris</p>
            </div>

            <section class="grid grid-cols-1 gap-x-4 gap-y-6 p-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-7 2xl:grid-cols-8">
                <ScheduledStreamCard
                    v-for="schedule in props.scheduledStreams"
                    :key="schedule.broadcasterId"
                    :schedule="schedule"
                    :is-favorite="props.favoriteStreamers.includes(schedule.broadcasterId)"
                    @click="() => redirectToTwitch(schedule.broadcasterLogin)"
                />
            </section>
        </div>

        <div class="mt-6 flex items-center justify-between px-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Mes suivis en live</h1>
                <h2 class="text-base tracking-tight text-gray-900 dark:text-white">Actualisation dans {{ countdown }} secondes</h2>
                <button @click="enableWebPush" class="mt-2 cursor-pointer rounded bg-gray-800 p-2 text-white">Activer notification</button>
            </div>
            <input type="text" placeholder="Rechercher un streamer" class="border-b-2 border-gray-400 focus:outline-none" v-model="inputSearch" />
        </div>
        <div>
            <section
                class="card-inner grid grid-cols-1 gap-x-4 gap-y-6 p-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-7 2xl:grid-cols-8"
            >
                <div
                    v-for="streamer in statusOfFollowedStreamersComp"
                    :key="streamer.userId"
                    @click.exact="() => redirectToTwitch(streamer.userName)"
                    @click.ctrl="
                        () =>
                            toggleFavoriteStreamerRework({
                                streamerId: streamer.userId,
                                streamerName: streamer.userName,
                            })
                    "
                    class="group flex cursor-pointer flex-col overflow-hidden rounded-lg bg-white shadow-md transition-all hover:scale-105 dark:bg-[#121212]"
                    :class="{
                        'favorite-border': props.favoriteStreamers.includes(streamer.userId),
                    }"
                >
                    <div class="relative h-32">
                        <img
                            :src="
                                streamer.thumbnailUrl
                                    ? formatTwitchThumbnailUrl(streamer.thumbnailUrl, 480, 270).toString()
                                    : 'http://placebeard.it/640/480'
                            "
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
                        <div
                            v-if="props.subscriptions?.some((sub) => sub.condition.broadcaster_user_id === streamer.userId)"
                            class="absolute top-2 left-2 flex items-center gap-1 rounded-full px-2 py-1 text-xs text-white"
                            :class="{
                                'bg-green-500': props.subscriptions?.some(
                                    (sub) => sub.condition.broadcaster_user_id === streamer.userId && sub.status === 'enabled',
                                ),
                                'bg-orange-500': props.subscriptions?.some(
                                    (sub) =>
                                        sub.condition.broadcaster_user_id === streamer.userId &&
                                        sub.status === 'webhook_callback_verification_failed',
                                ),
                            }"
                        >
                            <div class="h-2 w-2 animate-pulse rounded-full bg-white text-xs"></div>
                            RealTime
                        </div>
                        <StreamHistoryDialog
                            v-if="props.favoriteStreamers.includes(streamer.userId)"
                            :streamer-id="streamer.userId"
                            :streamer-name="streamer.userName"
                        />
                    </div>
                    <div class="px-4 py-6">
                        <h3 class="text-base font-bold">{{ streamer.userName }}</h3>
                        <div v-if="streamer.title" class="mt-1 overflow-hidden">
                            <p class="stream-title text-sm text-gray-700 dark:text-gray-300">
                                {{ streamer.title }}
                            </p>
                        </div>
                        <div v-if="streamer.type === 'live'" class="mt-2 text-xs font-semibold text-gray-400 dark:text-gray-200">
                            <p>{{ streamer.gameName }}</p>
                        </div>
                        <div class="mt-2 flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                            <div class="flex items-center gap-1">
                                <Eye :size="14" />
                                <span>{{ streamer.viewerCount }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <Clock :size="14" />
                                <span>{{ formatRelativeDate(streamer.startedAt) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <template v-if="false">
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
        </template>
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
    inset: -2px;
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

.stream-title {
    position: relative;
    overflow: hidden;
    max-height: 3.9em;
    line-height: 1.4;
    transition: max-height 0.25s ease-in-out;
}

.stream-title::after {
    content: '...';
    position: absolute;
    bottom: 0;
    right: 0;
    background: white;
    padding-left: 0.5em;
    opacity: 1;
    transition: opacity 0.25s ease-in-out;
}

.dark .stream-title::after {
    background: #121212;
}

.group:hover .stream-title {
    max-height: 15em;
}

.group:hover .stream-title::after {
    opacity: 0;
}
</style>
