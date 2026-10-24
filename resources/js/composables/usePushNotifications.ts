import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, type Ref } from 'vue';
import {
    subscription as subscriptionRoute,
    test as testRoute,
} from '@/routes/push';
import { urlBase64ToUint8Array } from '@/utils';

const SERVICE_WORKER_URL = '/service-worker.js';
const PUSH_META_CACHE = 'obsvr-push-meta';
const PUSH_META_KEY = '/__push-meta';
/** How long we wait for the test push to reach the service worker before calling it lost. */
const TEST_TIMEOUT_MS = 20_000;

export type PushHealth =
    | 'checking'
    | 'unsupported'
    | 'denied'
    | 'prompt'
    | 'broken'
    | 'healthy';

export type PushTestState = 'idle' | 'sending' | 'received' | 'missed';

export interface PushChecks {
    supported: boolean;
    permission: NotificationPermission | null;
    serviceWorkerActive: boolean;
    hasSubscription: boolean;
    keyMatches: boolean;
    knownByServer: boolean;
}

interface PushMeta {
    lastPushAt?: string;
    subscriptionChangedAt?: string;
    previousEndpoint?: string | null;
}

const isPushSupported = (): boolean =>
    typeof window !== 'undefined' &&
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    'Notification' in window;

const readPushMeta = async (): Promise<PushMeta> => {
    try {
        const cache = await caches.open(PUSH_META_CACHE);
        const response = await cache.match(PUSH_META_KEY);
        return response ? ((await response.json()) as PushMeta) : {};
    } catch {
        return {};
    }
};

const clearPreviousEndpoint = async (): Promise<void> => {
    try {
        const meta = await readPushMeta();
        const cache = await caches.open(PUSH_META_CACHE);
        await cache.put(
            PUSH_META_KEY,
            new Response(JSON.stringify({ ...meta, previousEndpoint: null })),
        );
    } catch {
        /* Metadata is best effort only. */
    }
};

const sameKey = (
    current: ArrayBuffer | null | undefined,
    expected: Uint8Array,
): boolean => {
    if (!current) return false;
    const bytes = new Uint8Array(current);
    return (
        bytes.length === expected.length &&
        bytes.every((byte, index) => byte === expected[index])
    );
};

/**
 * Tracks the health of the web push chain (permission → service worker → push
 * subscription → server) and repairs it when one of the links is missing.
 *
 * @param knownEndpoints Push endpoints the server has stored for the current user.
 */
