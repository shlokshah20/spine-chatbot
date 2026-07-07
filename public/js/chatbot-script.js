/**
 * Spine HR Chatbot — Frontend AI Conversation Widget  (v2.1)
 *
 * State machine:
 *   idle
 *     └─ open ──────────────────────────────► product_chat (direct, no branch step)
 *
 *   product_chat
 *     ├─ answer          → (stay in product_chat)
 *     ├─ demo_booked     → (stay in product_chat, AI handled it)
 *     ├─ requesting_agent
 *     │     ├─ pending   → requesting_agent → live_agent (via poll)
 *     │     └─ offline   → lead_form
 *     └─ live_agent      → closed
 *
 * Two-stream routing (support vs. prospect) is handled entirely server-side
 * by the AI system prompt. The frontend just sends messages and renders responses.
 *
 * @version 2.1.0
 * @package SpineChatbot
 */

/* global spineChatVars */
(function ($) {
  'use strict';

  if (typeof spineChatVars === 'undefined') return;

  const V = spineChatVars;

  // ── App state ──────────────────────────────────────────────────────────────
  const Chat = {
    state:      'idle',   // idle | product_chat | requesting_agent | live_agent | lead_form | closed
    sessionId:  null,
    isLiveChat: false,
    agentName:  null,
    hasGreeted: false,
    focused:    !document.hidden,
    lastMsgTime:'',
  };

  // ── Visitor-side polling timer ─────────────────────────────────────────────
  var _pollTimer   = null;
  var POLL_FAST_MS = 2000;   // 2 s while live agent is connected
  var POLL_SLOW_MS = 4000;   // 4 s while waiting for agent to accept

  // ── DOM references ─────────────────────────────────────────────────────────
  const el = {
    root:       document.getElementById('spine-chat-root'),
    launcher:   document.getElementById('spine-chat-launcher'),
    widget:     document.getElementById('spine-chat-widget'),
    messages:   document.getElementById('spine-chat-messages'),
    typing:     document.getElementById('spine-typing-indicator'),
    leadForm:   document.getElementById('spine-lead-form'),
    leadFormEl: document.getElementById('spine-lead-form-inner'),
    inputArea:  document.getElementById('spine-input-area'),
    input:      document.getElementById('spine-chat-input'),
    sendBtn:    document.getElementById('spine-chat-send'),
    closeBtn:   document.getElementById('spine-chat-close'),
    minimiseBtn:document.getElementById('spine-chat-minimise'),
    restartBtn: document.getElementById('spine-restart-btn'),
    statusDot:  document.getElementById('spine-status-dot'),
    statusLabel:document.getElementById('spine-status-label'),
    unreadBadge:document.getElementById('spine-unread-badge'),
    demoLink:   document.getElementById('spine-demo-link'),
    leadSubmit: document.getElementById('spine-lead-submit'),
    attachBtn:  document.getElementById('spine-attach-btn'),
    fileInput:  document.getElementById('spine-file-input'),
  };

  // ═══════════════════════════════════════════════════════════════════════════
  // BOOT
  // ═══════════════════════════════════════════════════════════════════════════

  function init() {
    if (el.demoLink) el.demoLink.href = V.demoUrl || '#';

    el.launcher.addEventListener('click', toggleWidget);
    el.closeBtn.addEventListener('click',  () => closeWidget(true));
    el.minimiseBtn.addEventListener('click', () => closeWidget(false));
    el.restartBtn && el.restartBtn.addEventListener('click', restartSession);

    el.sendBtn.addEventListener('click', handleSend);

    el.input.addEventListener('input', function () {
      el.sendBtn.disabled = el.input.value.trim().length === 0;
      autoResizeTextarea(el.input);
    });

    el.input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (!el.sendBtn.disabled) handleSend();
      }
    });

    if (el.leadFormEl) el.leadFormEl.addEventListener('submit', handleLeadSubmit);

    if (el.fileInput) {
      el.fileInput.addEventListener('change', function () {
        if (el.fileInput.files && el.fileInput.files[0]) {
          uploadVisitorFile(el.fileInput.files[0]);
          el.fileInput.value = '';
        }
      });
    }

    initVisibilityTracking();

    // Try to restore previous session
    const stored = sessionStorage.getItem('spine_session_id');
    if (stored) {
      initSession(stored);
    }
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // WIDGET OPEN / CLOSE
  // ═══════════════════════════════════════════════════════════════════════════

  function toggleWidget() {
    if (el.root.classList.contains('spine-chat--open')) {
      closeWidget(false);
    } else {
      openWidget();
    }
  }

  function openWidget() {
    el.root.classList.add('spine-chat--open');
    el.widget.removeAttribute('hidden');
    el.launcher.setAttribute('aria-expanded', 'true');
    el.unreadBadge && (el.unreadBadge.hidden = true);

    if (!Chat.sessionId) {
      initSession(null);
    }

    scrollToBottom();
  }

  function closeWidget(hard) {
    el.root.classList.remove('spine-chat--open');
    el.launcher.setAttribute('aria-expanded', 'false');
    setTimeout(() => el.widget.setAttribute('hidden', ''), 280);

    if (hard) {
      stopPoll();
      Chat.state      = 'idle';
      Chat.sessionId  = null;
      Chat.isLiveChat = false;
      Chat.hasGreeted = false;
      sessionStorage.removeItem('spine_session_id');
      el.messages.innerHTML = '';
      el.input.disabled     = true;
      el.input.value        = '';
      el.sendBtn.disabled   = true;
      el.restartBtn && el.restartBtn.setAttribute('hidden', '');
    }
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // SESSION
  // ═══════════════════════════════════════════════════════════════════════════

  function initSession(storedId) {
    ajax('spine_chat_init', { session_id: storedId || '' }).then(function (res) {
      if (!res || !res.success) return;
      const d = res.data;

      Chat.sessionId = d.session_id;
      sessionStorage.setItem('spine_session_id', Chat.sessionId);

      const tx = d.transcript || [];

      if (tx.length > 0) {
        // Restore existing session
        tx.forEach(function (msg) {
          if (msg.role === 'system') return;
          renderMessageRaw(msg.content, msg.role, msg.agent_name || null, msg);
        });
        Chat.lastMsgTime = tx[tx.length - 1].timestamp || '';
        Chat.hasGreeted  = true;
        syncStateFromServer(d.status);
      } else {
        // Fresh session — show welcome and open input immediately
        Chat.hasGreeted = true;
        renderMessageText(V.welcomeMessage, 'bot');
        Chat.state = 'product_chat';
        unlockInput(false);
        showRestartBtn();
      }
    });
  }

  function syncStateFromServer(serverStatus) {
    switch (serverStatus) {
      case 'pending_agent':
        Chat.state = 'requesting_agent';
        setStatusLabel('Waiting for agent…', 'away');
        unlockInput(true);
        startPoll(false);
        break;

      case 'active':
        Chat.state     = 'live_agent';
        Chat.isLiveChat= true;
        setStatusLabel(Chat.agentName || 'Agent', 'online');
        unlockInput(false);
        showAttachBtn(true);
        startPoll(true);
        break;

      case 'lead_captured':
      case 'closed':
      case 'support_redirect':
        Chat.state = 'closed';
        freezeInputClosed();
        break;

      default:
        // 'bot' or any other status → open AI chat
        Chat.state = 'product_chat';
        unlockInput(false);
        showRestartBtn();
        break;
    }
  }

  function restartSession() {
    stopPoll();
    showAttachBtn(false);
    Chat.state      = 'idle';
    Chat.sessionId  = null;
    Chat.isLiveChat = false;
    Chat.hasGreeted = false;
    sessionStorage.removeItem('spine_session_id');
    el.messages.innerHTML = '';
    el.restartBtn && el.restartBtn.setAttribute('hidden', '');
    el.input.disabled   = true;
    el.input.value      = '';
    el.sendBtn.disabled = true;
    clearAgentOffer();
    initSession(null);
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // INPUT STATE
  // ═══════════════════════════════════════════════════════════════════════════

  function unlockInput(keepDisabled) {
    el.inputArea.classList.remove('spine-chat__input-area--frozen');
    el.inputArea.removeAttribute('hidden');

    if (keepDisabled) {
      el.input.disabled    = true;
      el.input.placeholder = 'Waiting for an agent to connect…';
      el.sendBtn.disabled  = true;
    } else {
      el.input.disabled    = false;
      el.input.placeholder = 'Type your message…';
      el.sendBtn.disabled  = el.input.value.trim().length === 0;
      el.input.focus();
    }
  }

  function freezeInputClosed() {
    el.inputArea.classList.add('spine-chat__input-area--frozen');
    el.inputArea.removeAttribute('hidden');
    el.input.disabled    = true;
    el.input.value       = '';
    el.input.placeholder = 'This conversation has ended.';
    el.sendBtn.disabled  = true;
  }

  function showRestartBtn() {
    el.restartBtn && el.restartBtn.removeAttribute('hidden');
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // MESSAGING
  // ═══════════════════════════════════════════════════════════════════════════

  function handleSend() {
    const text = el.input.value.trim();
    if (!text || !Chat.sessionId) return;
    if (Chat.state === 'closed') return;

    el.input.value        = '';
    el.input.style.height = '';
    el.sendBtn.disabled   = true;
    clearAgentOffer();
    renderMessageText(text, 'user');
    showTyping();

    ajax('spine_chat_message', {
      session_id: Chat.sessionId,
      message   : text,
      branch    : '',
    }).then(function (res) {
      hideTyping();
      // Live-agent messages arrive via poll — no bot response needed
      if (Chat.isLiveChat) return;
      if (res && res.success) {
        handleBotResponse(res.data);
      } else {
        renderMessageText('Something went wrong. Please try again.', 'bot');
      }
    });
  }

  function handleBotResponse(data) {
    Chat.lastMsgTime = new Date().toISOString().slice(0, 19).replace('T', ' ');

    switch (data.type) {
      case 'requesting_agent':
        renderMessageHTML(data.response, 'bot');
        if (data.outcome === 'pending_agent') {
          Chat.state = 'requesting_agent';
          setStatusLabel('Waiting for agent…', 'away');
          unlockInput(true);
          startPoll(false);
        } else {
          // offline — offer lead form
          showLeadForm('offline_ticket');
        }
        break;

      case 'demo_booked':
        renderMessageHTML(data.response, 'bot');
        break;

      case 'live_agent':
        // Session already active with a live agent
        if (!Chat.isLiveChat) {
          Chat.isLiveChat = true;
          Chat.state      = 'live_agent';
          if (data.agent_name) Chat.agentName = data.agent_name;
          setStatusLabel(Chat.agentName || 'Agent', 'online');
          unlockInput(false);
          clearAgentOffer();
          showAttachBtn(true);
          startPoll(true);
        }
        break;

      case 'answer':
      default:
        renderMessageHTML(data.response, 'bot');
        break;
    }

    scrollToBottom();
  }

  function clearAgentOffer() {
    if (el.messages) {
      el.messages.querySelectorAll('.spine-chat__agent-offer').forEach(function (n) { n.remove(); });
    }
  }

  function showAttachBtn(show) {
    if (el.attachBtn) el.attachBtn.hidden = !show;
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // AGENT REQUEST  (manual trigger; AI-driven handover goes through handleBotResponse)
  // ═══════════════════════════════════════════════════════════════════════════

  function requestAgent() {
    if (!Chat.sessionId) return;
    if (Chat.state === 'requesting_agent' || Chat.state === 'live_agent') return;

    Chat.state = 'requesting_agent';
    clearAgentOffer();
    showTyping();

    ajax('spine_chat_request_agent', { session_id: Chat.sessionId }).then(function (res) {
      hideTyping();
      if (!res || !res.success) {
        Chat.state = 'product_chat';
        renderMessageText('Something went wrong. Please try again.', 'bot');
        return;
      }

      const d = res.data;

      if (d.outcome === 'pending_agent') {
        renderMessageText(d.message, 'bot');
        setStatusLabel('Waiting for agent…', 'away');
        unlockInput(true);
        startPoll(false);
      } else if (d.outcome === 'active') {
        Chat.state     = 'live_agent';
        Chat.isLiveChat= true;
        Chat.agentName = d.agent_name || 'Agent';
        renderMessageText(d.message || 'An agent has joined.', 'bot');
        setStatusLabel(Chat.agentName, 'online');
        unlockInput(false);
        clearAgentOffer();
        showAttachBtn(true);
        startPoll(true);
      } else {
        Chat.state = 'product_chat';
        renderMessageText(d.message || 'No agents available right now.', 'bot');
        showLeadForm('offline_ticket');
      }
    });
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // LEAD FORM
  // ═══════════════════════════════════════════════════════════════════════════

  function showLeadForm(type) {
    Chat.state = 'lead_form';
    el.leadForm.removeAttribute('hidden');
    el.inputArea.setAttribute('hidden', '');

    const typeInput = el.leadFormEl && el.leadFormEl.querySelector('input[name="type"]');
    if (typeInput) typeInput.value = type || 'demo_request';
  }

  function handleLeadSubmit(e) {
    e.preventDefault();
    clearLeadErrors();

    const fd      = new FormData(el.leadFormEl);
    const name    = (fd.get('name')    || '').trim();
    const email   = (fd.get('email')   || '').trim();
    const phone   = (fd.get('phone')   || '').trim();
    const company = (fd.get('company') || '').trim();
    const msg     = (fd.get('message') || '').trim();
    const type    = fd.get('type') || 'demo_request';

    var emailInput = document.getElementById('spine-lead-email');
    var emailValid = email && emailInput
      ? emailInput.validity.valid
      : /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);

    var PERSONAL_DOMAINS = [
      'gmail.com','googlemail.com','yahoo.com','yahoo.co.in','yahoo.co.uk',
      'yahoo.com.au','ymail.com','hotmail.com','hotmail.co.uk','hotmail.in',
      'outlook.com','outlook.in','live.com','msn.com','icloud.com','me.com',
      'mac.com','aol.com','mail.com','zoho.com','protonmail.com','proton.me',
      'tutanota.com','rediffmail.com','rocketmail.com','inbox.com',
      'fastmail.com','gmx.com','gmx.net','yandex.com','yandex.ru',
      'tempmail.com','guerrillamail.com','10minutemail.com',
    ];
    var emailDomain      = email.split('@')[1] ? email.split('@')[1].toLowerCase() : '';
    var isPersonalDomain = PERSONAL_DOMAINS.indexOf(emailDomain) !== -1;

    const errors = {};
    if (!name)  errors.name  = 'Name is required.';
    if (!email || !emailValid) {
      errors.email = 'A valid email address is required.';
    } else if (isPersonalDomain) {
      errors.email = 'Please use your work email address (personal emails like Gmail are not accepted).';
    }
    if (!phone)   errors.phone   = 'Phone is required.';
    if (!company) errors.company = 'Company name is required.';

    if (Object.keys(errors).length) {
      displayLeadErrors(errors);
      return;
    }

    el.leadSubmit.disabled    = true;
    el.leadSubmit.textContent = 'Submitting…';

    ajax('spine_chat_submit_lead', {
      session_id: Chat.sessionId,
      name, email, phone, company, message: msg, type,
    }).then(function (res) {
      el.leadSubmit.disabled = false;
      if (res && res.success) {
        el.leadForm.setAttribute('hidden', '');
        el.inputArea.removeAttribute('hidden');
        Chat.state = 'closed';
        renderMessageText(res.data.message || "Thank you! We'll be in touch shortly.", 'bot');
        freezeInputClosed();
        scrollToBottom();
      } else {
        el.leadSubmit.textContent = 'Submit Request';
        if (res && res.data && res.data.errors) {
          displayLeadErrors(res.data.errors);
        } else {
          renderMessageText('Something went wrong. Please try again.', 'bot');
        }
      }
    });
  }

  function clearLeadErrors() {
    document.querySelectorAll('.spine-chat__field-error').forEach(function (e) {
      e.textContent = '';
      e.hidden = true;
    });
  }

  function displayLeadErrors(errors) {
    Object.keys(errors).forEach(function (field) {
      const errEl = document.getElementById('err-' + field);
      if (errEl) { errEl.textContent = errors[field]; errEl.hidden = false; }
    });
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // MESSAGE RENDERING
  // ═══════════════════════════════════════════════════════════════════════════

  function renderMessageText(text, role, agentName) {
    if (!text || role === 'system') return;
    const wrap   = buildWrap(role, agentName);
    const bubble = wrap.querySelector('.spine-msg__bubble');
    bubble.innerHTML = formatMarkdown(escHtml(text));
    el.messages.appendChild(wrap);
    scrollToBottom();
    updateUnreadBadge(role);
    Chat.lastMsgTime = new Date().toISOString().slice(0, 19).replace('T', ' ');
  }

  function renderMessageHTML(html, role, agentName) {
    if (!html || role === 'system') return;
    const wrap   = buildWrap(role, agentName);
    const bubble = wrap.querySelector('.spine-msg__bubble');
    bubble.innerHTML = formatMarkdown(html);
    el.messages.appendChild(wrap);
    scrollToBottom();
    updateUnreadBadge(role);
    Chat.lastMsgTime = new Date().toISOString().slice(0, 19).replace('T', ' ');
  }

  function renderMessageRaw(content, role, agentName, msgObj) {
    if (role === 'system') return;
    if (msgObj && msgObj.file_url) {
      renderFileAttachment(msgObj, role, agentName);
      return;
    }
    if (role === 'user') {
      renderMessageText(content, role, agentName);
    } else {
      renderMessageHTML(content, role, agentName);
    }
  }

  function renderFileAttachment(msg, role, agentName) {
    const wrap   = buildWrap(role, agentName);
    const bubble = wrap.querySelector('.spine-msg__bubble');
    var isImage  = (msg.file_type || '').startsWith('image/');
    var safeUrl  = msg.file_url  || '';
    var safeName = escHtml(msg.file_name || 'attachment');

    if (isImage) {
      bubble.innerHTML =
        '<a href="' + safeUrl + '" target="_blank" rel="noopener" class="spine-chat__file-link">' +
        '<img src="' + safeUrl + '" alt="' + safeName + '" class="spine-chat__file-img">' +
        '</a>';
    } else {
      bubble.innerHTML =
        '<a href="' + safeUrl + '" target="_blank" rel="noopener" class="spine-chat__file-link spine-chat__file-link--doc">' +
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>' +
        ' ' + safeName + '</a>';
    }

    el.messages.appendChild(wrap);
    scrollToBottom();
  }

  function uploadVisitorFile(file) {
    var MAX_BYTES = 5 * 1024 * 1024;
    if (file.size > MAX_BYTES) {
      renderMessageText('File too large — max 5 MB allowed.', 'bot');
      return;
    }

    var fd = new FormData();
    fd.append('action',     'spine_chat_upload');
    fd.append('nonce',      V.nonce);
    fd.append('session_id', Chat.sessionId);
    fd.append('file',       file);

    renderMessageText('[Uploading ' + escHtml(file.name) + '…]', 'user');
    showTyping();

    fetch(_ajaxUrl, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        hideTyping();
        var last = el.messages.lastElementChild;
        if (res && res.success) {
          if (last) last.remove();
          renderFileAttachment({
            file_url:  res.data.file_url,
            file_name: res.data.file_name,
            file_type: res.data.file_type,
          }, 'user', null);
        } else {
          if (last) last.remove();
          renderMessageText('Upload failed. Please try again.', 'bot');
        }
      })
      .catch(function () {
        hideTyping();
        renderMessageText('Upload failed. Please try again.', 'bot');
      });
  }

  function buildWrap(role, agentName) {
    const wrap = document.createElement('div');
    wrap.className = 'spine-msg spine-msg--' + role;

    if (role !== 'system' && role !== 'user') {
      const avatarEl = document.createElement('div');
      avatarEl.className = 'spine-msg__avatar';
      if (role === 'agent' && agentName) {
        avatarEl.textContent = agentName.charAt(0).toUpperCase();
        avatarEl.style.background = 'linear-gradient(135deg,#059669,#0d9488)';
      } else {
        avatarEl.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2Z"/></svg>';
      }
      wrap.appendChild(avatarEl);
    }

    const bubble = document.createElement('div');
    bubble.className = 'spine-msg__bubble';

    if (role === 'agent' && agentName) {
      const nameEl = document.createElement('span');
      nameEl.className   = 'spine-msg__agent-name';
      nameEl.textContent = agentName;
      bubble.appendChild(nameEl);
    }

    wrap.appendChild(bubble);
    return wrap;
  }

  function formatMarkdown(html) {
    if (!html) return '';
    let out = html;
    out = out.replace(/\*\*(.+?)\*\*/g,      '<strong>$1</strong>');
    out = out.replace(/^•\s(.+)$/gm,          '<li>$1</li>');
    out = out.replace(/(<li>[\s\S]+?<\/li>)/g,'<ul>$1</ul>');
    out = out.replace(/\n/g,                   '<br>');
    return out;
  }

  function updateUnreadBadge(role) {
    if (role !== 'user' && el.root && !el.root.classList.contains('spine-chat--open')) {
      el.unreadBadge && (el.unreadBadge.hidden = false);
    }
  }

  // ── Typing indicator ───────────────────────────────────────────────────────
  function showTyping() { if (el.typing) el.typing.removeAttribute('hidden'); scrollToBottom(); }
  function hideTyping() { if (el.typing) el.typing.setAttribute('hidden', ''); }

  // ── Status label ───────────────────────────────────────────────────────────
  function setStatusLabel(label, mode) {
    if (el.statusLabel) el.statusLabel.textContent = label;
    if (el.statusDot) {
      el.statusDot.className = 'spine-chat__status-dot';
      if (mode === 'online')  el.statusDot.classList.add('spine-chat__status-dot--online');
      if (mode === 'away')    el.statusDot.classList.add('spine-chat__status-dot--away');
      if (mode === 'offline') el.statusDot.classList.add('spine-chat__status-dot--offline');
    }
  }

  // ── Scroll ─────────────────────────────────────────────────────────────────
  function scrollToBottom() {
    if (el.messages) el.messages.scrollTop = el.messages.scrollHeight;
  }

  // ── Textarea autoresize ────────────────────────────────────────────────────
  function autoResizeTextarea(ta) {
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
  }

  // ── HTML escape ────────────────────────────────────────────────────────────
  function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // VISITOR POLLING  (setInterval — 2 s live / 4 s waiting for agent)
  // ═══════════════════════════════════════════════════════════════════════════

  function startPoll(fast) {
    stopPoll();
    _pollTimer = setInterval(doPoll, fast ? POLL_FAST_MS : POLL_SLOW_MS);
  }

  function stopPoll() {
    if (_pollTimer !== null) { clearInterval(_pollTimer); _pollTimer = null; }
  }

  function doPoll() {
    if (!Chat.sessionId) return;
    if (Chat.state === 'closed' || Chat.state === 'idle') { stopPoll(); return; }

    ajax('spine_chat_poll', { session_id: Chat.sessionId, since: Chat.lastMsgTime })
      .then(function (res) {
        if (!res || !res.success) return;
        var d = res.data;

        if (d.messages && d.messages.length > 0) {
          d.messages.forEach(function (msg) {
            if (msg.role !== 'user') {
              renderMessageRaw(msg.content, msg.role, msg.agent_name || null, msg);
            }
          });
          var lastMsg = d.messages[d.messages.length - 1];
          if (lastMsg.timestamp) Chat.lastMsgTime = lastMsg.timestamp;
        }
        if (d.server_time) Chat.lastMsgTime = d.server_time;

        // pending_agent → live_agent transition
        if (d.status === 'active' && !Chat.isLiveChat) {
          Chat.isLiveChat = true;
          Chat.state      = 'live_agent';
          var ai          = d.agent_info || {};
          Chat.agentName  = ai.name || Chat.agentName || 'Agent';
          setStatusLabel(Chat.agentName, 'online');
          unlockInput(false);
          clearAgentOffer();
          showAttachBtn(true);
          startPoll(true);
        }

        // Agent closed the session
        if (d.status === 'closed' && Chat.isLiveChat) {
          Chat.isLiveChat = false;
          Chat.state      = 'closed';
          setStatusLabel('Chat ended', 'offline');
          freezeInputClosed();
          stopPoll();
        }
      });
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // VISIBILITY / TAB FOCUS TRACKING
  // ═══════════════════════════════════════════════════════════════════════════

  function initVisibilityTracking() {
    document.addEventListener('visibilitychange', function () {
      Chat.focused = !document.hidden;
      if (Chat.focused && _pollTimer !== null) {
        startPoll(Chat.isLiveChat);
      }
    });
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // AJAX HELPER
  // ═══════════════════════════════════════════════════════════════════════════

  var _ajaxUrl = (function () {
    var url = V.ajaxUrl || '/wp-admin/admin-ajax.php';
    if (url.charAt(0) === '/') { return url; }
    try { return new URL(url).pathname; } catch (e) {}
    return '/wp-admin/admin-ajax.php';
  }());

  function ajax(action, data) {
    return $.ajax({
      url   : _ajaxUrl,
      method: 'POST',
      data  : Object.assign({ action: action, nonce: V.nonce }, data || {}),
    }).then(function (res) {
      return res;
    }).catch(function (jqXHR) {
      if (typeof console !== 'undefined' && console.error) {
        console.error('[SpineChat] AJAX error for "' + action + '"',
          { status: jqXHR.status, responseText: jqXHR.responseText });
      }
      return null;
    });
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // BOOTSTRAP
  // ═══════════════════════════════════════════════════════════════════════════

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

}(jQuery));
