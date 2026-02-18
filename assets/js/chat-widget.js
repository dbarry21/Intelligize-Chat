/**
 * Intelligize Chat — Frontend Widget v2.0
 *
 * Features: delay, auto-open, lead capture, contact buttons,
 * session tracking, return to start.
 */
(function () {
    'use strict';

    const CFG = window.wpscConfig || {};

    // ── DOM ───────────────────────────────────────────────────────────────
    const widget   = document.getElementById('wpsc-chat-widget');
    const toggle   = document.getElementById('wpsc-toggle');
    const closeBtn = document.getElementById('wpsc-close');
    const chatWin  = document.getElementById('wpsc-window');
    const messages = document.getElementById('wpsc-messages');
    const input    = document.getElementById('wpsc-input');
    const sendBtn  = document.getElementById('wpsc-send');

    if (!widget || !toggle || !messages || !input || !sendBtn) return;

    // ── State ─────────────────────────────────────────────────────────────
    let isOpen        = false;
    let sending       = false;
    let history       = [];
    let sessionId     = generateSessionId();
    let leadCaptured  = false;
    let leadData      = { name: '', email: '', phone: '' };
    let hasAutoOpened = false;

    // ── Mobile check ──────────────────────────────────────────────────────
    function isMobile() {
        return window.innerWidth <= 768;
    }

    // ── Init ──────────────────────────────────────────────────────────────
    function init() {
        // Hide on mobile if configured
        if ( !CFG.showOnMobile && isMobile() ) return;

        // Delay before showing the widget
        const delay = (CFG.showDelay || 0) * 1000;
        setTimeout(() => {
            widget.style.display = '';
            toggle.classList.add('wpsc-pulse');

            // Auto-open after additional delay
            if ( CFG.autoOpen && !hasAutoOpened ) {
                const autoDelay = (CFG.autoOpenDelay || 5) * 1000;
                setTimeout(() => {
                    if ( !isOpen && !hasAutoOpened ) {
                        hasAutoOpened = true;
                        openChat();
                    }
                }, autoDelay);
            }
        }, delay);

        // Events
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

    // ── Session ID ────────────────────────────────────────────────────────
    function generateSessionId() {
        return 'wpsc_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    // ── Toggle / Open / Close ─────────────────────────────────────────────
    function toggleChat() { isOpen ? closeChat() : openChat(); }

    function openChat() {
        isOpen = true;
        hasAutoOpened = true;
        widget.classList.add('wpsc-open');
        chatWin.setAttribute('aria-hidden', 'false');
        toggle.classList.remove('wpsc-pulse');

        if (messages.children.length === 0) {
            // Check if lead capture is needed first
            if ( CFG.leadCapture && CFG.leadCapture.enabled && !leadCaptured ) {
                showLeadCaptureForm();
            } else {
                showWelcome();
            }
        }

        setTimeout(() => input.focus(), 350);
    }

    function closeChat() {
        isOpen = false;
        widget.classList.remove('wpsc-open');
        chatWin.setAttribute('aria-hidden', 'true');
    }

    function showWelcome() {
        if (CFG.welcome) addBotMessageClean(CFG.welcome);
        if (CFG.quickReplies && CFG.quickReplies.length > 0) showQuickReplies(CFG.quickReplies);
    }

    // ══════════════════════════════════════════════════════════════════════
    // LEAD CAPTURE FORM
    // ══════════════════════════════════════════════════════════════════════
    function showLeadCaptureForm() {
        const lc = CFG.leadCapture;
        const form = document.createElement('div');
        form.className = 'wpsc-lead-form';

        let fieldsHTML = '<div class="wpsc-lead-title">' + escapeHtml(lc.title) + '</div>';

        if (lc.requireName) {
            fieldsHTML += '<input type="text" class="wpsc-lead-input" data-field="name" placeholder="Your name" autocomplete="name">';
        }
        if (lc.requireEmail) {
            fieldsHTML += '<input type="email" class="wpsc-lead-input" data-field="email" placeholder="Your email" autocomplete="email">';
        }
        if (lc.requirePhone) {
            fieldsHTML += '<input type="tel" class="wpsc-lead-input" data-field="phone" placeholder="Your phone" autocomplete="tel">';
        }

        fieldsHTML += '<button class="wpsc-lead-submit">Start Chatting →</button>';
        fieldsHTML += '<button class="wpsc-lead-skip">Skip for now</button>';

        form.innerHTML = fieldsHTML;
        messages.appendChild(form);

        // Submit handler
        form.querySelector('.wpsc-lead-submit').addEventListener('click', (e) => {
            e.stopPropagation();
            submitLeadForm(form);
        });

        // Skip handler
        form.querySelector('.wpsc-lead-skip').addEventListener('click', (e) => {
            e.stopPropagation();
            leadCaptured = true;
            form.remove();
            showWelcome();
        });

        // Enter key on inputs
        form.querySelectorAll('.wpsc-lead-input').forEach(inp => {
            inp.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); submitLeadForm(form); }
            });
        });

        scrollToBottom();
    }

    function submitLeadForm(form) {
        const inputs = form.querySelectorAll('.wpsc-lead-input');
        const lc = CFG.leadCapture;
        let valid = true;

        inputs.forEach(inp => {
            const field = inp.dataset.field;
            const val = inp.value.trim();
            inp.style.borderColor = '';

            if (field === 'email' && lc.requireEmail && (!val || !val.includes('@'))) {
                inp.style.borderColor = '#d63638';
                valid = false;
            }
            if (field === 'name' && lc.requireName && !val) {
                inp.style.borderColor = '#d63638';
                valid = false;
            }
            if (field === 'phone' && lc.requirePhone && !val) {
                inp.style.borderColor = '#d63638';
                valid = false;
            }

            leadData[field] = val;
        });

        if (!valid) return;

        leadCaptured = true;

        // Send lead data to server
        const fd = new FormData();
        fd.append('action', 'wpsc_save_lead');
        fd.append('nonce', CFG.nonce);
        fd.append('session_id', sessionId);
        fd.append('name', leadData.name);
        fd.append('email', leadData.email);
        fd.append('phone', leadData.phone);
        fd.append('page_url', CFG.pageUrl || '');
        fetch(CFG.ajaxUrl, { method: 'POST', body: fd });

        form.remove();
        showWelcome();
    }

    // ══════════════════════════════════════════════════════════════════════
    // SENDING MESSAGES
    // ══════════════════════════════════════════════════════════════════════
    function sendMessage() {
        const text = input.value.trim();
        if (!text || sending) return;

        addUserMessage(text);
        history.push({ role: 'user', content: text });
        removeQuickReplies();
        removeReturnToStart();
        removeContactButtons();

        input.value = '';
        input.style.height = 'auto';
        sendBtn.disabled = true;
        sending = true;

        const typing = addTypingIndicator();

        const fd = new FormData();
        fd.append('action', 'wpsc_send_message');
        fd.append('nonce', CFG.nonce);
        fd.append('message', text);
        fd.append('session_id', sessionId);
        fd.append('page_url', CFG.pageUrl || '');
        fd.append('visitor_name', leadData.name);
        fd.append('visitor_email', leadData.email);
        fd.append('visitor_phone', leadData.phone);

        history.slice(-10).forEach((msg, i) => {
            fd.append('history[' + i + '][role]', msg.role);
            fd.append('history[' + i + '][content]', msg.content);
        });

        fetch(CFG.ajaxUrl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                removeTypingIndicator(typing);
                if (data.success && data.data) {
                    addBotMessage(data.data.answer || "Sorry, I couldn't process that.", data.data.sources);
                    history.push({ role: 'assistant', content: data.data.answer });
                } else {
                    addBotMessage((data.data && data.data.message) || "Something went wrong.");
                }
            })
            .catch(() => {
                removeTypingIndicator(typing);
                addBotMessage("Oops! I'm having trouble connecting. Please try again.");
            })
            .finally(() => {
                sending = false;
                sendBtn.disabled = false;
                input.focus();
            });
    }

    function handleKey(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    }

    function autoResize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 100) + 'px';
    }

    // ══════════════════════════════════════════════════════════════════════
    // MESSAGE RENDERING
    // ══════════════════════════════════════════════════════════════════════
    function addUserMessage(text) {
        const el = document.createElement('div');
        el.className = 'wpsc-msg wpsc-msg-user';
        el.textContent = text;
        messages.appendChild(el);
        scrollToBottom();
    }

    function addBotMessage(text, sources) {
        removeReturnToStart();
        removeContactButtons();

        const el = document.createElement('div');
        el.className = 'wpsc-msg wpsc-msg-bot';
        el.innerHTML = renderMarkdown(text);

        if (sources && sources.length > 0) {
            const container = document.createElement('div');
            container.className = 'wpsc-sources';
            sources.forEach(s => {
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
        showActionButtons();
        scrollToBottom();
    }

    function addBotMessageClean(text) {
        const el = document.createElement('div');
        el.className = 'wpsc-msg wpsc-msg-bot';
        el.innerHTML = renderMarkdown(text);
        messages.appendChild(el);
        scrollToBottom();
    }

    // ══════════════════════════════════════════════════════════════════════
    // ACTION BUTTONS (Return to Start + Contact)
    // ══════════════════════════════════════════════════════════════════════
    function showActionButtons() {
        removeReturnToStart();
        removeContactButtons();

        const wrapper = document.createElement('div');
        wrapper.className = 'wpsc-action-buttons';

        // Return to Start
        const returnBtn = document.createElement('button');
        returnBtn.className = 'wpsc-return-btn';
        returnBtn.textContent = '↩ Return to Start';
        returnBtn.addEventListener('click', (e) => { e.stopPropagation(); resetChat(); });
        wrapper.appendChild(returnBtn);

        // Contact buttons
        if (CFG.contacts && CFG.contacts.length > 0) {
            const contactRow = document.createElement('div');
            contactRow.className = 'wpsc-contact-buttons';
            CFG.contacts.forEach(c => {
                const a = document.createElement('a');
                a.className = 'wpsc-contact-btn';
                a.href = c.value;
                a.textContent = c.label;
                if (c.type === 'link') { a.target = '_blank'; a.rel = 'noopener'; }
                a.addEventListener('click', (e) => e.stopPropagation());
                contactRow.appendChild(a);
            });
            wrapper.appendChild(contactRow);
        }

        messages.appendChild(wrapper);
    }

    function removeReturnToStart() {
        messages.querySelectorAll('.wpsc-action-buttons').forEach(el => el.remove());
    }

    function removeContactButtons() {
        messages.querySelectorAll('.wpsc-contact-buttons-standalone').forEach(el => el.remove());
    }

    function resetChat() {
        messages.innerHTML = '';
        history = [];
        sessionId = generateSessionId(); // Fresh session
        if (CFG.leadCapture && CFG.leadCapture.enabled && !leadCaptured) {
            showLeadCaptureForm();
        } else {
            showWelcome();
        }
    }

    // ── Typing Indicator ──────────────────────────────────────────────────
    function addTypingIndicator() {
        const el = document.createElement('div');
        el.className = 'wpsc-typing';
        el.innerHTML = '<div class="wpsc-typing-dot"></div><div class="wpsc-typing-dot"></div><div class="wpsc-typing-dot"></div>';
        messages.appendChild(el);
        scrollToBottom();
        return el;
    }

    function removeTypingIndicator(el) {
        if (el && el.parentNode) el.parentNode.removeChild(el);
    }

    // ── Quick Replies ─────────────────────────────────────────────────────
    function showQuickReplies(replies) {
        removeQuickReplies();
        const container = document.createElement('div');
        container.className = 'wpsc-quick-replies';
        replies.forEach(text => {
            const btn = document.createElement('button');
            btn.className = 'wpsc-quick-reply';
            btn.textContent = text;
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
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
        messages.querySelectorAll('.wpsc-quick-replies').forEach(el => el.remove());
    }

    // ── Scroll ────────────────────────────────────────────────────────────
    function scrollToBottom() {
        requestAnimationFrame(() => { messages.scrollTop = messages.scrollHeight; });
    }

    // ── Markdown ──────────────────────────────────────────────────────────
    function renderMarkdown(text) {
        let html = escapeHtml(text);
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
        html = html.replace(/\n/g, '<br>');
        return html;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ── Boot ──────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
