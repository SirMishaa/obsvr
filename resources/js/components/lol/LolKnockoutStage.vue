<script setup lang="ts">
import LolBracketMatchCard from '@/components/lol/LolBracketMatchCard.vue';
import type { SwissMatch } from '@/lib/lolSwiss';
import type {
    LolBracketColumn,
    LolBracketMatch,
    LolKnockoutSection,
} from '@/types/lol';

defineProps<{ section: LolKnockoutSection }>();

const dayFormat = new Intl.DateTimeFormat('fr-BE', {
    timeZone: 'Europe/Brussels',
    weekday: 'short',
    day: 'numeric',
    month: 'short',
});

const columnTitle = (column: LolBracketColumn) =>
    column.title ??
    (column.matches[0]
        ? dayFormat.format(new Date(column.matches[0].startTime))
        : '');

const toCard = (match: LolBracketMatch): SwissMatch => ({
    key: match.id,
    startTime: match.startTime,
    bestOf: match.bestOf,
    state: match.state,
    teams: match.teams,
    winner: match.teams.find((team) => team.outcome === 'win')?.code ?? null,
    simulated: false,
    pickable: false,
});
</script>

<template>
    <div class="overflow-x-auto pb-2">
        <div class="flex min-w-max gap-4">
            <div
                v-for="column in section.columns"
                :key="column.title ?? column.matches[0]?.id"
                class="flex w-60 flex-col gap-1"
            >
                <div
                    class="rounded-sm bg-foreground/10 px-2 py-0.5 text-xs font-semibold uppercase"
                >
                    {{ columnTitle(column) }}
                </div>
                <div class="flex flex-1 flex-col justify-around gap-2">
                    <LolBracketMatchCard
                        v-for="match in column.matches"
                        :key="match.id"
                        :match="toCard(match)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
