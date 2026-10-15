<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LolLeagueCard from '@/components/lol/LolLeagueCard.vue';
import LolMatchRow from '@/components/lol/LolMatchRow.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { LolLeague, LolUpcomingMatch } from '@/types/lol';

defineProps<{
    leagues: LolLeague[];
    upcomingMatches: LolUpcomingMatch[];
}>();

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
                <h2 class="text-lg font-semibold">Upcoming matches</h2>
                <p
                    v-if="upcomingMatches.length === 0"
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
