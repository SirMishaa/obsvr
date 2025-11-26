<script setup lang="ts">
import { getStreamerEvents } from '@/actions/App/Http/Controllers/TwitchController';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import type { TwitchEvent } from '@/types';
import { router } from '@inertiajs/vue3';
import { History } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    streamerId: string;
    streamerName: string;
}>();

const isOpen = ref(false);
const loadedEvents = ref<TwitchEvent[]>([]);
const isLoading = ref(false);

watch(isOpen, (newVal) => {
    if (!newVal || loadedEvents.value.length > 0) return;

    isLoading.value = true;

    router.get(
        getStreamerEvents(props.streamerId),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['streamerEvents'],
            onSuccess: (page) => {
                loadedEvents.value = page.props.streamerEvents;
            },
            onFinish: () => {
                isLoading.value = false;
            },
        },
    );
});

const generateMockHistoryForStreamer = (streamerId: string, streamerName: string): TwitchEvent[] => {
    const now = new Date();
    const events: TwitchEvent[] = [];

    for (let i = 0; i < 10; i++) {
        const daysAgo = Math.floor(i / 2);
        const hoursAgo = (i % 2) * 6;
        const eventDate = new Date(now);
        eventDate.setDate(eventDate.getDate() - daysAgo);
        eventDate.setHours(eventDate.getHours() - hoursAgo);

        const eventTypes: TwitchEvent['event_type'][] = ['stream.online', 'stream.offline', 'channel.update'];
        const eventType = eventTypes[i % 3];

        events.push({
            id: i + 1,
            event_id: `event-${streamerId}-${i}`,
            event_type: eventType,
            streamer_id: streamerId,
            streamer_name: streamerName,
            payload: {
                id: `event-${i}`,
                broadcaster_user_id: streamerId,
                broadcaster_user_name: streamerName,
                started_at: eventType === 'stream.online' ? eventDate.toISOString() : undefined,
                title: eventType === 'channel.update' ? `Stream title updated ${i}` : undefined,
                category_name: eventType === 'channel.update' ? 'Just Chatting' : undefined,
            },
            occurred_at: eventDate.toISOString(),
            received_at: eventDate.toISOString(),
        });
    }

    return events.sort((a, b) => new Date(b.occurred_at).getTime() - new Date(a.occurred_at).getTime());
};

const streamHistory = computed(() => {
    return loadedEvents.value.length > 0 ? loadedEvents.value : generateMockHistoryForStreamer(props.streamerId, props.streamerName);
});

// Todo: Refactor this with better date handling
const formatEventDate = (dateString: string): string => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInMs = now.getTime() - date.getTime();
    const diffInHours = Math.floor(diffInMs / (1000 * 60 * 60));
    const diffInDays = Math.floor(diffInHours / 24);

    if (diffInDays > 7) {
        return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    } else if (diffInDays > 0) {
        return `Il y a ${diffInDays} jour${diffInDays > 1 ? 's' : ''}`;
    } else if (diffInHours > 0) {
        return `Il y a ${diffInHours} heure${diffInHours > 1 ? 's' : ''}`;
    } else {
        const diffInMinutes = Math.floor(diffInMs / (1000 * 60));
        return `Il y a ${diffInMinutes} minute${diffInMinutes > 1 ? 's' : ''}`;
    }
};

const getEventIcon = (eventType: TwitchEvent['event_type']): string => {
    switch (eventType) {
        case 'stream.online':
            return '🟢';
        case 'stream.offline':
            return '🔴';
        case 'channel.update':
            return '✏️';
        default:
            return '📋';
    }
};

const getEventLabel = (event: TwitchEvent): string => {
    switch (event.event_type) {
        case 'stream.online':
            return 'Stream démarré';
        case 'stream.offline':
            return 'Stream terminé';
        case 'channel.update':
            return 'Informations mises à jour';
        default:
            return 'Événement';
    }
};

const getEventDescription = (event: TwitchEvent): string | null => {
    if (event.event_type === 'channel.update' && event.payload.title) {
        return event.payload.title;
    }
    if (event.event_type === 'channel.update' && event.payload.category_name) {
        return `Catégorie: ${event.payload.category_name}`;
    }
    return null;
};

const getEventColor = (eventType: TwitchEvent['event_type']): string => {
    switch (eventType) {
        case 'stream.online':
            return 'bg-green-500/10 border-green-500/20';
        case 'stream.offline':
            return 'bg-red-500/10 border-red-500/20';
        case 'channel.update':
            return 'bg-blue-500/10 border-blue-500/20';
        default:
            return 'bg-gray-500/10 border-gray-500/20';
    }
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <button
                class="absolute right-2 bottom-2 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-black/60 text-white transition-all hover:bg-black/80"
                @click.stop
            >
                <History :size="16" />
            </button>
        </DialogTrigger>
        <DialogContent class="max-h-[80vh] max-w-2xl overflow-hidden">
            <DialogHeader>
                <DialogTitle class="text-xl font-bold">Historique de {{ streamerName }}</DialogTitle>
                <DialogDescription> Derniers événements et streams </DialogDescription>
            </DialogHeader>

            <div class="mt-4 max-h-[60vh] space-y-3 overflow-y-auto pr-2">
                <!-- Loading skeleton -->
                <div v-if="isLoading" class="space-y-3">
                    <div v-for="i in 5" :key="i" class="group relative">
                        <div class="flex gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <div class="h-10 w-10 shrink-0 animate-pulse rounded-full bg-gray-200 dark:bg-gray-700"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-4 w-3/4 animate-pulse rounded bg-gray-200 dark:bg-gray-700"></div>
                                <div class="h-3 w-1/2 animate-pulse rounded bg-gray-200 dark:bg-gray-700"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-else-if="streamHistory.length === 0" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    Aucun événement enregistré pour ce streamer
                </div>

                <!-- Events list -->
                <div v-else v-for="event in streamHistory" :key="event.id" class="group relative">
                    <div
                        class="flex gap-3 rounded-lg border p-3 transition-all hover:shadow-md dark:hover:bg-white/5"
                        :class="getEventColor(event.event_type)"
                    >
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/50 text-xl dark:bg-black/20">
                            {{ getEventIcon(event.event_type) }}
                        </div>

                        <div class="flex-1 space-y-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ getEventLabel(event) }}
                                </p>
                                <time class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                                    {{ formatEventDate(event.occurred_at) }}
                                </time>
                            </div>

                            <p v-if="getEventDescription(event)" class="text-sm text-gray-600 dark:text-gray-300">
                                {{ getEventDescription(event) }}
                            </p>

                            <div v-if="event.payload.category_name" class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <span class="rounded-full bg-purple-500/10 px-2 py-0.5 font-medium text-purple-700 dark:text-purple-300">
                                    {{ event.payload.category_name }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="event !== streamHistory[streamHistory.length - 1]"
                        class="absolute top-[52px] left-[32px] h-3 w-0.5 bg-gray-200 dark:bg-gray-700"
                    ></div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
