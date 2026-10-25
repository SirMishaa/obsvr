<script setup lang="ts">
import { computed } from 'vue';
import type { SwissMatch } from '@/lib/lolSwiss';
import type { LolBracketTeam } from '@/types/lol';

const props = defineProps<{ match: SwissMatch | null }>();

const emit = defineEmits<{ pick: [code: string] }>();

const regionColors: Record<string, string> = {
    LCK: 'bg-sky-500',
    LPL: 'bg-red-500',
    LEC: 'bg-teal-400',
    LCS: 'bg-indigo-400',
    LCP: 'bg-orange-400',
    CBLOL: 'bg-green-500',
    LTA: 'bg-purple-400',
};

const teams = computed<(LolBracketTeam | null)[]>(() =>
    props.match?.teams.length === 2
        ? props.match.teams.map((team) => (team.code === 'TBD' ? null : team))
        : [null, null],
);

const score = computed(() => {
    const [a, b] = props.match?.teams ?? [];
    if (
        !a ||
        !b ||
        props.match?.simulated ||
        (props.match?.bestOf ?? 1) <= 1 ||
        a.gameWins === null ||
        b.gameWins === null ||
        a.gameWins + b.gameWins === 0
    ) {
        return null;
    }
    return `${a.gameWins}-${b.gameWins}`;
});

const teamState = (team: LolBracketTeam | null) => {
    if (!team || !props.match?.winner) {
        return 'pending';
    }
    return team.code === props.match.winner ? 'won' : 'lost';
};
</script>

<template>
    <div
        class="grid h-14 grid-cols-[1fr_auto_1fr] items-center rounded-md bg-muted/60 text-sm dark:bg-muted/40"
        :class="{ 'ring-1 ring-red-600': match?.state === 'inProgress' }"
    >
        <template v-for="(team, index) in teams" :key="index">
            <span
                v-if="index === 1"
                class="px-1 text-xs font-semibold text-muted-foreground tabular-nums"
            >
                {{ score ?? 'VS' }}
            </span>
            <component
                :is="match?.pickable && team ? 'button' : 'div'"
                :type="match?.pickable && team ? 'button' : undefined"
                :data-pick="match?.pickable && team ? team.code : undefined"
                class="flex h-full min-w-0 items-center gap-2 px-2"
                :class="[
                    index === 1 ? 'flex-row-reverse text-right' : 'text-left',
                    match?.pickable && team
                        ? 'cursor-pointer rounded-md transition-colors hover:bg-foreground/10'
                        : '',
                ]"
                :title="
                    match?.pickable && team
                        ? `Simulate a ${team.code} win`
                        : team?.name
                "
                @click="match?.pickable && team && emit('pick', team.code)"
            >
                <span
                    class="h-8 w-1 shrink-0 rounded-full"
                    :class="
                        team?.homeLeague
                            ? (regionColors[team.homeLeague] ??
                              'bg-muted-foreground/50')
                            : 'bg-transparent'
                    "
                />
                <span class="flex min-w-0 flex-col leading-tight">
                    <span
                        class="truncate text-[10px] text-muted-foreground uppercase"
                        >{{ team?.homeLeague ?? '&nbsp;' }}</span
                    >
                    <span
                        class="truncate font-bold tracking-wide"
                        :class="{
                            'text-muted-foreground/60 line-through decoration-1':
                                teamState(team) === 'lost',
                            'text-emerald-600 dark:text-emerald-400':
                                teamState(team) === 'won' && !match?.simulated,
                            'text-amber-600 italic dark:text-amber-400':
                                teamState(team) === 'won' && match?.simulated,
                        }"
                        >{{ team?.code ?? '' }}</span
                    >
                </span>
                <img
                    v-if="team?.image"
                    :src="team.image"
                    :alt="team.name"
                    class="size-6 shrink-0 object-contain"
                    :class="{ 'opacity-40': teamState(team) === 'lost' }"
                />
            </component>
        </template>
    </div>
</template>
