import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
import { broadcasterOptions } from './echo-config';
window.Pusher = Pusher;

window.Echo = new Echo(broadcasterOptions());
