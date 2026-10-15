<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { update } from '@/routes/lol-settings';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
    reminderMinutes: number | null;
    reminderOptions: number[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'League of Legends settings', href: '/settings/lol' },
];

const OFF = 'off';

const form = useForm({
    reminder_minutes: props.reminderMinutes,
});

const selected = computed({
    get: () =>
        form.reminder_minutes === null ? OFF : String(form.reminder_minutes),
    set: (value: string) => {
        form.reminder_minutes = value === OFF ? null : Number(value);
    },
});

const save = () => {
    form.submit(update(), { preserveScroll: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="League of Legends settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="League of Legends"
                    description="Choose when to be reminded before a followed match starts"
                />

                <form class="space-y-6" @submit.prevent="save">
                    <div class="grid max-w-xs gap-2">
                        <Label>Reminder before a match</Label>
                        <Select v-model="selected">
                            <SelectItem :value="OFF">No reminder</SelectItem>
                            <SelectItem
                                v-for="minutes in reminderOptions"
                                :key="minutes"
                                :value="String(minutes)"
                                >{{ minutes }} minutes</SelectItem
                            >
                        </Select>
                        <InputError :message="form.errors.reminder_minutes" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">Save</Button>
                        <p
                            v-show="form.recentlySuccessful"
                            class="text-sm text-neutral-600"
                        >
                            Saved.
                        </p>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
