<script setup lang="ts">
import { cn } from '@/lib/utils'
import { AvatarFallback, type AvatarFallbackProps } from 'reka-ui'
import { computed, CSSProperties, type HTMLAttributes } from 'vue';

interface Props extends AvatarFallbackProps {
    class?: HTMLAttributes['class']
    avatarUrl?: string | null
}

const props = defineProps<Props>()
const delegatedProps = computed(() => {
    const { class: _, avatarUrl, ...rest } = props
    return rest
})
const fallbackStyle = computed<CSSProperties>((): CSSProperties =>
    props.avatarUrl ? {} : {
        'backgroundImage': `url(${props.avatarUrl})`,
        'backgroundSize': 'cover',
        'backgroundPosition': 'center'
    })


</script>

<template>
  <AvatarFallback
    data-slot="avatar-fallback"
    v-bind="delegatedProps"
    :style="fallbackStyle"
    :class="cn('bg-muted flex size-full items-center justify-center rounded-full', props.class)"
  >
    <slot />
  </AvatarFallback>
</template>
