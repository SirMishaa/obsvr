<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import type { SelectItemProps } from 'reka-ui';
import { SelectItem, SelectItemIndicator, SelectItemText } from 'reka-ui';

const props = defineProps<SelectItemProps>();
</script>

<template>
    <SelectItem
        v-bind="props"
        class="relative flex w-full cursor-default items-center gap-2 rounded-sm px-2 py-2 text-sm transition-colors outline-none select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
    >
        <SelectItemIndicator class="absolute left-2 inline-flex h-4 w-4 items-center justify-center text-primary">
            <Check class="h-4 w-4" />
        </SelectItemIndicator>
        <SelectItemText class="pl-6">
            <slot />
        </SelectItemText>
    </SelectItem>
</template>
