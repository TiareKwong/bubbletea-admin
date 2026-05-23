@props(['branchId' => null])
<div
    x-data="{
        supported: ('Notification' in window) && ('serviceWorker' in navigator) && ('PushManager' in window),
        permission: ('Notification' in window) ? Notification.permission : 'unsupported',
        loading: false,

        async saveSubscription(reg) {
            let sub = await reg.pushManager.getSubscription();
            if (!sub) {
                sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.urlBase64ToUint8Array('{{ config('services.vapid.public_key') }}'),
                });
            }
            const key  = sub.getKey('p256dh');
            const auth = sub.getKey('auth');
            const res = await fetch('/staff/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                },
                body: JSON.stringify({
                    endpoint:  sub.endpoint,
                    p256dh:    btoa(String.fromCharCode(...new Uint8Array(key))),
                    auth:      btoa(String.fromCharCode(...new Uint8Array(auth))),
                    branch_id: {{ $branchId ?? 'null' }},
                }),
            });
            console.log('Subscribe response:', res.status, res.url);
            if (!res.ok) {
                const text = await res.text();
                console.warn('Subscribe failed:', text.substring(0, 200));
            }
        },

        async enable() {
            if (!this.supported) return;
            this.loading = true;
            try {
                const reg = await navigator.serviceWorker.register('/sw.js');
                await navigator.serviceWorker.ready;

                const result = await Notification.requestPermission();
                this.permission = result;
                if (result !== 'granted') { this.loading = false; return; }

                await this.saveSubscription(reg);
            } catch(e) {
                console.warn('Push setup failed:', e);
            }
            this.loading = false;
        },

        urlBase64ToUint8Array(base64) {
            const pad = '='.repeat((4 - base64.length % 4) % 4);
            const b64 = (base64 + pad).replace(/-/g, '+').replace(/_/g, '/');
            return Uint8Array.from(atob(b64), c => c.charCodeAt(0));
        }
    }"
    x-init="
        if (supported && permission === 'granted') {
            navigator.serviceWorker.register('/sw.js').then(reg =>
                navigator.serviceWorker.ready.then(() => saveSubscription(reg))
            ).catch(e => console.warn('SW re-register failed:', e));
        }
    "
    style="display:inline-flex;align-items:center;"
>
    <template x-if="supported && permission !== 'granted'">
        <button
            x-on:click.prevent="enable()"
            :disabled="loading"
            type="button"
            style="
                display:inline-flex;align-items:center;gap:6px;
                background:#7c3aed;color:#fff;border:none;
                padding:6px 12px;border-radius:8px;
                font-size:13px;font-weight:600;cursor:pointer;
                -webkit-user-select:none;user-select:none;
                touch-action:manipulation;
            "
        >
            <span>🔔</span>
            <span x-text="loading ? 'Setting up...' : 'Enable Order Alerts'"></span>
        </button>
    </template>

    <template x-if="supported && permission === 'granted'">
        <span style="font-size:12px;color:#6b7280;display:inline-flex;align-items:center;gap:4px;">
            🔔 Alerts on
        </span>
    </template>
</div>
