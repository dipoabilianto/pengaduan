import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { broadcasterOptions } from './echo-config';
import { formatMessageBody } from './chat-format';

/**
 * Deliberately its OWN Echo instance, separate from the global window.Echo that
 * resources/js/echo.js sets up (used by admin pages, authEndpoint defaults to
 * /broadcasting/auth which assumes Auth::user()). Citizens aren't logged-in
 * Users, so the widget authenticates its private channel through the guest
 * token endpoint instead (see ChatBroadcastAuthController).
 */
function makeGuestEcho() {
    return new Echo({
        ...broadcasterOptions(),
        authEndpoint: '/chat/broadcasting/auth',
        Pusher,
    });
}

// How long the widget waits for the CITIZEN'S OWN next message before resetting back to
// the phone-entry screen — AI/officer replies arriving don't reset this clock, only the
// citizen's own activity does. Purely a frontend UX/privacy affordance (e.g. a shared
// public computer); the ticket and its history in the backend are untouched, so typing
// the same phone number back in (and solving a fresh captcha) resumes the same
// conversation, as long as it's still active — see ChatTicket::findOrStartFor().
const IDLE_RESET_MS = 5 * 60 * 1000;

const CTA_ACTIONS = {
    report_form: { label: 'Buat Pengaduan Resmi', href: '/pengaduan/buat' },
    check_status: { label: 'Cek Status Laporan', href: '/cek-status' },
};

/**
 * The widget script loads async — grecaptcha.render() isn't available the instant
 * this module runs, so poll briefly instead of relying on a global onload callback
 * (simpler to reason about alongside this widget's own Alpine lifecycle).
 */
function whenRecaptchaReady(callback) {
    if (window.grecaptcha && window.grecaptcha.render) {
        callback();
        return;
    }
    setTimeout(() => whenRecaptchaReady(callback), 100);
}

/**
 * Exposed as a plain global (not Alpine.data() via the alpine:init event) —
 * chat-widget.js is a separate Vite entry from app.js, which already calls
 * Alpine.start() at its own top-level (module scripts execute in document
 * order, so app.js's alpine:init fires before this file's listener could ever
 * register). x-data="chatWidget()" resolves this as a plain global function
 * call instead, sidestepping that race entirely.
 */
