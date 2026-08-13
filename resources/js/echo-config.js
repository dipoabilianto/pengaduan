/**
 * Shared between echo.js (admin/global) and chat-widget.js (citizen guest instance) so
 * the two never drift apart. Reverb (self-hosted, custom host/port) is what local dev
 * runs — production hosts that can't keep a persistent WebSocket process alive (shared/
 * cloud hosting with no Supervisor, e.g. Rumahweb) switch to real Pusher cloud instead,
 * since Reverb speaks the same wire protocol Echo's "pusher" broadcaster already knows.
 * VITE_BROADCAST_DRIVER picks which; unset defaults to "reverb" so existing local .env
 * files keep working untouched.
 */
export function broadcasterOptions() {
    if (import.meta.env.VITE_BROADCAST_DRIVER === 'pusher') {
        return {
            broadcaster: 'pusher',
            key: import.meta.env.VITE_PUSHER_APP_KEY,
            cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
            forceTLS: true,
        };
    }

    return {
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    };
}
