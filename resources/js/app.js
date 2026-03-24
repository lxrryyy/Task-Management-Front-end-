import './bootstrap';

import Alpine from 'alpinejs';
import { registerNotifications } from './notifications';

window.Alpine = Alpine;

registerNotifications();
Alpine.start();