window.chatWidget = function chatWidget() {
    return {
        open: false,
        step: 'phone',
        phone: '',
        recaptchaWidgetId: null,
        pendingTicketNo: null,
        ticketId: null,
        ticketStatus: null,
        messages: [],
        draft: '',
        sending: false,
        starting: false,
        unread: 0,
        error: null,
        historyHidden: false,
        revealing: false,
        echo: null,
        officerTyping: false,
        typingClearTimer: null,
        lastTypingWhisperAt: 0,
        lastCitizenActivityAt: null,
        idleWatchTimer: null,
        awaitingRating: false,
        ratingSubmitted: false,
        selectedRatingScale: null,
        ratingComment: '',
        ratingSaving: false,
        savedScrollY: 0,

        init() {
            // One-time cleanup — a pre-session-based build of this widget cached the raw
            // phone number here and silently replayed it to auto-resume. Nothing reads
            // these keys anymore; leaving them behind would just be dead plaintext data
            // sitting in the browser.
            localStorage.removeItem('sidumas_chat_phone');
            localStorage.removeItem('sidumas_chat_ticket_id');

            // $watch (not just handling it inside toggle()) so this fires no matter how
            // `open` changes — including the header's close button, which flips it
            // directly via @click="open = false" rather than going through a method.
            this.$watch('open', (isOpen) => {
                if (isOpen) {
                    this.lockBodyScroll();
                } else {
                    this.unlockBodyScroll();
                }
            });

            this.renderRecaptcha();
            this.resumeFromSession();

            window.addEventListener('chat-widget:open', (event) => {
                this.open = true;
                this.unread = 0;
                if (event.detail?.phone) {
                    this.phone = event.detail.phone;
                }
                if (event.detail?.ticketNo) {
                    this.pendingTicketNo = event.detail.ticketNo;
                }
            });
        },

        toggle() {
            this.open = ! this.open;
            if (this.open) {
                this.unread = 0;
                // Messages may have arrived (and been appended to `messages`) while the
                // widget was closed — the thread wasn't visible then to scroll, so catch
                // up now that it's opening.
                this.$nextTick(() => this.scrollToBottom());
            }
        },

        /**
         * On mobile the widget takes over the full screen (see chat-widget.blade.php's
         * fixed inset-0), but the page underneath is still a normal scrollable <body> —
         * a touch-scroll gesture starting inside the widget's own thread can "chain"
         * into the page behind it once the thread hits its own scroll boundary,
         * dragging the whole page along ("chat box dibuka di pertengahan halaman, saat
         * scroll... halaman ikut ter-scroll"). Desktop is untouched — there the widget
         * is a small floating box, not a takeover, so the page should stay scrollable.
         * `overflow: hidden` on <body> alone is known to be unreliable for this on iOS
         * Safari/WebKit — position: fixed + restoring scrollY on unlock is the
         * established cross-browser-safe technique.
         */
        lockBodyScroll() {
            if (window.matchMedia('(min-width: 640px)').matches) {
                return;
            }
            this.savedScrollY = window.scrollY;
            document.body.style.position = 'fixed';
            document.body.style.top = `-${this.savedScrollY}px`;
            document.body.style.width = '100%';
        },

        unlockBodyScroll() {
            if (document.body.style.position !== 'fixed') {
                return;
            }
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.width = '';
            window.scrollTo(0, this.savedScrollY || 0);
        },

        /**
         * Resumes an already-open conversation purely from the HttpOnly session
         * cookie (see ChatController::session()) — no phone number involved, so a
         * returning visitor within the same session lifetime never sees the phone/
         * captcha screen at all.
         */
        resumeFromSession() {
            this.starting = true;
            window.axios.get('/chat/sesi')
                .then((res) => {
                    if (res.data.active) {
                        this.enterTicket(res.data);
                    }
                })
                .catch(() => {})
                .finally(() => { this.starting = false; });
        },

        renderRecaptcha() {
            whenRecaptchaReady(() => {
                if (! this.$refs.recaptcha || this.recaptchaWidgetId !== null) {
                    return;
                }
                this.recaptchaWidgetId = window.grecaptcha.render(this.$refs.recaptcha, {
                    sitekey: this.$refs.recaptcha.dataset.sitekey,
                    // "compact" isn't just smaller, it's a different (squarer) layout
                    // that looked off — kept "normal" (default) and shrunk it with a
                    // CSS scale on the ref'd container instead (see chat-widget.blade.php).
                });
            });
        },

        startChat() {
            if (! this.phone || this.phone.trim().length < 9) {
                this.error = 'Masukkan nomor HP yang valid.';
                return;
            }

            const recaptchaResponse = this.recaptchaWidgetId !== null
                ? window.grecaptcha.getResponse(this.recaptchaWidgetId)
                : '';

            if (! recaptchaResponse) {
                this.error = 'Selesaikan verifikasi captcha terlebih dahulu.';
                return;
            }

            this.error = null;
            this.starting = true;

            window.axios.post('/chat/mulai', {
                phone: this.phone,
                related_ticket_no: this.pendingTicketNo,
                'g-recaptcha-response': recaptchaResponse,
            })
                .then((res) => this.enterTicket(res.data))
                .catch((err) => {
                    this.error = err.response?.data?.errors?.['g-recaptcha-response']?.[0]
                        ?? err.response?.data?.errors?.phone?.[0]
                        ?? 'Gagal memulai chat. Coba lagi.';
                    if (this.recaptchaWidgetId !== null) {
                        window.grecaptcha.reset(this.recaptchaWidgetId);
                    }
                })
                .finally(() => { this.starting = false; });
        },

        enterTicket(data) {
            this.ticketId = data.ticket_id;
            this.ticketStatus = data.status;
            this.messages = data.messages;
            this.historyHidden = !! data.history_hidden;
            this.awaitingRating = !! data.awaiting_rating;
            this.ratingSubmitted = false;
            this.selectedRatingScale = null;
            this.ratingComment = '';
            this.step = 'chat';
            this.subscribe();
            this.lastCitizenActivityAt = Date.now();
            this.startIdleWatch();
            // Resuming an existing thread can load a long history — without this the
            // citizen lands scrolled to the TOP (oldest messages), with the latest
            // reply hidden below the fold until they scroll manually.
            this.$nextTick(() => this.scrollToBottom());
        },

        /**
         * "Lihat Riwayat Chat Sebelumnya" — only offered by the server (history_hidden)
         * for a ticket closed 6-12h ago; past that the server stops returning history at
         * all regardless of this flag (see ChatController::session()). Goes through the
         * session endpoint (not /chat/mulai) — by the time this button is visible the
         * session already proves ownership of the open ticket, no phone number needed.
         */
        revealHistory() {
            if (this.revealing) {
                return;
            }

            this.revealing = true;
            window.axios.get('/chat/sesi', { params: { reveal_history: true } })
                .then((res) => {
                    if (res.data.active) {
                        this.enterTicket(res.data);
                    }
                })
                .catch(() => {
                    this.error = 'Gagal memuat riwayat. Coba lagi.';
                })
                .finally(() => { this.revealing = false; });
        },

        subscribe() {
            if (this.echo) {
                return;
            }

            this.echo = makeGuestEcho();
            this.echo.private(`chat.${this.ticketId}`)
                .listen('.message.sent', (event) => {
                    // The citizen's own message is shown optimistically the instant they
                    // hit "Kirim" (see send()) rather than waiting for this broadcast to
                    // round-trip back — reconcile with the real, server-confirmed copy
                    // (real id/created_at) instead of appending a second bubble for it.
                    const pendingIndex = event.sender_type === 'citizen'
                        ? this.messages.findIndex((m) => m.pending)
                        : -1;
                    if (pendingIndex !== -1) {
                        this.messages.splice(pendingIndex, 1, event);
                    } else {
                        this.messages.push(event);
                    }
                    if (! this.open) {
                        this.unread++;
                    }
                    this.officerTyping = false;
                    this.$nextTick(() => this.scrollToBottom());
                    // Belt-and-braces re-scroll shortly after — covers any layout shift
                    // that settles just after the immediate DOM update (e.g. the typing
                    // indicator's height disappearing right as the real message renders).
                    setTimeout(() => this.scrollToBottom(), 150);
                })
                .listenForWhisper('typing', () => this.showTypingIndicator(3000))
                // Server-originated equivalent for when AI is processing (a queued job
                // has no live socket to whisper from) — deliberately reuses the exact
                // same "Petugas sedang mengetik…" indicator rather than a separate one,
                // since the citizen doesn't need to know whether AI or a human answers.
                // Broadcast twice per reply: once immediately when the message is sent
                // (ChatController::sendMessage, instant feedback) and again right before
                // AnswerChatMessageWithAiJob actually calls the AI — which, on shared
                // hosting's cron-polled queue, can run tens of seconds later. A short
                // window (previously 8s) let the first indicator time out and disappear
                // in that gap, so the second broadcast made it visibly flash back on —
                // looked like it fired twice. Long enough to bridge one full cron cycle
                // (queue:work's --max-time=55) so the indicator stays continuously up
                // between the two broadcasts instead of flickering; the real reply
                // arriving clears it immediately regardless (.listen('.message.sent')).
                .listen('.assistant.typing', () => this.showTypingIndicator(60000))
                // Fires the moment any of the three closers (citizen confirming done, 6h
                // auto-close, officer's "Tandai Selesai") marks the ticket selesai — lets
                // the rating card appear live without the citizen needing to reload.
                .listen('.ticket.closed', () => {
                    this.ticketStatus = 'selesai';
                    if (! this.ratingSubmitted) {
                        this.awaitingRating = true;
                    }
                });
        },

        showTypingIndicator(durationMs) {
            this.officerTyping = true;
            clearTimeout(this.typingClearTimer);
            this.typingClearTimer = setTimeout(() => { this.officerTyping = false; }, durationMs);
        },

        /**
         * Throttled to at most once every 2s while actively typing — whisper
         * events don't need to fire on every keystroke, just often enough that
         * the other side's 3s auto-clear timeout never lapses mid-sentence.
         */
        notifyTyping() {
            const now = Date.now();
            if (now - this.lastTypingWhisperAt < 2000 || ! this.echo) {
                return;
            }
            this.lastTypingWhisperAt = now;
            this.echo.private(`chat.${this.ticketId}`).whisper('typing', {});
        },

        send() {
            if (! this.draft.trim() || this.sending) {
                return;
            }

            this.sending = true;
            const body = this.draft;
            this.draft = '';

            // Shown immediately rather than waiting on the HTTP request + broadcast
            // round-trip — that gap (network + server processing + WebSocket delivery)
            // was reading as "the button doesn't work" even though the send itself is
            // fine. Reconciled with the real message once its broadcast arrives (see
            // subscribe()'s .listen('.message.sent', ...)), or removed below on failure.
            const tempId = `pending-${Date.now()}`;
            this.messages.push({
                id: tempId,
                sender_type: 'citizen',
                sender_name: 'Anda',
                body,
                cta_action: null,
                created_at: new Date().toISOString(),
                pending: true,
            });
            this.$nextTick(() => this.scrollToBottom());

            window.axios.post(`/chat/${this.ticketId}/pesan`, { message: body })
                .then(() => { this.lastCitizenActivityAt = Date.now(); })
                .catch((err) => {
                    // 422 here means the ticket got closed between the widget loading and
                    // this send (e.g. an officer closed it, or the reveal-history window
                    // lapsed) — ticketStatus wasn't updated yet to have hidden the form.
                    this.error = err.response?.data?.errors?.message?.[0] ?? 'Pesan gagal terkirim. Coba lagi.';
                    this.messages = this.messages.filter((m) => m.id !== tempId);
                    this.draft = body;
                    if (err.response?.status === 422 && err.response?.data?.errors?.message) {
                        this.ticketStatus = 'selesai';
                    }
                })
                .finally(() => { this.sending = false; });
        },

        /**
         * Only the CITIZEN'S OWN messages reset this clock (see IDLE_RESET_MS) — an
         * incoming AI/officer reply must not keep the session alive on its own.
         */
        startIdleWatch() {
            clearInterval(this.idleWatchTimer);
            this.idleWatchTimer = setInterval(() => {
                if (this.step === 'chat' && Date.now() - this.lastCitizenActivityAt >= IDLE_RESET_MS) {
                    this.resetToPhoneEntry();
                }
            }, 30000);
        },

        /**
         * The ticket and its history in the backend are untouched (see
         * ProcessInactiveChatTicketsCommand for the separate, much longer backend
         * inactivity policy) — but the server-side session IS revoked here (POST
         * /chat/keluar), not just client-side state cleared. Without that, reopening
         * the widget on the same (possibly shared/public) computer would silently
         * auto-resume the previous citizen's chat via the session cookie alone.
         * Typing the same phone number back in (with a fresh captcha) resumes the
         * same conversation from scratch.
         */
        resetToPhoneEntry() {
            clearInterval(this.idleWatchTimer);
            if (this.echo) {
                this.echo.leave(`chat.${this.ticketId}`);
                this.echo = null;
            }
            this.messages = [];
            this.ticketId = null;
            this.ticketStatus = null;
            this.historyHidden = false;
            this.awaitingRating = false;
            this.ratingSubmitted = false;
            this.selectedRatingScale = null;
            this.ratingComment = '';
            this.phone = '';
            this.step = 'phone';
            if (this.recaptchaWidgetId !== null) {
                window.grecaptcha.reset(this.recaptchaWidgetId);
            }
            window.axios.post('/chat/keluar').catch(() => {});
        },

        ctaFor(action) {
            return CTA_ACTIONS[action] ?? null;
        },

        /**
         * Local wall-clock time (WIB or whatever the citizen's device is set to) per
         * message, so delays between sending and an AI/officer reply are visible at a
         * glance instead of only inferred from the "typing…" indicator's duration.
         */
        formatTime(isoString) {
            if (! isoString) return '';

            return new Date(isoString).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        },

        formatBody(text) {
            return formatMessageBody(text);
        },

        sendRating() {
            if (! this.selectedRatingScale || this.ratingSaving) {
                return;
            }

            this.ratingSaving = true;
            window.axios.post(`/chat/${this.ticketId}/nilai`, {
                scale: this.selectedRatingScale,
                comment: this.ratingComment || null,
            })
                .then(() => {
                    this.awaitingRating = false;
                    this.ratingSubmitted = true;
                })
                .catch(() => {
                    this.error = 'Gagal mengirim penilaian. Coba lagi.';
                })
                .finally(() => { this.ratingSaving = false; });
        },

        dismissRating() {
            this.awaitingRating = false;
        },

        /**
         * Plain DOM lookup by id rather than Alpine's $refs — $refs proved unreliable
         * here (intermittently returning undefined for this element across calls from
         * the Echo listener closure, root cause not conclusively pinned down), so this
         * sidesteps it entirely with a mechanism that can't have the same failure mode.
         */
        scrollToBottom() {
            const el = document.getElementById('chat-widget-thread');
            if (el) {
                el.scrollTop = el.scrollHeight;
            }
        },
    };
};
