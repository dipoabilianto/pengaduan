import './bootstrap';
import Alpine from 'alpinejs';
import AOS from 'aos';
import 'aos/dist/aos.css';

window.Alpine = Alpine;

// Deferred to DOMContentLoaded (not called synchronously here) — app.js is one of
// several independent type="module" entries on a page (e.g. chat-widget.js also
// loads on public pages), and while they execute in document order, Alpine.start()
// does its DOM walk synchronously the instant it's called. Calling it here would run
// before sibling scripts finish registering their x-data globals (e.g. window.chatWidget),
// causing "chatWidget is not defined" on any element Alpine reaches first. All deferred
// module scripts have finished running by DOMContentLoaded, so starting Alpine there
// guarantees every sibling entry has already registered what it needs to.
document.addEventListener('DOMContentLoaded', () => {
    Alpine.start();
});

AOS.init({ duration: 600, once: true });
