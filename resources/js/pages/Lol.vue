<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Bell, BellOff } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { toggle } from '@/routes/lol/leagues';
import { type BreadcrumbItem } from '@/types';

type League = {
    slug: string;
    label: string;
    image: string;
    subscribed: boolean;
};

defineProps<{ leagues: League[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'League of Legends', href: '/lol' },
];

const toggleLeague = (league: League) => {
    router.post(toggle.url(league.slug), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="League of Legends" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <p class="text-sm text-muted-foreground">
                Follow a league to get a reminder before each match and a
                notification when it starts.
            </p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="league in leagues"
                    :key="league.slug"
                    class="flex items-center gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <img
                        :src="league.image"
                        :alt="league.label"
                        class="size-12 rounded-md bg-neutral-900 object-contain p-1"
                    />
                    <span class="flex-1 font-medium">{{ league.label }}</span>
                    <Button
                        :variant="league.subscribed ? 'default' : 'outline'"
                        size="sm"
                        @click="toggleLeague(league)"
                    >
                        <Bell v-if="league.subscribed" class="size-4" />
                        <BellOff v-else class="size-4" />
                        {{ league.subscribed ? 'Following' : 'Follow' }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
