import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { broadcasterOptions } from './echo-config';

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
        captcha: '',
        captchaCode: '',
        pendingTicketNo: null,
        ticketId: null,
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

        init() {
            // One-time cleanup — a pre-session-based build of this widget cached the raw
            // phone number here and silently replayed it to auto-resume. Nothing reads
            // these keys anymore; leaving them behind would just be dead plaintext data
            // sitting in the browser.
            localStorage.removeItem('sidumas_chat_phone');
            localStorage.removeItem('sidumas_chat_ticket_id');

            this.refreshCaptcha();
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

        refreshCaptcha() {
            window.axios.get('/captcha')
                .then((res) => { this.captchaCode = res.data.code; });
        },

        startChat() {
            if (! this.phone || this.phone.trim().length < 9) {
                this.error = 'Masukkan nomor HP yang valid.';
                return;
            }

            this.error = null;
            this.starting = true;

            window.axios.post('/chat/mulai', { phone: this.phone, related_ticket_no: this.pendingTicketNo, captcha: this.captcha })
                .then((res) => this.enterTicket(res.data))
                .catch((err) => {
                    this.error = err.response?.data?.errors?.captcha?.[0]
                        ?? err.response?.data?.errors?.phone?.[0]
                        ?? 'Gagal memulai chat. Coba lagi.';
                    this.captcha = '';
                    this.refreshCaptcha();
                })
                .finally(() => { this.starting = false; });
        },

        enterTicket(data) {
            this.ticketId = data.ticket_id;
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
                    this.messages.push(event);
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
                // Longer window: covers the AI call itself PLUS the simulated human-typing
                // delay before a found answer is actually posted (up to ~6s, see
                // AnswerChatMessageWithAiJob::typingDelayFor) — re-broadcast right as that
                // delay begins, so this window just needs to outlast it with margin.
                .listen('.assistant.typing', () => this.showTypingIndicator(8000))
                // Fires the moment any of the three closers (citizen confirming done, 6h
                // auto-close, officer's "Tandai Selesai") marks the ticket selesai — lets
                // the rating card appear live without the citizen needing to reload.
                .listen('.ticket.closed', () => {
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

            window.axios.post(`/chat/${this.ticketId}/pesan`, { message: body })
                .then(() => { this.lastCitizenActivityAt = Date.now(); })
                .catch(() => {
                    this.error = 'Pesan gagal terkirim. Coba lagi.';
                    this.draft = body;
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
            this.historyHidden = false;
            this.awaitingRating = false;
            this.ratingSubmitted = false;
            this.selectedRatingScale = null;
            this.ratingComment = '';
            this.phone = '';
            this.step = 'phone';
            this.refreshCaptcha();
            window.axios.post('/chat/keluar').catch(() => {});
        },

        ctaFor(action) {
            return CTA_ACTIONS[action] ?? null;
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
