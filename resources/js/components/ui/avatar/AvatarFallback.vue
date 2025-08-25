<script setup lang="ts">
import { cn } from '@/lib/utils';
import { AvatarFallback, type AvatarFallbackProps } from 'reka-ui';
import { computed, CSSProperties, type HTMLAttributes } from 'vue';

interface Props extends AvatarFallbackProps {
    class?: HTMLAttributes['class'];
    _avatarUrl?: string | null;
}

const props = defineProps<Props>();
const delegatedProps = computed(() => {
    const { class: _, _avatarUrl, ...rest } = props;
    return rest;
});
const fallbackStyle = computed<CSSProperties>(
    (): CSSProperties =>
        props._avatarUrl
            ? {}
            : {
                  backgroundImage: `url(${props._avatarUrl})`,
                  backgroundSize: 'cover',
                  backgroundPosition: 'center',
              },
);
</script>
<template>
    <AvatarFallback
        data-slot="avatar-fallback"
        v-bind="delegatedProps"
        :style="fallbackStyle"
        :class="cn('flex size-full items-center justify-center rounded-full bg-muted', props.class)"
    >
        <slot />
    </AvatarFallback>
</template>
