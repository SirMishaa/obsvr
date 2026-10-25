<script setup lang="ts">
import LolKnockoutStage from '@/components/lol/LolKnockoutStage.vue';
import LolSwissStage from '@/components/lol/LolSwissStage.vue';
import type { LolBracket } from '@/types/lol';

defineProps<{ brackets: LolBracket[] }>();
</script>

<template>
    <div class="flex flex-col gap-8">
        <p v-if="brackets.length === 0" class="text-sm text-muted-foreground">
            No tournament in the leagues you follow.
        </p>
        <section
            v-for="bracket in brackets"
            :key="bracket.league"
            class="flex flex-col gap-4"
            data-test="lol-bracket"
        >
            <h3 class="font-semibold">{{ bracket.leagueLabel }}</h3>
            <div
                v-for="section in bracket.sections"
                :key="section.title"
                class="flex flex-col gap-2"
            >
                <h4
                    class="text-sm font-medium tracking-wide text-muted-foreground uppercase"
                >
                    {{ section.title }}
                </h4>
                <LolSwissStage
                    v-if="section.kind === 'swiss'"
                    :section="section"
                />
                <LolKnockoutStage v-else :section="section" />
            </div>
        </section>
    </div>
</template>
