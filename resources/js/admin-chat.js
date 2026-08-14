/**
 * Uses the existing global window.Echo (resources/js/echo.js, imported via app.js)
 * — admin pages are authenticated, so the default /broadcasting/auth route (which
 * resolves Auth::user()) already works correctly here, unlike the public widget
 * which needs its own guest-token Echo instance (see chat-widget.js).
 */
document.addEventListener('DOMContentLoaded', () => {
    const inboxBanner = document.getElementById('chat-inbox-update-banner');
    if (inboxBanner && window.Echo) {
        window.Echo.private('chat-inbox').listen('.ticket.updated', () => {
            inboxBanner.classList.remove('hidden');
        });
    }

    const threadEl = document.getElementById('chat-thread');
    if (threadEl && window.Echo) {
        const ticketId = threadEl.dataset.ticketId;
        const channel = window.Echo.private(`chat.${ticketId}`);
        const typingIndicator = document.getElementById('chat-typing-indicator');
        let typingClearTimer = null;

        channel.listen('.message.sent', (event) => {
            const bubble = document.createElement('div');
            const isOfficer = event.sender_type === 'officer';
            const isAi = event.sender_type === 'ai';
            const isOrgSide = isOfficer || isAi;
            const sideClass = isOfficer ? 'ml-auto bg-sky-600 text-white'
                : isAi ? 'ml-auto bg-emerald-600 text-white'
                : 'mr-auto bg-white text-gray-700 border border-gray-200';
            bubble.className = sideClass + ' max-w-[75%] rounded-lg px-3 py-2 text-sm mb-2';

            const nameEl = document.createElement('p');
            nameEl.className = 'mb-0.5 text-xs font-semibold ' + (isOfficer ? 'text-sky-100' : isAi ? 'text-emerald-100' : 'text-sky-600');
            nameEl.textContent = event.sender_name;
            bubble.appendChild(nameEl);

            const bodyEl = document.createElement('p');
            bodyEl.className = 'whitespace-pre-line';
            bodyEl.textContent = event.body;
            bubble.appendChild(bodyEl);

            const timeEl = document.createElement('p');
            timeEl.className = 'mt-1 text-right text-[10px] opacity-60';
            timeEl.textContent = event.created_at
                ? new Date(event.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
                : '';
            bubble.appendChild(timeEl);

            threadEl.appendChild(bubble);
            threadEl.scrollTop = threadEl.scrollHeight;
            typingIndicator?.classList.add('hidden');
        });

        // No explicit "stopped typing" signal — auto-clear after a short silence
        // instead (same pattern as chat-widget.js), simpler and survives a dropped event.
        channel.listenForWhisper('typing', () => {
            typingIndicator?.classList.remove('hidden');
            clearTimeout(typingClearTimer);
            typingClearTimer = setTimeout(() => typingIndicator?.classList.add('hidden'), 3000);
        });

        const composer = document.getElementById('chat-composer-input');
        let lastTypingWhisperAt = 0;
        composer?.addEventListener('input', () => {
            const now = Date.now();
            if (now - lastTypingWhisperAt < 2000) {
                return;
            }
            lastTypingWhisperAt = now;
            channel.whisper('typing', {});
        });
    }
});
