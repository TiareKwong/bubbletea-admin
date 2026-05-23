self.addEventListener('push', function (event) {
    let data = {};
    try { data = event.data.json(); } catch (e) {}

    const title   = data.title || '🧋 New Order!';
    const options = {
        body: data.body || 'A new order is waiting.',
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        tag: 'new-order',
        renotify: true,
        requireInteraction: true,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (const client of clientList) {
                if (client.url.includes('/admin') && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow('/admin');
            }
        })
    );
});
