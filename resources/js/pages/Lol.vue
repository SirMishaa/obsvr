<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useStorage } from '@vueuse/core';
import { List, Network } from 'lucide-vue-next';
import { defineAsyncComponent } from 'vue';
import LolLeagueCard from '@/components/lol/LolLeagueCard.vue';
import LolMatchRow from '@/components/lol/LolMatchRow.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type {
    LolBracket,
    LolLeague,
    LolUpcomingMatch,
    LolViewMode,
} from '@/types/lol';

defineProps<{
    leagues: LolLeague[];
    upcomingMatches: LolUpcomingMatch[];
    brackets: LolBracket[];
}>();

const LolBracketView = defineAsyncComponent(
    () => import('@/components/lol/LolBracketView.vue'),
);

const viewMode = useStorage<LolViewMode>('lol-view-mode', 'list', undefined, {
    initOnMounted: true,
});

const viewModes: { value: LolViewMode; label: string; icon: typeof List }[] = [
    { value: 'list', label: 'List', icon: List },
    { value: 'tree', label: 'Tree', icon: Network },
];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'League of Legends', href: '/lol' },
];
</script>

<template>
    <Head title="League of Legends" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <p class="text-sm text-muted-foreground">
                Follow a league to get a reminder before each match and a
                notification when it starts. The reminder delay is set in
                Settings.
            </p>
            <div class="grid items-start gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <LolLeagueCard
                    v-for="league in leagues"
                    :key="`${league.slug}-${league.subscribed}`"
                    :league="league"
                />
            </div>

            <section class="flex flex-col gap-3">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">
                        {{
                            viewMode === 'tree'
                                ? 'Tournament tree'
                                : 'Upcoming matches'
                        }}
                    </h2>
                    <div
                        class="inline-flex rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800"
                        role="radiogroup"
                        aria-label="View mode"
                    >
                        <button
                            v-for="mode in viewModes"
                            :key="mode.value"
                            type="button"
                            role="radio"
                            :aria-checked="viewMode === mode.value"
                            class="flex items-center gap-1.5 rounded-md px-3 py-1 text-sm transition-colors"
                            :class="
                                viewMode === mode.value
                                    ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                                    : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60'
                            "
                            @click="viewMode = mode.value"
                        >
                            <component :is="mode.icon" class="size-4" />
                            {{ mode.label }}
                        </button>
                    </div>
                </div>
                <LolBracketView
                    v-if="viewMode === 'tree'"
                    :brackets="brackets"
                />
                <p
                    v-else-if="upcomingMatches.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No upcoming match in the leagues you follow.
                </p>
                <ul v-else class="flex flex-col gap-2">
                    <LolMatchRow
                        v-for="match in upcomingMatches"
                        :key="match.id"
                        :match="match"
                    />
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
