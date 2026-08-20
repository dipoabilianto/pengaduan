/**
 * Shared by chat-widget.js (public) and admin-chat.js — a minimal **bold** markdown
 * convention (used by the chat AI to bold its own name — see
 * BuildsChatAnswerPrompt::chatAnswerSystemPrompt()) converted to <strong>. Escape
 * FIRST, then apply the markdown — otherwise a citizen typing raw HTML in their own
 * message would render unescaped. Mirrors ChatMessage::formattedBody() (PHP side,
 * used for the admin thread's initial server-rendered messages).
 */
export function formatMessageBody(text) {
    const escaped = String(text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    return escaped.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
}
