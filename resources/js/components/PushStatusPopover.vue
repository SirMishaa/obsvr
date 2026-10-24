<script setup lang="ts">
import {
    Bell,
    BellOff,
    BellRing,
    Check,
    LoaderCircle,
    Send,
    TriangleAlert,
    Wrench,
    X,
} from 'lucide-vue-next';
import { computed, toRef } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    type PushHealth,
    usePushNotifications,
} from '@/composables/usePushNotifications';

const props = defineProps<{
    /** Push endpoints the server has stored for the current user. */
    endpoints: string[];
}>();

const {
    checks,
    health,
    isWorking,
    lastError,
    lastPushAt,
    testState,
    ensureHealthy,
    sendTest,
} = usePushNotifications(toRef(props, 'endpoints'));

const healthDisplay: Record<
    PushHealth,
    { label: string; description: string; icon: unknown; class: string }
> = {
    checking: {
        label: 'Vérification…',
        description: 'Contrôle du lien de notification en cours.',
        icon: LoaderCircle,
        class: 'animate-spin text-gray-400',
    },
    unsupported: {
        label: 'Non supporté',
        description: 'Ce navigateur ne gère pas les notifications push.',
        icon: BellOff,
        class: 'text-gray-400',
    },
    denied: {
        label: 'Bloquées',
        description:
            'Les notifications sont bloquées pour ce site. Autorise-les dans les réglages du navigateur.',
        icon: BellOff,
        class: 'text-red-500',
    },
    prompt: {
        label: 'Désactivées',
        description: 'Les notifications ne sont pas encore activées ici.',
        icon: Bell,
        class: 'text-amber-500',
    },
    broken: {
        label: 'Lien cassé',
        description:
            'Un maillon de la chaîne est rompu, les notifications risquent de ne pas arriver.',
        icon: TriangleAlert,
        class: 'text-red-500',
    },
    healthy: {
        label: 'Opérationnel',
        description: 'Ce navigateur est bien abonné aux notifications.',
        icon: BellRing,
        class: 'text-green-500',
    },
};

const display = computed(() => healthDisplay[health.value]);

const checkRows = computed(() => [
    { label: 'Navigateur compatible', ok: checks.value.supported },
    {
        label: 'Permission accordée',
        ok: checks.value.permission === 'granted',
    },
    { label: 'Service worker actif', ok: checks.value.serviceWorkerActive },
    { label: 'Abonnement push', ok: checks.value.hasSubscription },
    { label: 'Clé VAPID à jour', ok: checks.value.keyMatches },
    { label: 'Connu du serveur', ok: checks.value.knownByServer },
]);

const testLabel = computed(
    () =>
        ({
            idle: null,
            sending: 'Test envoyé, en attente de réception…',
            received: 'Notification de test reçue.',
            missed: 'Notification de test non reçue. Répare le lien pour créer un nouvel abonnement.',
        })[testState.value],
);

const formattedLastPush = computed(() => {
    if (!lastPushAt.value) return 'jamais (sur ce navigateur)';
    const minutes = Math.round(
        (new Date(lastPushAt.value).getTime() - Date.now()) / 60000,
    );
    const formatter = new Intl.RelativeTimeFormat('fr', { numeric: 'auto' });
    if (Math.abs(minutes) < 60) return formatter.format(minutes, 'minute');
    if (Math.abs(minutes) < 1440)
        return formatter.format(Math.round(minutes / 60), 'hour');
    return formatter.format(Math.round(minutes / 1440), 'day');
});
</script>

<template>
    <Popover>
        <PopoverTrigger
            class="inline-flex cursor-pointer items-center rounded-full p-1.5 transition-colors hover:bg-gray-100 dark:hover:bg-white/10"
            :aria-label="`État des notifications : ${display.label}`"
        >
            <component :is="display.icon" :size="22" :class="display.class" />
        </PopoverTrigger>
        <PopoverContent align="start" class="w-80">
            <div class="flex items-center gap-2">
                <component
                    :is="display.icon"
                    :size="18"
                    :class="display.class"
                />
                <p class="font-semibold">Notifications : {{ display.label }}</p>
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ display.description }}
            </p>

            <ul class="mt-3 space-y-1 text-sm">
                <li
                    v-for="row in checkRows"
                    :key="row.label"
                    class="flex items-center gap-2"
                >
                    <Check v-if="row.ok" :size="14" class="text-green-500" />
                    <X v-else :size="14" class="text-red-500" />
                    {{ row.label }}
                </li>
            </ul>

            <p class="mt-3 text-xs text-muted-foreground">
                Dernière notification reçue : {{ formattedLastPush }}
            </p>
            <p
                v-if="testLabel"
                class="mt-1 text-xs"
                :class="{
                    'text-muted-foreground': testState === 'sending',
                    'text-green-600 dark:text-green-400':
                        testState === 'received',
                    'text-red-600 dark:text-red-400': testState === 'missed',
                }"
            >
                {{ testLabel }}
            </p>
            <p
                v-if="lastError"
                class="mt-1 text-xs text-red-600 dark:text-red-400"
            >
                {{ lastError }}
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
                <Button
                    v-if="health === 'prompt'"
                    size="sm"
                    :disabled="isWorking"
                    @click="ensureHealthy({ prompt: true })"
                >
                    <Bell />
                    Activer
                </Button>
                <template v-else-if="checks.permission === 'granted'">
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="isWorking || testState === 'sending'"
                        @click="sendTest"
                    >
                        <Send />
                        Envoyer un test
                    </Button>
                    <Button
                        size="sm"
                        :variant="health === 'broken' ? 'default' : 'ghost'"
                        :disabled="isWorking"
                        @click="ensureHealthy({ force: true })"
                    >
                        <Wrench />
                        Réparer le lien
                    </Button>
                </template>
            </div>
        </PopoverContent>
    </Popover>
</template>
