<script setup lang="ts">
import { ChevronDownIcon, ChevronUpIcon } from '@radix-icons/vue';
import type { SelectRootEmits, SelectRootProps } from 'reka-ui';
import {
    SelectContent,
    SelectGroup,
    SelectIcon,
    SelectPortal,
    SelectRoot,
    SelectScrollDownButton,
    SelectScrollUpButton,
    SelectTrigger,
    SelectValue,
    SelectViewport,
    useForwardPropsEmits,
} from 'reka-ui';

const props = defineProps<SelectRootProps>();
const emits = defineEmits<SelectRootEmits>();

const forward = useForwardPropsEmits(props, emits);
</script>

<template>
    <SelectRoot v-bind="forward">
        <SelectTrigger
            class="inline-flex h-8 w-full items-center justify-between gap-2 rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground shadow-sm transition-colors placeholder:text-muted-foreground hover:bg-accent/30 focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-background focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
        >
            <SelectValue placeholder="Sélectionner..." class="truncate" />
            <SelectIcon class="text-muted-foreground">
                <ChevronDownIcon class="h-4 w-4" />
            </SelectIcon>
        </SelectTrigger>

        <SelectPortal>
            <SelectContent
                class="z-50 mt-1 max-h-64 min-w-[12rem] overflow-hidden rounded-md border border-border bg-popover text-popover-foreground shadow-lg focus:outline-none data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
                position="popper"
                :side-offset="6"
            >
                <SelectScrollUpButton class="flex h-8 cursor-default items-center justify-center bg-popover/70 text-muted-foreground">
                    <ChevronUpIcon class="h-4 w-4" />
                </SelectScrollUpButton>
                <SelectViewport class="p-1">
                    <SelectGroup>
                        <slot />
                    </SelectGroup>
                </SelectViewport>
                <SelectScrollDownButton class="flex h-8 cursor-default items-center justify-center bg-popover/70 text-muted-foreground">
                    <ChevronDownIcon class="h-4 w-4" />
                </SelectScrollDownButton>
            </SelectContent>
        </SelectPortal>
    </SelectRoot>
</template>
