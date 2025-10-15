<script setup lang="ts">
import { update } from '@/actions/App/Http/Controllers/Settings/SubscriptionsController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem, FavouriteStreamer } from '@/types';
import { Form, Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Props {
    favouriteStreamers: Array<FavouriteStreamer>;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Profile subscriptions',
        href: '/settings/subscriptions',
    },
];

const selections = ref<Record<number, any[]>>(
    Object.fromEntries(
        props.favouriteStreamers.map((s) => [
            s.streamer_id,
            s.subscriptions
                //.filter((sub) => sub.status === 'enabled')
                .map((sub) => sub.type),
        ]),
    ),
);

const EVENT_TYPES = new Set(['stream.online', 'stream.offline', 'channel.update']);

function submit(subscriptionId: number, streamerId: number) {
    const selected = selections.value[streamerId] ?? [];
    router.put(
        update({ id: subscriptionId }),
        { favourite_streamer_id: subscriptionId, types: selected },
        { preserveScroll: true, showProgress: true },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profile subscriptions" />
        <SettingsLayout>
            <div class="my-6 flex flex-col">
                <HeadingSmall title="Profile subscriptions" description="Manage your profile subscriptions" />
            </div>
            <table class="h-full w-full text-left text-sm text-muted-foreground">
                <thead class="bg-background text-xs text-muted-foreground uppercase">
                    <tr>
                        <th scope="col" class="px-6 py-3">Streamer</th>
                        <th scope="col" class="px-6 py-3">Subscription Type</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="streamer in favouriteStreamers" :key="streamer.streamer_id">
                        <td class="px-6 py-3 font-medium whitespace-nowrap text-gray-300">
                            <p>{{ streamer.streamer_name }}</p>
                        </td>
                        <td class="px-6 py-3 font-medium whitespace-nowrap text-gray-400">
                            <ul>
                                <!--                                <template v-for="subscription in streamer.subscriptions" :key="subscription.id">
                                    <li>
                                        <a href="#" class="hover:underline">{{ subscription.type }}</a>
                                        <small class="ml-1.5 text-gray-500">({{ subscription.status }})</small>
                                    </li>
                                </template>-->
                                <li class="mt-1 flex items-center">
                                    <Form
                                        :action="update({ id: streamer.id })"
                                        method="post"
                                        :transform="(data) => ({ ...data, types: selections[streamer.streamer_id] })"
                                    >
                                        <!-- FIXME: For a unknown reason, the select component is not passed to the form, even with name attribute -->
                                        <Select :multiple="true" v-model="selections[streamer.streamer_id]">
                                            <SelectItem v-for="event in EVENT_TYPES" :key="event" :value="event">{{ event }}</SelectItem>
                                        </Select>
                                        <button
                                            type="submit"
                                            class="mx-2 inline-flex h-8 w-8 items-center justify-center rounded-md bg-green-100 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-100"
                                        >
                                            ✓
                                        </button>
                                    </Form>
                                </li>
                            </ul>
                        </td>
                    </tr>
                </tbody>
            </table>
        </SettingsLayout>
    </AppLayout>
</template>
