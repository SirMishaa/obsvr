<script setup lang="ts">
import { getStreamerEvents } from '@/actions/App/Http/Controllers/TwitchController';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import type { TwitchEvent } from '@/types';
import { Temporal } from '@js-temporal/polyfill';
import { History } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    streamerId: string;
    streamerName: string;
}>();

const isOpen = ref(false);
const loadedEvents = ref<TwitchEvent[]>([]);
const isLoading = ref(false);
const errors = ref<string | null>(null);

watch(isOpen, async (newVal) => {
    if (!newVal || loadedEvents.value.length > 0) return;

    isLoading.value = true;
    errors.value = null;

    try {
        const [response] = await Promise.all([
            fetch(getStreamerEvents.url(props.streamerId), {
                headers: {
                    Accept: 'application/json',
                },
            }),
            new Promise((resolve) => setTimeout(resolve, 400)),
        ]);

        if (!response.ok) throw new Error('Failed to load events');
        loadedEvents.value = await response.json();
    } catch (err) {
        errors.value = err instanceof Error ? err.message : 'Une erreur est survenue';
    } finally {
        isLoading.value = false;
    }
});

const streamHistory = computed(() => (loadedEvents.value.length > 0 ? loadedEvents.value : []));

/**
 * Formats a given date string into a relative or absolute date based on its proximity to the current time.
 *
 * If the time difference between the current time and the given date is greater than one week, the function returns
 * the date formatted as a localized string in "fr-FR" format, including the day, short month, year, hour, and minute.
 *
 * If the difference is less than one week, it returns a relative time string in French, indicating the difference
 * in days, hours, or minutes using the Temporal API and Intl.RelativeTimeFormat.
 *
 * @param {string} dateString - The input date as an ISO 8601 string to be formatted.
 * @returns {string} A formatted string representing the date, either in absolute or relative terms.
 */
const formatEventDate = (dateString: string): string => {
    const eventTime = Temporal.Instant.from(dateString);
    const now = Temporal.Now.instant();
    const duration = now.since(eventTime);

    const days = duration.total('days');
    if (days > 7) {
        return eventTime.toLocaleString('fr-FR', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    const rtf = new Intl.RelativeTimeFormat('fr', { numeric: 'auto' });

    if (days >= 1) return rtf.format(-Math.round(days), 'day');

    const hours = duration.total('hours');
    if (hours >= 1) return rtf.format(-Math.round(hours), 'hour');

    const minutes = duration.total('minutes');
    return rtf.format(-Math.round(minutes), 'minute');
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

                <!-- Error state -->
                <div v-else-if="errors" class="py-8 text-center text-sm text-red-500">
                    {{ errors }}
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
