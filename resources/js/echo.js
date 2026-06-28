import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Driver do broadcasting no front. 'pusher' (cloud) é o padrão.
// Para usar o WebSocket local do Laravel, defina VITE_BROADCAST_DRIVER=reverb.
const driver = import.meta.env.VITE_BROADCAST_DRIVER ?? 'pusher';

const auth = {
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
    },
};

const config =
    driver === 'reverb'
        ? {
              broadcaster: 'reverb',
              key: import.meta.env.VITE_REVERB_APP_KEY,
              wsHost: import.meta.env.VITE_REVERB_HOST,
              wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
              wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
              forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
              enabledTransports: ['ws', 'wss'],
              auth,
          }
        : {
              broadcaster: 'pusher',
              key: import.meta.env.VITE_PUSHER_APP_KEY,
              cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
              forceTLS: true,
              auth,
          };

window.Echo = new Echo(config);
