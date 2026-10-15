<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Bell, BellOff } from 'lucide-vue-next';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toggle, update } from '@/routes/lol/leagues';
import type { LolLeague } from '@/types/lol';

const props = defineProps<{ league: LolLeague }>();

const form = useForm({
    mode: props.league.mode,
    team_codes: [...props.league.teamCodes],
    costream_channel: props.league.costreamChannel ?? '',
});

/** Scheduled teams plus already selected ones that are no longer scheduled, so they stay deselectable. */
const teamChips = computed(() => [
    ...props.league.teams,
    ...form.team_codes
        .filter(
            (code) => !props.league.teams.some((team) => team.code === code),
        )
        .map((code) => ({ code, name: code, image: '' })),
]);

const toggleFollow = () => {
    router.post(toggle.url(props.league.slug), {}, { preserveScroll: true });
};

const toggleTeam = (code: string) => {
    form.team_codes = form.team_codes.includes(code)
        ? form.team_codes.filter((teamCode) => teamCode !== code)
        : [...form.team_codes, code];
};

const save = () => {
    form.submit(update(props.league.slug), { preserveScroll: true });
};
</script>

<template>
    <div
        class="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <div class="flex items-center gap-4">
            <img
                :src="league.image"
                :alt="league.label"
                class="size-12 rounded-md bg-neutral-900 object-contain p-1"
            />
            <span class="flex-1 font-medium">{{ league.label }}</span>
            <Button
                :variant="league.subscribed ? 'default' : 'outline'"
                size="sm"
                @click="toggleFollow"
            >
                <Bell v-if="league.subscribed" class="size-4" />
                <BellOff v-else class="size-4" />
                {{ league.subscribed ? 'Following' : 'Follow' }}
            </Button>
        </div>

        <form
            v-if="league.subscribed"
            class="flex flex-col gap-4"
            @submit.prevent="save"
        >
            <div class="flex gap-2">
                <Button
                    type="button"
                    size="sm"
                    :variant="form.mode === 'all' ? 'default' : 'outline'"
                    @click="form.mode = 'all'"
                >
                    All matches
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :variant="form.mode === 'teams' ? 'default' : 'outline'"
                    @click="form.mode = 'teams'"
                >
                    My teams
                </Button>
            </div>

            <div v-if="form.mode === 'teams'" class="flex flex-col gap-2">
                <p
                    v-if="teamChips.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Teams appear once the schedule is synced.
                </p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="team in teamChips"
                        :key="team.code"
                        type="button"
                        :title="team.name"
                        :aria-pressed="form.team_codes.includes(team.code)"
                        class="flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors"
                        :class="
                            form.team_codes.includes(team.code)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border hover:bg-accent'
                        "
                        @click="toggleTeam(team.code)"
                    >
                        <img
                            v-if="team.image"
                            :src="team.image"
                            :alt="team.name"
                            class="size-4 object-contain"
                        />
                        {{ team.code }}
                    </button>
                </div>
                <InputError :message="form.errors.team_codes" />
            </div>

            <div class="grid gap-2">
                <Label :for="`costream-${league.slug}`"
                    >Co-streamer (Twitch)</Label
                >
                <Input
                    :id="`costream-${league.slug}`"
                    v-model="form.costream_channel"
                    placeholder="e.g. skyart"
                    autocomplete="off"
                />
                <InputError :message="form.errors.costream_channel" />
            </div>

            <div class="flex items-center gap-4">
                <Button type="submit" size="sm" :disabled="form.processing"
                    >Save</Button
                >
                <p
                    v-show="form.recentlySuccessful"
                    class="text-sm text-neutral-600"
                >
                    Saved.
                </p>
            </div>
        </form>
    </div>
</template>
