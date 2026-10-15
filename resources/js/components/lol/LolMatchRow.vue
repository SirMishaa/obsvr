<script setup lang="ts">
import { Bell, BellOff } from 'lucide-vue-next';
import { computed } from 'vue';
import type { LolUpcomingMatch } from '@/types/lol';

const props = defineProps<{ match: LolUpcomingMatch }>();

const startsAt = computed(() =>
    new Intl.DateTimeFormat('fr-BE', {
        timeZone: 'Europe/Brussels',
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(props.match.startTime)),
);

const details = computed(() =>
    [
        props.match.leagueLabel,
        props.match.blockName,
        props.match.bestOf ? `BO${props.match.bestOf}` : null,
    ]
        .filter(Boolean)
        .join(' · '),
);
</script>

<template>
    <li
        class="flex items-center gap-4 rounded-lg border border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
    >
        <span class="w-28 shrink-0 text-sm text-muted-foreground tabular-nums">
            {{ startsAt }}
        </span>
        <div class="flex flex-1 items-center gap-3">
            <template v-for="(team, index) in match.teams" :key="team.code">
                <span v-if="index > 0" class="text-xs text-muted-foreground"
                    >vs</span
                >
                <span class="flex items-center gap-1.5 font-medium">
                    <img
                        v-if="team.image"
                        :src="team.image"
                        :alt="team.name"
                        class="size-6 object-contain"
                    />
                    {{ team.code }}
                </span>
            </template>
        </div>
        <span class="hidden text-xs text-muted-foreground sm:inline">{{
            details
        }}</span>
        <span
            v-if="match.state === 'inProgress'"
            class="rounded bg-red-600 px-1.5 py-0.5 text-xs font-semibold text-white"
            >LIVE</span
        >
        <Bell
            v-if="match.notified"
            class="size-4 text-primary"
            aria-label="You will be notified"
        />
        <BellOff
            v-else
            class="size-4 text-muted-foreground/50"
            aria-label="Not notified"
        />
    </li>
</template>
