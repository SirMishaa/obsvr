const PUSH_META_CACHE = 'obsvr-push-meta';
const PUSH_META_KEY = '/__push-meta';

/**
 * Stores push metadata in the Cache API so open pages can read it directly.
 */
const writePushMeta = async (patch) => {
    const cache = await caches.open(PUSH_META_CACHE);
    const previous = await cache.match(PUSH_META_KEY);
    const meta = { ...(previous ? await previous.json() : {}), ...patch };
    await cache.put(PUSH_META_KEY, new Response(JSON.stringify(meta)));
};

const notifyClients = async (message) => {
    const windows = await self.clients.matchAll({
        type: 'window',
        includeUncontrolled: true,
    });
    windows.forEach((client) => client.postMessage(message));
};

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch {}

    const title = data.title || 'Notification';
    const options = {
        body: data.body || '',
        icon: data.icon || '/favicon.ico',
        data: data.data || {},
        actions: data.actions || [],
    };
    const receivedAt = new Date().toISOString();
    const kind = (data.data && data.data.type) || 'notification';

    event.waitUntil(
        Promise.all([
            self.registration.showNotification(title, options),
            writePushMeta({ lastPushAt: receivedAt }),
            notifyClients({ type: 'push-received', kind, receivedAt }),
        ]),
    );
});

/**
 * The push service rotated or expired the subscription: subscribe again with the
 * same options and let open pages send the new endpoint to the server.
 */
self.addEventListener('pushsubscriptionchange', (event) => {
    const options = event.oldSubscription && event.oldSubscription.options;

    event.waitUntil(
        (options
            ? self.registration.pushManager.subscribe(options)
            : Promise.resolve(null)
        ).then((subscription) =>
            Promise.all([
                writePushMeta({
                    subscriptionChangedAt: new Date().toISOString(),
                    previousEndpoint: event.oldSubscription
                        ? event.oldSubscription.endpoint
                        : null,
                }),
                notifyClients({
                    type: 'push-subscription-changed',
                    endpoint: subscription ? subscription.endpoint : null,
                }),
            ]),
        ),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/';
    event.waitUntil(clients.openWindow(url));
});
