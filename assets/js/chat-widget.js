/**
 * WP SmartChat — Frontend Chat Widget
 *
 * Pure vanilla JS, no dependencies.
 */
(function () {
    'use strict';

    // ── Config (injected by WordPress via wp_localize_script) ─────────────
    const CFG = window.wpscConfig || {};

    // ── DOM References ───────────────────────────────────────────────────
    const widget   = document.getElementById('wpsc-chat-widget');
    const toggle   = document.getElementById('wpsc-toggle');
    const closeBtn = document.getElementById('wpsc-close');
    const window_  = document.getElementById('wpsc-window');
    const messages = document.getElementById('wpsc-messages');
    const input    = document.getElementById('wpsc-input');
    const sendBtn  = document.getElementById('wpsc-send');

    if (!widget || !toggle || !messages || !input || !sendBtn) return;

    // ── State ────────────────────────────────────────────────────────────
    let isOpen  = false;
    let sending = false;
    let history = []; // { role: 'user'|'assistant', content: string }

    // ── Init ─────────────────────────────────────────────────────────────
    function init() {
        // Pulse the toggle button after a few seconds
        setTimeout(() => toggle.classList.add('wpsc-pulse'), 3000);

        // Event listeners
        toggle.addEventListener('click', toggleChat);
        closeBtn.addEventListener('click', closeChat);
        sendBtn.addEventListener('click', sendMessage);
        input.addEventListener('keydown', handleKey);
        input.addEventListener('input', autoResize);

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (isOpen && !widget.contains(e.target) && !e.target.closest('#wpsc-chat-widget') && !e.target.classList.contains('wpsc-quick-reply')) {
                closeChat();
            }
        });
    }

    // ── Toggle / Open / Close ────────────────────────────────────────────
    function toggleChat() {
        isOpen ? closeChat() : openChat();
    }

    function openChat() {
        isOpen = true;
        widget.classList.add('wpsc-open');
        window_.setAttribute('aria-hidden', 'false');
        toggle.classList.remove('wpsc-pulse');

        // Show welcome message + quick replies on first open
        if (messages.children.length === 0) {
            if (CFG.welcome) {
                addBotMessageClean(CFG.welcome);
            }
            if (CFG.quickReplies && CFG.quickReplies.length > 0) {
                showQuickReplies(CFG.quickReplies);
            }
        }

        // Focus input after animation
        setTimeout(() => input.focus(), 350);
    }

    function closeChat() {
        isOpen = false;
        widget.classList.remove('wpsc-open');
        window_.setAttribute('aria-hidden', 'true');
    }

    // ── Sending Messages ─────────────────────────────────────────────────
    function sendMessage() {
        const text = input.value.trim();
        if (!text || sending) return;

        // Add user message to UI
        addUserMessage(text);
        history.push({ role: 'user', content: text });

        // Remove quick replies once user engages
        removeQuickReplies();

        // Clear input
        input.value = '';
        input.style.height = 'auto';
        sendBtn.disabled = true;
        sending = true;

        // Show typing indicator
        const typing = addTypingIndicator();

        // Send to server
        const formData = new FormData();
        formData.append('action', 'wpsc_send_message');
        formData.append('nonce', CFG.nonce);
        formData.append('message', text);

        // Send last 10 history messages for context
        history.slice(-10).forEach((msg, i) => {
            formData.append(`history[${i}][role]`, msg.role);
            formData.append(`history[${i}][content]`, msg.content);
        });

        fetch(CFG.ajaxUrl, {
            method: 'POST',
            body: formData,
        })
            .then((res) => res.json())
            .then((data) => {
                removeTypingIndicator(typing);

                if (data.success && data.data) {
                    const answer  = data.data.answer || "Sorry, I couldn't process that.";
                    const sources = data.data.sources || [];

                    addBotMessage(answer, sources);
                    history.push({ role: 'assistant', content: answer });
                } else {
                    const errMsg = (data.data && data.data.message) || "Something went wrong. Please try again.";
                    addBotMessage(errMsg);
                }
            })
            .catch(() => {
                removeTypingIndicator(typing);
                addBotMessage("Oops! I'm having trouble connecting. Please try again in a moment.");
            })
            .finally(() => {
                sending = false;
                sendBtn.disabled = false;
                input.focus();
            });
    }

    // ── Keyboard Handling ────────────────────────────────────────────────
    function handleKey(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    }

    // ── Auto-resize Textarea ─────────────────────────────────────────────
    function autoResize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 100) + 'px';
    }

    // ── Add Messages to UI ───────────────────────────────────────────────
    function addUserMessage(text) {
        const el = document.createElement('div');
        el.className = 'wpsc-msg wpsc-msg-user';
        el.textContent = text;
        messages.appendChild(el);
        scrollToBottom();
    }

    function addBotMessage(text, sources) {
        removeReturnToStart();

        const el = document.createElement('div');
        el.className = 'wpsc-msg wpsc-msg-bot';
        el.innerHTML = renderMarkdown(text);

        // Add source links if provided
        if (sources && sources.length > 0) {
            const container = document.createElement('div');
            container.className = 'wpsc-sources';
            sources.forEach((s) => {
                const link = document.createElement('a');
                link.className = 'wpsc-source-link';
                link.href = s.url;
                link.target = '_blank';
                link.rel = 'noopener';
                link.textContent = '📄 ' + s.title;
                container.appendChild(link);
            });
            el.appendChild(container);
        }

        messages.appendChild(el);
        showReturnToStart();
        scrollToBottom();
    }

    // Bot message without the Return button (used for welcome message)
    function addBotMessageClean(text) {
        const el = document.createElement('div');
        el.className = 'wpsc-msg wpsc-msg-bot';
        el.innerHTML = renderMarkdown(text);
        messages.appendChild(el);
        scrollToBottom();
    }

    // ── Return to Start ──────────────────────────────────────────────────
    function showReturnToStart() {
        removeReturnToStart();

        const wrapper = document.createElement('div');
        wrapper.className = 'wpsc-return-start';

        const btn = document.createElement('button');
        btn.className = 'wpsc-return-btn';
        btn.textContent = '↩ Return to Start';
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            resetChat();
        });

        wrapper.appendChild(btn);
        messages.appendChild(wrapper);
    }

    function removeReturnToStart() {
        messages.querySelectorAll('.wpsc-return-start').forEach((el) => el.remove());
    }

    function resetChat() {
        messages.innerHTML = '';
        history = [];

        // Show welcome + quick replies again
        if (CFG.welcome) {
            addBotMessageClean(CFG.welcome);
        }
        if (CFG.quickReplies && CFG.quickReplies.length > 0) {
            showQuickReplies(CFG.quickReplies);
        }
    }

    // ── Typing Indicator ─────────────────────────────────────────────────
    function addTypingIndicator() {
        const el = document.createElement('div');
        el.className = 'wpsc-typing';
        el.innerHTML = '<div class="wpsc-typing-dot"></div><div class="wpsc-typing-dot"></div><div class="wpsc-typing-dot"></div>';
        messages.appendChild(el);
        scrollToBottom();
        return el;
    }

    function removeTypingIndicator(el) {
        if (el && el.parentNode) {
            el.parentNode.removeChild(el);
        }
    }

    // ── Quick Replies ──────────────────────────────────────────────────────
    function showQuickReplies(replies) {
        // Remove any existing quick replies first
        removeQuickReplies();

        const container = document.createElement('div');
        container.className = 'wpsc-quick-replies';

        replies.forEach((text) => {
            const btn = document.createElement('button');
            btn.className = 'wpsc-quick-reply';
            btn.textContent = text;
            btn.addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent "outside click" from closing the window
                removeQuickReplies();
                input.value = text;
                sendMessage();
            });
            container.appendChild(btn);
        });

        messages.appendChild(container);
        scrollToBottom();
    }

    function removeQuickReplies() {
        const existing = messages.querySelectorAll('.wpsc-quick-replies');
        existing.forEach((el) => el.remove());
    }

    // ── Scroll ───────────────────────────────────────────────────────────
    function scrollToBottom() {
        requestAnimationFrame(() => {
            messages.scrollTop = messages.scrollHeight;
        });
    }

    // ── Simple Markdown Renderer ─────────────────────────────────────────
    // Handles: **bold**, [links](url), \n line breaks
    function renderMarkdown(text) {
        let html = escapeHtml(text);

        // Bold: **text**
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

        // Links: [text](url)
        html = html.replace(
            /\[([^\]]+)\]\(([^)]+)\)/g,
            '<a href="$2" target="_blank" rel="noopener">$1</a>'
        );

        // Line breaks
        html = html.replace(/\n/g, '<br>');

        return html;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ── Boot ─────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
