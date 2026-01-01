<script setup lang="ts">
import { ScheduledStream } from '@/types';
import { Temporal } from '@js-temporal/polyfill';
import { Calendar, Clock, Repeat, Tv } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    schedule: ScheduledStream;
    isFavorite?: boolean;
}>();

const emit = defineEmits<{
    click: [];
}>();

const formatScheduledDate = (dateString: string): string => {
    const scheduleTime = Temporal.Instant.from(dateString);
    const now = Temporal.Now.instant();
    const duration = scheduleTime.since(now);

    const days = duration.total('days');
    if (days > 7) {
        return scheduleTime.toLocaleString('fr-FR', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    const rtf = new Intl.RelativeTimeFormat('fr', { numeric: 'auto' });

    if (days >= 1) return rtf.format(Math.round(days), 'day');

    const hours = duration.total('hours');
    if (hours >= 1) return rtf.format(Math.round(hours), 'hour');

    const minutes = duration.total('minutes');
    return rtf.format(Math.round(minutes), 'minute');
};

const formattedDate = computed(() => {
    if (!props.schedule?.nextSegment) return '';
    return formatScheduledDate(props.schedule.nextSegment.startTime);
});
</script>

<template>
    <div
        @click="emit('click')"
        class="group scheduled-stream-card flex cursor-pointer flex-col overflow-hidden rounded-lg bg-white shadow-md transition-all hover:scale-105 dark:bg-[#121212]"
    >
        <div class="relative h-32 overflow-hidden bg-linear-to-br from-purple-500/20 to-blue-500/20 dark:from-purple-500/10 dark:to-blue-500/10">
            <div
                class="absolute inset-0 text-purple-500/30 dark:text-purple-400/20"
                style="background-image: radial-gradient(circle, currentColor 1px, transparent 1px); background-size: 12px 12px"
            ></div>

            <div class="absolute top-2 right-2 flex items-center gap-1 rounded-full bg-purple-500 px-2 py-1 text-xs font-medium text-white shadow-lg">
                <Calendar :size="12" />
                Planifié
            </div>

            <div
                class="absolute bottom-2 left-2 flex h-12 w-12 items-center justify-center rounded-full bg-linear-to-br from-purple-600 to-blue-600 text-white shadow-lg"
            >
                <Tv :size="24" />
            </div>
        </div>

        <div class="flex flex-col gap-2 px-4 py-6">
            <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ schedule.broadcasterName }}</h3>

            <div v-if="schedule.nextSegment" class="flex items-center gap-1 text-sm font-semibold text-purple-600 dark:text-purple-400">
                <Clock :size="14" />
                <span>{{ formattedDate }}</span>
            </div>

            <div v-if="schedule.nextSegment?.title" class="overflow-hidden">
                <p class="stream-title text-sm text-gray-700 dark:text-gray-300">
                    {{ schedule.nextSegment.title }}
                </p>
            </div>

            <div v-if="schedule.nextSegment?.category" class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                <p>{{ schedule.nextSegment.category.name }}</p>
            </div>

            <div
                v-if="schedule.nextSegment?.isRecurring"
                class="mt-1 inline-flex w-fit items-center gap-1 rounded-full bg-gray-200 px-2 py-1 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-300"
            >
                <Repeat :size="12" />
                Récurrent
            </div>
        </div>
    </div>
</template>

<style scoped>
.scheduled-stream-card {
    border: 2px dashed rgba(168, 85, 247, 0.3);
}

.dark .scheduled-stream-card {
    border: 2px dashed rgba(168, 85, 247, 0.5);
}

.scheduled-stream-card:hover {
    border-style: solid;
    border-color: rgba(168, 85, 247, 0.6);
}

.dark .scheduled-stream-card:hover {
    border-color: rgba(168, 85, 247, 0.8);
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