export function usePushNotifications(knownEndpoints: Ref<string[]>) {
    const vapidPublicKey = import.meta.env.VITE_VAPID_PUBLIC_KEY as
        | string
        | undefined;

    const checks = ref<PushChecks>({
        supported: false,
        permission: null,
        serviceWorkerActive: false,
        hasSubscription: false,
        keyMatches: false,
        knownByServer: false,
    });
    const isWorking = ref(true);
    const lastError = ref<string | null>(null);
    const lastPushAt = ref<string | null>(null);
    const testState = ref<PushTestState>('idle');
    let testTimeout: ReturnType<typeof setTimeout> | undefined;

    const health = computed<PushHealth>(() => {
        const c = checks.value;
        if (isWorking.value) return 'checking';
        if (!c.supported || !vapidPublicKey) return 'unsupported';
        if (c.permission === 'denied') return 'denied';
        if (c.permission !== 'granted') return 'prompt';
        if (
            !c.serviceWorkerActive ||
            !c.hasSubscription ||
            !c.keyMatches ||
            !c.knownByServer ||
            testState.value === 'missed'
        )
            return 'broken';
        return 'healthy';
    });

    const inspect = async (): Promise<void> => {
        const supported = isPushSupported();
        const next: PushChecks = {
            supported,
            permission: supported ? Notification.permission : null,
            serviceWorkerActive: false,
            hasSubscription: false,
            keyMatches: false,
            knownByServer: false,
        };

        if (supported) {
            const registration =
                await navigator.serviceWorker.getRegistration(
                    SERVICE_WORKER_URL,
                );
            next.serviceWorkerActive = Boolean(registration?.active);

            const current = await registration?.pushManager.getSubscription();
            if (current) {
                next.hasSubscription = true;
                next.keyMatches = vapidPublicKey
                    ? sameKey(
                          current.options.applicationServerKey,
                          urlBase64ToUint8Array(vapidPublicKey),
                      )
                    : false;
                next.knownByServer = knownEndpoints.value.includes(
                    current.endpoint,
                );
            }
        }

        checks.value = next;
        lastPushAt.value = (await readPushMeta()).lastPushAt ?? null;
    };

    const saveSubscription = (
        pushSubscription: PushSubscription,
        previousEndpoint: string | null,
    ): Promise<void> =>
        new Promise((resolve, reject) => {
            router.post(
                subscriptionRoute.url(),
                { ...pushSubscription.toJSON(), previousEndpoint },
                {
                    preserveScroll: true,
                    preserveState: true,
                    async: true,
                    only: ['pushEndpoints'],
                    onSuccess: () => resolve(),
                    onError: (errors) =>
                        reject(new Error(Object.values(errors).join(', '))),
                },
            );
        });

    /**
     * Brings every link of the chain back. Only prompts for permission when
     * asked to, and only re-creates the subscription when it is missing, signed
     * with another VAPID key, or when `force` asks for a fresh endpoint.
     */
    const ensureHealthy = async ({
        prompt = false,
        force = false,
    }: { prompt?: boolean; force?: boolean } = {}): Promise<void> => {
        if (!isPushSupported() || !vapidPublicKey) {
            await inspect();
            isWorking.value = false;
            return;
        }

        isWorking.value = true;
        lastError.value = null;

        try {
            if (prompt && Notification.permission === 'default') {
                await Notification.requestPermission();
            }

            if (Notification.permission !== 'granted') return;

            await navigator.serviceWorker.register(SERVICE_WORKER_URL);
            const registration = await navigator.serviceWorker.ready;
            const applicationServerKey = urlBase64ToUint8Array(vapidPublicKey);

            let current = await registration.pushManager.getSubscription();
            let previousEndpoint =
                (await readPushMeta()).previousEndpoint ?? null;

            if (
                current &&
                (force ||
                    !sameKey(
                        current.options.applicationServerKey,
                        applicationServerKey,
                    ))
            ) {
                previousEndpoint = current.endpoint;
                await current.unsubscribe();
                current = null;
            }

            current ??= await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey,
            });

            if (
                previousEndpoint ||
                !knownEndpoints.value.includes(current.endpoint)
            ) {
                await saveSubscription(current, previousEndpoint);
                await clearPreviousEndpoint();
            }

            if (force) testState.value = 'idle';
        } catch (error) {
            lastError.value =
                error instanceof Error ? error.message : String(error);
            console.error('Push notifications repair failed', error);
        } finally {
            await inspect();
            isWorking.value = false;
        }
    };

    const sendTest = (): void => {
        clearTimeout(testTimeout);
        testState.value = 'sending';

        router.post(
            testRoute.url(),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                async: true,
                only: ['pushEndpoints'],
                onError: () => (testState.value = 'missed'),
            },
        );

        testTimeout = setTimeout(() => {
            if (testState.value === 'sending') testState.value = 'missed';
        }, TEST_TIMEOUT_MS);
    };

    const onServiceWorkerMessage = (event: MessageEvent): void => {
        if (event.data?.type === 'push-received') {
            lastPushAt.value = event.data.receivedAt;
            if (event.data.kind === 'test' && testState.value === 'sending') {
                clearTimeout(testTimeout);
                testState.value = 'received';
            }
        }

        if (event.data?.type === 'push-subscription-changed') {
            void ensureHealthy();
        }
    };

    onMounted(() => {
        if (isPushSupported()) {
            navigator.serviceWorker.addEventListener(
                'message',
                onServiceWorkerMessage,
            );
        }
        void ensureHealthy();
    });

    onBeforeUnmount(() => {
        clearTimeout(testTimeout);
        if (isPushSupported()) {
            navigator.serviceWorker.removeEventListener(
                'message',
                onServiceWorkerMessage,
            );
        }
    });

    return {
        checks,
        health,
        isWorking,
        lastError,
        lastPushAt,
        testState,
        ensureHealthy,
        sendTest,
    };
}
