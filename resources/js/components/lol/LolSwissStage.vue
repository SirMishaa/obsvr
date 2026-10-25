<script setup lang="ts">
import { RotateCcw } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import LolBracketMatchCard from '@/components/lol/LolBracketMatchCard.vue';
import { Button } from '@/components/ui/button';
import {
    buildSwissColumns,
    type SwissPicks,
    type SwissPool,
} from '@/lib/lolSwiss';
import type { LolSwissSection } from '@/types/lol';

const props = defineProps<{ section: LolSwissSection }>();

const picks = ref<SwissPicks>({});

const columns = computed(() => buildSwissColumns(props.section, picks.value));

const hasPicks = computed(() => Object.keys(picks.value).length > 0);

const pick = (key: string, code: string) => {
    const { [key]: current, ...others } = picks.value;
    picks.value = current === code ? others : { ...others, [key]: code };
};

const dayFormat = new Intl.DateTimeFormat('fr-BE', {
    timeZone: 'Europe/Brussels',
    day: 'numeric',
    month: 'short',
});

const poolHeader = (pool: SwissPool) => {
    const scheduled = pool.matches.filter((match) => match?.startTime);
    const days = [
        ...new Set(
            scheduled.map((match) =>
                dayFormat.format(new Date(match!.startTime!)),
            ),
        ),
    ];
    const bestOf = scheduled[0]?.bestOf;

    return [days.join(' & '), bestOf ? `BO${bestOf}` : null]
        .filter(Boolean)
        .join(' · ');
};
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-muted-foreground">
                Click a team to simulate its win, click again to undo. Next
                rounds are drawn once a round is decided.
            </p>
            <Button
                variant="outline"
                size="sm"
                :disabled="!hasPicks"
                @click="picks = {}"
            >
                <RotateCcw class="size-4" />
                Official results
            </Button>
        </div>

        <div class="overflow-x-auto pb-2">
            <div class="flex min-w-max gap-4">
                <div
                    v-for="column in columns"
                    :key="column.title"
                    class="flex w-60 flex-col justify-center gap-4"
                >
                    <div
                        v-if="column.advanced"
                        class="flex flex-col gap-1"
                        data-test="swiss-advanced"
                    >
                        <div
                            class="flex justify-between rounded-sm bg-cyan-500 px-2 py-0.5 text-xs font-semibold text-cyan-950 uppercase"
                        >
                            <span>Advances</span>
                            <span>({{ column.advanced.record }})</span>
                        </div>
                        <div
                            class="flex min-h-9 flex-wrap items-center justify-center gap-x-3 rounded-md bg-muted/60 px-2 py-1 text-sm font-bold dark:bg-muted/40"
                        >
                            <span
                                v-for="(team, index) in column.advanced.teams"
                                :key="team?.code ?? `empty-${index}`"
                                :class="team ? '' : 'text-muted-foreground/40'"
                                >{{ team?.code ?? '—' }}</span
                            >
                        </div>
                    </div>

                    <div
                        v-for="pool in column.pools"
                        :key="pool.record"
                        class="flex flex-col gap-1"
                    >
                        <div
                            class="flex justify-between rounded-sm bg-foreground/10 px-2 py-0.5 text-xs font-semibold uppercase"
                        >
                            <span>{{ poolHeader(pool) }}</span>
                            <span>({{ pool.record }})</span>
                        </div>
                        <LolBracketMatchCard
                            v-for="(match, index) in pool.matches"
                            :key="match?.key ?? `${pool.record}-${index}`"
                            :match="match"
                            @pick="(code) => match && pick(match.key, code)"
                        />
                    </div>

                    <div
                        v-if="column.eliminated"
                        class="flex flex-col gap-1"
                        data-test="swiss-eliminated"
                    >
                        <div
                            class="flex justify-between px-2 text-xs font-semibold text-muted-foreground uppercase"
                        >
                            <span>Eliminated</span>
                            <span>({{ column.eliminated.record }})</span>
                        </div>
                        <div
                            class="flex min-h-9 flex-wrap items-center justify-center gap-x-3 rounded-md bg-muted/60 px-2 py-1 text-sm font-bold text-red-500 dark:bg-muted/40"
                        >
                            <span
                                v-for="(team, index) in column.eliminated.teams"
                                :key="team?.code ?? `empty-${index}`"
                                :class="team ? '' : 'text-muted-foreground/40'"
                                >{{ team?.code ?? '—' }}</span
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
