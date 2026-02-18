/**
 * Intelligize Chat — Frontend Widget v2.3
 *
 * Features: delay, auto-open, lead capture, contact buttons,
 * session tracking, return to start, DRAGGABLE + DOCKABLE window.
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

    // ── Drag State ────────────────────────────────────────────────────────
    let isDocked      = true;
    let isDragging    = false;
    let dragStartX    = 0;
    let dragStartY    = 0;
    let windowStartX  = 0;
    let windowStartY  = 0;
    let dragThreshold = 5;  // px of movement before we consider it a drag
    let dragMoved     = false;

    // ── Mobile check ──────────────────────────────────────────────────────
    function isMobile() {
        return window.innerWidth <= 480;
    }

    // ── Init ──────────────────────────────────────────────────────────────
    function init() {
        // Hide on mobile if configured
        if ( !CFG.showOnMobile && window.innerWidth <= 768 ) return;

        // Inject drag handle + dock button into header
        injectDragUI();

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
            if (isOpen && !widget.contains(e.target) && !chatWin.contains(e.target) && !e.target.closest('#wpsc-chat-widget') && !e.target.classList.contains('wpsc-quick-reply')) {
                closeChat();
            }
        });
    }

    // ══════════════════════════════════════════════════════════════════════
    // DRAG & DOCK SYSTEM
    // ══════════════════════════════════════════════════════════════════════

    function injectDragUI() {
        const header = chatWin.querySelector('.wpsc-header');
        if (!header) return;

        // Add drag handle bar (visible when undocked)
        const dragHandle = document.createElement('div');
        dragHandle.className = 'wpsc-drag-handle';
        header.appendChild(dragHandle);

        // Wrap close button in a container with dock button
        const existingClose = header.querySelector('.wpsc-close-btn');
        if (existingClose) {
            const btnGroup = document.createElement('div');
            btnGroup.className = 'wpsc-header-buttons';

            // Dock/undock button
            const dockBtn = document.createElement('button');
            dockBtn.className = 'wpsc-dock-btn';
            dockBtn.id = 'wpsc-dock-btn';
            dockBtn.setAttribute('aria-label', 'Undock chat window');
            dockBtn.title = 'Pop out — drag to reposition';
            dockBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>';
            dockBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (isDocked) {
                    undockWindow();
                } else {
                    dockWindow();
                }
            });

            // Insert dock btn before close btn
            existingClose.parentNode.insertBefore(btnGroup, existingClose);
            btnGroup.appendChild(dockBtn);
            btnGroup.appendChild(existingClose);
        }

        // Drag events on header (mouse)
        header.addEventListener('mousedown', onDragStart);
        document.addEventListener('mousemove', onDragMove);
        document.addEventListener('mouseup', onDragEnd);

        // Drag events on header (touch)
        header.addEventListener('touchstart', onDragStart, { passive: false });
        document.addEventListener('touchmove', onDragMove, { passive: false });
        document.addEventListener('touchend', onDragEnd);
    }

    function undockWindow() {
        if (isMobile()) return;

        const vw = window.innerWidth;
        const vh = window.innerHeight;
        const pos = CFG.position || 'bottom-right';
        const winWidth = chatWin.offsetWidth || 390;
        const winHeight = chatWin.offsetHeight || 580;

        // Calculate starting position (where the docked window visually sits)
        let startTop = vh - 24 - 76 - winHeight;
        let startLeft;

        if (pos === 'bottom-left') {
            startLeft = 24;
        } else {
            startLeft = vw - 24 - winWidth;
        }

        // Clamp fully inside the browser viewport
        startTop = Math.max(10, Math.min(vh - winHeight - 10, startTop));
        startLeft = Math.max(10, Math.min(vw - winWidth - 10, startLeft));

        isDocked = false;
        chatWin.classList.add('wpsc-undocked');

        // CRITICAL: Force-clear all CSS positioning via inline styles.
        // Inline !important beats everything, including .wpsc-bottom-right .wpsc-window { right: 0 !important }
        chatWin.style.setProperty('position', 'fixed', 'important');
        chatWin.style.setProperty('top', startTop + 'px', 'important');
        chatWin.style.setProperty('left', startLeft + 'px', 'important');
        chatWin.style.setProperty('right', 'auto', 'important');
        chatWin.style.setProperty('bottom', 'auto', 'important');

        // Update button icon to "dock back" (arrows pointing inward)
        const dockBtn = document.getElementById('wpsc-dock-btn');
        if (dockBtn) {
            dockBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="14" y1="10" x2="21" y2="3"/><line x1="3" y1="21" x2="10" y2="14"/></svg>';
            dockBtn.title = 'Dock back to corner';
            dockBtn.setAttribute('aria-label', 'Dock chat window');
        }
    }

    function dockWindow() {
        isDocked = true;
        chatWin.classList.remove('wpsc-undocked');
        chatWin.classList.remove('wpsc-dragging');

        // Remove ALL inline position overrides so CSS rules take back over
        chatWin.style.removeProperty('position');
        chatWin.style.removeProperty('top');
        chatWin.style.removeProperty('left');
        chatWin.style.removeProperty('right');
        chatWin.style.removeProperty('bottom');

        // Hide snap zone
        removeSnapZone();

        // Update button icon to "undock" (arrows pointing outward)
        const dockBtn = document.getElementById('wpsc-dock-btn');
        if (dockBtn) {
            dockBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>';
            dockBtn.title = 'Pop out — drag to reposition';
            dockBtn.setAttribute('aria-label', 'Undock chat window');
        }
    }

    function onDragStart(e) {
        // Only drag when undocked, and only from the header
        if (isDocked || isMobile()) return;
        // Don't drag if clicking a button
        if (e.target.closest('button')) return;

        const touch = e.touches ? e.touches[0] : e;
        dragStartX = touch.clientX;
        dragStartY = touch.clientY;

        const rect = chatWin.getBoundingClientRect();
        windowStartX = rect.left;
        windowStartY = rect.top;

        isDragging = true;
        dragMoved = false;

        if (e.touches) e.preventDefault();
    }

    function onDragMove(e) {
        if (!isDragging) return;

        const touch = e.touches ? e.touches[0] : e;
        const dx = touch.clientX - dragStartX;
        const dy = touch.clientY - dragStartY;

        // Only start visual drag after threshold
        if (!dragMoved && Math.abs(dx) < dragThreshold && Math.abs(dy) < dragThreshold) return;
        dragMoved = true;

        chatWin.classList.add('wpsc-dragging');

        // Calculate new position — fully constrained inside browser viewport
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        const w = chatWin.offsetWidth;
        const h = chatWin.offsetHeight;

        let newX = windowStartX + dx;
        let newY = windowStartY + dy;

        // Hard clamp: entire window must stay inside viewport
        newX = Math.max(0, Math.min(vw - w, newX));
        newY = Math.max(0, Math.min(vh - h, newY));

        chatWin.style.setProperty('left', newX + 'px', 'important');
        chatWin.style.setProperty('top', newY + 'px', 'important');

        // Show snap zone when dragged near the original dock position
        checkSnapZone(newX, newY);

        if (e.touches) e.preventDefault();
        e.preventDefault();
    }

    function onDragEnd(e) {
        if (!isDragging) return;
        isDragging = false;
        chatWin.classList.remove('wpsc-dragging');

        // If dragged into snap zone, dock it
        if (dragMoved && isNearDockPosition()) {
            dockWindow();
        }

        removeSnapZone();
    }

    // ── Snap Zone (visual hint for docking back) ──────────────────────────

    function checkSnapZone(x, y) {
        const pos = CFG.position || 'bottom-right';
        const vw = window.innerWidth;
        const vh = window.innerHeight;

        // Dock position: where the window normally sits
        let dockX, dockY;
        if (pos === 'bottom-left') {
            dockX = 24;
            dockY = vh - 580 - 76 - 24; // bottom:24 + toggle area
        } else {
            dockX = vw - 390 - 24;
            dockY = vh - 580 - 76 - 24;
        }

        const dist = Math.sqrt(Math.pow(x - dockX, 2) + Math.pow(y - dockY, 2));

        if (dist < 200) {
            showSnapZone();
        } else {
            removeSnapZone();
        }
    }

    function isNearDockPosition() {
        const rect = chatWin.getBoundingClientRect();
        const pos = CFG.position || 'bottom-right';
        const vw = window.innerWidth;
        const vh = window.innerHeight;

        let dockX, dockY;
        if (pos === 'bottom-left') {
            dockX = 24;
            dockY = vh - 580 - 76 - 24;
        } else {
            dockX = vw - 390 - 24;
            dockY = vh - 580 - 76 - 24;
        }

        const dist = Math.sqrt(Math.pow(rect.left - dockX, 2) + Math.pow(rect.top - dockY, 2));
        return dist < 150;
    }

    function showSnapZone() {
        let zone = document.getElementById('wpsc-snap-zone');
        if (!zone) {
            zone = document.createElement('div');
            zone.id = 'wpsc-snap-zone';
            zone.className = 'wpsc-snap-zone';
            const pos = CFG.position || 'bottom-right';
            zone.classList.add(pos === 'bottom-left' ? 'wpsc-snap-left' : 'wpsc-snap-right');
            document.body.appendChild(zone);
        }
        zone.classList.add('wpsc-snap-visible');
    }

    function removeSnapZone() {
        const zone = document.getElementById('wpsc-snap-zone');
        if (zone) zone.classList.remove('wpsc-snap-visible');
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

        // If undocked, make sure it's visible
        if (!isDocked) {
            chatWin.style.opacity = '1';
            chatWin.style.transform = 'none';
            chatWin.style.pointerEvents = 'all';
        }

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

        // If undocked, also hide it properly
        if (!isDocked) {
            chatWin.style.opacity = '0';
            chatWin.style.pointerEvents = 'none';
        }
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

    // ── Markdown + Smart Contact Linking ─────────────────────────────────
    function renderMarkdown(text) {
        let html = escapeHtml(text);

        // Bold: **text**
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

        // Markdown links: [text](url)
        html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');

        // ── Auto-link emails ──
        html = html.replace(
            /(?:[\u{1F4E7}\u{1F4E8}\u{1F4E9}\u{2709}\u{FE0F}]\s*)?([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/gu,
            function(match, email) {
                if (match.indexOf('mailto:') !== -1) return match;
                return '<a href="mailto:' + email + '" class="wpsc-smart-link wpsc-smart-email">📧 ' + email + '</a>';
            }
        );

        // ── Auto-link phone numbers ──
        html = html.replace(
            /(?:[\u{1F4DE}\u{1F4F1}\u{1F4F2}\u{260E}\u{2706}\u{FE0F}]\s*)?(\+?1?\s*[-.]?\s*\(?\d{3}\)?[\s.\-]*\d{3}[\s.\-]*\d{4})/gu,
            function(match, phone) {
                if (match.indexOf('tel:') !== -1) return match;
                var digits = phone.replace(/[^\d+]/g, '');
                if (digits.length < 10) return match;
                return '<a href="tel:' + digits + '" class="wpsc-smart-link wpsc-smart-phone">📞 ' + phone.trim() + '</a>';
            }
        );

        // ── Auto-link addresses ──
        html = html.replace(
            /(\d{1,5}\s+[A-Za-z0-9\s.]+\b(?:Street|Avenue|Boulevard|Drive|Road|Lane|Way|Court|Circle|Place|Parkway|Highway)\b[.,]?(?:\s*(?:Suite|Ste|Unit|Apt|#)\s*\w+)?[.,]?\s*(?:[A-Za-z]+(?:\s+[A-Za-z]+)*[,]\s*)?(?:[A-Z]{2}\s+\d{5}(?:-\d{4})?)?(?:[,\s]+[A-Za-z]+)?)/g,
            function(match, addr) {
                var clean = addr.trim().replace(/[,\s]+$/, '');
                if (clean.length < 12) return match;
                var encoded = encodeURIComponent(clean);
                return '<a href="https://www.google.com/maps/search/?api=1&query=' + encoded + '" target="_blank" rel="noopener" class="wpsc-smart-link wpsc-smart-address">📍 ' + clean + '</a>';
            }
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

    // ── Boot ──────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
