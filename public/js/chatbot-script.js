/**
 * Spine HR Chatbot — Frontend State Machine  (v1.1)
 *
 * State machine:
 *   idle
 *     └─ open ──────────────────────────────► branch_select
 *                                                 ├─ [HR Suite]         ──► product_chat (scope: hr_suite)
 *                                                 ├─ [Assets]           ──► product_chat (scope: assets)
 *                                                 ├─ [Support]          ──► support      (frozen, mailto)
 *                                                 └─ [Intl HR Suite]    ──► product_chat (scope: international)
 *
 *   product_chat
 *     ├─ bot answer        → (stay in product_chat)
 *     ├─ alternatives      → show module cards → pick one → answer
 *     ├─ no_match          → offer agent / lead form
 *     ├─ request agent (online)  → requesting_agent → live_agent
 *     └─ request agent (offline) → lead_form
 *
 *   support
 *     └─ (frozen — input disabled; mailto link displayed; restart button visible)
 *
 *   live_agent
 *     └─ agent close → closed
 *
 *   lead_form
 *     └─ submit → closed
 *
 * Session persistence:
 *   session_id stored in sessionStorage. On page reload, init restores the
 *   session from the server and reconstructs state from session status + branch.
 *
 * Security:
 *   All AJAX calls include a nonce. User-generated content is inserted only
 *   via textContent or through escHtml(). Server-generated HTML (bot messages
 *   containing <a> tags etc.) is server-side sanitised before echoing.
 *
 * @version 1.1.0
 * @package SpineChatbot
 */

/* global spineChatVars, wp */
(function ($) {
  'use strict';

  if (typeof spineChatVars === 'undefined') return;

  const V = spineChatVars;

  // ── SVG icon library (keyed by branch.icon string from spineChatVars.branches) ──
  const BRANCH_ICONS = {
    people: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
    box:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
    headset:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 18v-6a9 9 0 0118 0v6"/><path d="M21 19a2 2 0 01-2 2h-1a2 2 0 01-2-2v-3a2 2 0 012-2h3v5z"/><path d="M3 19a2 2 0 002 2h1a2 2 0 002-2v-3a2 2 0 00-2-2H3v5z"/></svg>',
    globe:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>',
  };

  // ── App state ──────────────────────────────────────────────────────────────
  const Chat = {
    // Current state machine state
    state: 'idle',   // idle|branch_select|product_chat|support|requesting_agent|live_agent|lead_form|closed

    sessionId:   null,
    branch:      '',     // hr_suite | assets | support | international
    isLiveChat:  false,
    agentName:   null,
    hasGreeted:  false,
    focused:     !document.hidden,
    lastMsgTime: '',
  };

  // ── Visitor-side polling timer ─────────────────────────────────────────────
  var _pollTimer      = null;
  var POLL_SLOW_MS    = 4000;   // poll every 4 s while waiting for agent to accept
  var POLL_FAST_MS    = 2000;   // poll every 2 s while in live chat

  // ── DOM references ─────────────────────────────────────────────────────────
  const el = {
    root:          document.getElementById('spine-chat-root'),
    launcher:      document.getElementById('spine-chat-launcher'),
    widget:        document.getElementById('spine-chat-widget'),
    messages:      document.getElementById('spine-chat-messages'),
    typing:        document.getElementById('spine-typing-indicator'),
    branchPanel:   document.getElementById('spine-branch-panel'),
    branchButtons: document.getElementById('spine-branch-buttons'),
    quickReplies:  document.getElementById('spine-quick-replies'),
    alternatives:  document.getElementById('spine-alternatives'),
    leadForm:      document.getElementById('spine-lead-form'),
    leadFormEl:    document.getElementById('spine-lead-form-inner'),
    inputArea:     document.getElementById('spine-input-area'),
    input:         document.getElementById('spine-chat-input'),
    sendBtn:       document.getElementById('spine-chat-send'),
    closeBtn:      document.getElementById('spine-chat-close'),
    minimiseBtn:   document.getElementById('spine-chat-minimise'),
    restartBtn:    document.getElementById('spine-restart-btn'),
    statusDot:     document.getElementById('spine-status-dot'),
    statusLabel:   document.getElementById('spine-status-label'),
    unreadBadge:   document.getElementById('spine-unread-badge'),
    demoLink:      document.getElementById('spine-demo-link'),
    leadSubmit:    document.getElementById('spine-lead-submit'),
    attachBtn:     document.getElementById('spine-attach-btn'),
    fileInput:     document.getElementById('spine-file-input'),
  };

  // ═══════════════════════════════════════════════════════════════════════════
  // BOOT
  // ═══════════════════════════════════════════════════════════════════════════

  function init() {
    if (el.demoLink) el.demoLink.href = V.demoUrl || '#';

    // Render the 4 branch buttons from config
    renderBranchButtons();

    // Bind all events
    el.launcher.addEventListener('click', toggleWidget);
    el.closeBtn.addEventListener('click', () => closeWidget(true));
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

    // File attach — only active in live_agent state
    if (el.fileInput) {
      el.fileInput.addEventListener('change', function () {
        if (el.fileInput.files && el.fileInput.files[0]) {
          uploadVisitorFile(el.fileInput.files[0]);
          el.fileInput.value = '';  // reset so same file can be re-selected
        }
      });
    }

    initHeartbeat();
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
    // Use the CSS class — not Chat.state — to determine current visibility.
    // Chat.state can be 'branch_select' (or any non-idle state) while the
    // widget is minimised, which would cause state === 'idle' to always
    // call closeWidget() and make the widget impossible to reopen.
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

    // If no session yet, start one
    if (!Chat.sessionId) {
      initSession(null);
    } else if (Chat.state === 'idle') {
      // Session restored from storage — state was synced; just reveal the window
      if (Chat.state === 'idle') {
        showBranchPanel();
        Chat.state = 'branch_select';
      }
    }

    scrollToBottom();
  }

  /**
   * @param {boolean} hard  true = reset everything; false = just hide window
   */
  function closeWidget(hard) {
    el.root.classList.remove('spine-chat--open');
    el.launcher.setAttribute('aria-expanded', 'false');
    setTimeout(() => el.widget.setAttribute('hidden', ''), 280);

    if (hard) {
      // Full reset
      Chat.state     = 'idle';
      Chat.sessionId = null;
      Chat.branch    = '';
      Chat.isLiveChat= false;
      Chat.hasGreeted= false;
      sessionStorage.removeItem('spine_session_id');
      el.messages.innerHTML = '';
      resetToLockedInput();
      el.restartBtn && el.restartBtn.setAttribute('hidden', '');
    } else {
      // Just minimise — state preserved
      if (!['idle', 'closed', 'support'].includes(Chat.state)) {
        // keep state as-is; widget just slides down
      }
      Chat.state = Chat.state === 'idle' ? 'idle' : Chat.state;
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

        // Sync UI state
        syncStateFromServer(d.status, d.branch || '');
      } else {
        // Fresh session
        Chat.hasGreeted = true;
        renderMessageText(V.welcomeMessage, 'bot');
        Chat.state = 'branch_select';
        showBranchPanel();
        resetToLockedInput();
      }
    });
  }

  function syncStateFromServer(serverStatus, serverBranch) {
    Chat.branch = serverBranch || '';

    switch (serverStatus) {
      case 'support_redirect':
        Chat.state = 'support';
        freezeInputForSupport();
        hideBranchPanel();
        showRestartBtn();
        break;

      case 'bot':
        if (serverBranch && serverBranch !== 'support') {
          Chat.state = 'product_chat';
          unlockInput(false);
          hideBranchPanel();
          showRestartBtn();
          showBranchQuickReplies(serverBranch);
        } else {
          Chat.state = 'branch_select';
          resetToLockedInput();
          showBranchPanel();
        }
        break;

      case 'pending_agent':
        Chat.state = 'requesting_agent';
        setStatusLabel('Waiting for agent…', 'away');
        unlockInput(true);
        hideBranchPanel();
        startPoll(false);   // slow poll while waiting for agent
        break;

      case 'active':
        Chat.state     = 'live_agent';
        Chat.isLiveChat= true;
        setStatusLabel(Chat.agentName || 'Agent', 'online');
        unlockInput(false);
        hideBranchPanel();
        clearQuickReplies();
        clearAgentOffer();
        showAttachBtn(true);
        startPoll(true);   // fast poll while live
        break;

      case 'lead_captured':
      case 'closed':
        Chat.state = 'closed';
        freezeInputClosed();
        hideBranchPanel();
        break;

      default:
        Chat.state = 'branch_select';
        resetToLockedInput();
        showBranchPanel();
    }
  }

  function restartSession() {
    stopPoll();
    showAttachBtn(false);
    Chat.state     = 'idle';
    Chat.sessionId = null;
    Chat.branch    = '';
    Chat.isLiveChat= false;
    Chat.hasGreeted= false;
    sessionStorage.removeItem('spine_session_id');
    el.messages.innerHTML = '';
    el.restartBtn && el.restartBtn.setAttribute('hidden', '');
    resetToLockedInput();
    clearQuickReplies();
    clearAlternatives();
    initSession(null);
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // BRANCH SELECTION
  // ═══════════════════════════════════════════════════════════════════════════

  function renderBranchButtons() {
    if (!el.branchButtons) return;
    el.branchButtons.innerHTML = '';

    const branches = V.branches || [];
    branches.forEach(function (branch) {
      const btn = document.createElement('button');
      btn.className     = 'spine-chat__branch-btn' + (branch.support ? ' spine-chat__branch-btn--support' : '');
      btn.dataset.branch= branch.id;
      btn.setAttribute('aria-label', branch.label);

      const iconSpan = document.createElement('span');
      iconSpan.className = 'spine-chat__branch-icon';
      iconSpan.innerHTML = BRANCH_ICONS[branch.icon] || BRANCH_ICONS.people;

      const labelSpan = document.createElement('span');
      labelSpan.className   = 'spine-chat__branch-label';
      labelSpan.textContent = branch.label;

      const descSpan = document.createElement('span');
      descSpan.className   = 'spine-chat__branch-desc';
      descSpan.textContent = branch.desc;

      btn.appendChild(iconSpan);
      btn.appendChild(labelSpan);
      btn.appendChild(descSpan);

      btn.addEventListener('click', function () {
        if (Chat.state !== 'branch_select') return;
        selectBranch(branch.id);
      });

      el.branchButtons.appendChild(btn);
    });
  }

  function showBranchPanel() {
    if (el.branchPanel) el.branchPanel.removeAttribute('hidden');
    clearQuickReplies();
    clearAlternatives();
  }

  function hideBranchPanel() {
    if (el.branchPanel) el.branchPanel.setAttribute('hidden', '');
  }

  function selectBranch(branchId) {
    if (!Chat.sessionId) return;

    // Visual feedback: disable all branch buttons while request is in flight
    el.branchButtons.querySelectorAll('.spine-chat__branch-btn').forEach(function (b) {
      b.disabled = true;
      b.classList.add('spine-chat__branch-btn--loading');
    });

    ajax('spine_chat_branch_select', {
      session_id: Chat.sessionId,
      branch    : branchId,
    }).then(function (res) {
      // Re-enable buttons (in case of error)
      el.branchButtons.querySelectorAll('.spine-chat__branch-btn').forEach(function (b) {
        b.disabled = false;
        b.classList.remove('spine-chat__branch-btn--loading');
      });

      if (!res || !res.success) {
        renderMessageText('Something went wrong. Please try again.', 'bot');
        return;
      }

      const d = res.data;
      hideBranchPanel();

      if (d.outcome === 'support') {
        // ── Support branch ────────────────────────────────────────
        Chat.state  = 'support';
        Chat.branch = 'support';

        // Render the server-built message (contains trusted mailto anchor)
        renderMessageHTML(d.message, 'bot');
        freezeInputForSupport();
        showRestartBtn();

      } else if (d.outcome === 'chat_open') {
        // ── Product branch ────────────────────────────────────────
        Chat.state  = 'product_chat';
        Chat.branch = branchId;

        renderMessageText(d.prompt_message, 'bot');
        unlockInput(false);
        showBranchQuickReplies(branchId);
        showRestartBtn();
      }

      scrollToBottom();
    });
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // INPUT STATE
  // ═══════════════════════════════════════════════════════════════════════════

  /**
   * Unlock the text input for product chat.
   * @param {boolean} keepDisabled  true = unlocked visually but still disabled (waiting for agent)
   */
  function unlockInput(keepDisabled) {
    el.inputArea.classList.remove('spine-chat__input-area--locked', 'spine-chat__input-area--frozen');
    el.inputArea.removeAttribute('hidden');

    if (keepDisabled) {
      el.input.disabled     = true;
      el.input.placeholder  = 'Waiting for an agent to connect…';
      el.sendBtn.disabled   = true;
    } else {
      el.input.disabled     = false;
      el.input.placeholder  = 'Type your message…';
      el.sendBtn.disabled   = el.input.value.trim().length === 0;
      el.input.focus();
    }
  }

  function resetToLockedInput() {
    el.inputArea.classList.remove('spine-chat__input-area--frozen');
    el.inputArea.classList.add('spine-chat__input-area--locked');
    el.inputArea.removeAttribute('hidden');
    el.input.disabled    = true;
    el.input.value       = '';
    el.input.placeholder = 'Select a topic above to begin…';
    el.sendBtn.disabled  = true;
  }

  /**
   * Freeze input for Support branch — permanently disabled, frozen appearance.
   * The user is directed to email. No further messages can be typed.
   */
  function freezeInputForSupport() {
    el.inputArea.classList.remove('spine-chat__input-area--locked');
    el.inputArea.classList.add('spine-chat__input-area--frozen');
    el.inputArea.removeAttribute('hidden');
    el.input.disabled    = true;
    el.input.value       = '';
    el.input.placeholder = 'Chat ended — please use the email link above.';
    el.sendBtn.disabled  = true;
    clearQuickReplies();
    clearAlternatives();
  }

  function freezeInputClosed() {
    el.inputArea.classList.remove('spine-chat__input-area--locked');
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

  // Keywords that should bypass the KB and directly show the agent/demo offer
  var AGENT_TRIGGERS = [
    'live agent','connect agent','connect with agent','talk to agent','talk to a agent',
    'speak to agent','speak to a agent','speak with agent','connect live','human agent',
    'real agent','real person','human support','talk to human','talk to someone',
    'connect with live','reach an agent','get an agent','agent please','need an agent',
    'want agent','chat with agent','chat to agent','agent connect','i want to connect',
    'connect me','connect now',
  ];
  var DEMO_TRIGGERS = [
    'book demo','book a demo','schedule demo','request demo','demo please',
    'i want a demo','want to see demo','product demo','schedule a meeting',
    'book meeting','book a meeting','arrange demo','demo request',
  ];

  function detectIntent(text) {
    var lower = text.toLowerCase().trim();
    for (var i = 0; i < AGENT_TRIGGERS.length; i++) {
      if (lower.includes(AGENT_TRIGGERS[i])) return 'agent';
    }
    for (var j = 0; j < DEMO_TRIGGERS.length; j++) {
      if (lower.includes(DEMO_TRIGGERS[j])) return 'demo';
    }
    return null;
  }

  function handleSend() {
    const text = el.input.value.trim();
    if (!text || !Chat.sessionId) return;
    if (Chat.state === 'support' || Chat.state === 'closed') return;

    el.input.value       = '';
    el.input.style.height= '';
    el.sendBtn.disabled  = true;

    clearQuickReplies();
    clearAlternatives();
    renderMessageText(text, 'user');

    // Short-circuit: if message is clearly an agent/demo request, skip the KB entirely
    if (!Chat.isLiveChat && Chat.state !== 'requesting_agent' && Chat.state !== 'live_agent') {
      var intent = detectIntent(text);
      if (intent === 'agent') {
        el.sendBtn.disabled = false;
        requestAgent();
        return;
      }
      if (intent === 'demo') {
        el.sendBtn.disabled = false;
        showLeadForm('demo_request');
        return;
      }
    }

    showTyping();

    if (Chat.isLiveChat) {
      // In live-agent mode: send as user message to the session transcript
      ajax('spine_chat_message', {
        session_id: Chat.sessionId,
        message   : text,
        branch    : Chat.branch,
      }).then(function () { hideTyping(); });
    } else {
      // Bot mode: scoped KB search
      ajax('spine_chat_message', {
        session_id: Chat.sessionId,
        message   : text,
        branch    : Chat.branch,
      }).then(function (res) {
        hideTyping();
        if (res && res.success) {
          handleBotResponse(res.data);
        } else {
          renderMessageText('Something went wrong. Please try again.', 'bot');
        }
      });
    }
  }

  function handleBotResponse(data) {
    Chat.lastMsgTime = new Date().toISOString().slice(0, 19).replace('T', ' ');

    switch (data.type) {
      case 'answer':
        renderMessageHTML(data.response, 'bot');
        showBranchQuickReplies(Chat.branch);
        break;

      case 'alternatives':
        renderMessageText(data.response || 'I found a few related topics:', 'bot');
        showAlternatives(data.alternatives || []);
        break;

      case 'live_agent':
        // Server confirmed the session is already live — update client state silently
        if (!Chat.isLiveChat) {
          Chat.isLiveChat = true;
          Chat.state      = 'live_agent';
          if (data.agent_name) Chat.agentName = data.agent_name;
          setStatusLabel(Chat.agentName || 'Agent', 'online');
          unlockInput(false);
          clearQuickReplies();
          clearAlternatives();
          clearAgentOffer();
          showAttachBtn(true);
          startPoll(true);
        }
        break;

      case 'no_match':
      default:
        renderMessageText(data.response, 'bot');
        showAgentOrLeadOffer();
        break;
    }

    scrollToBottom();
  }

  function clearAgentOffer() {
    if (el.messages) {
      el.messages.querySelectorAll('.spine-chat__agent-offer').forEach(function(n) { n.remove(); });
    }
  }

  function showAttachBtn(show) {
    if (el.attachBtn) el.attachBtn.hidden = !show;
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // QUICK REPLIES
  // ═══════════════════════════════════════════════════════════════════════════

  function showBranchQuickReplies(branchId) {
    const replies = (V.branchQuickReplies || {})[branchId] || [];
    showQuickReplies(replies);
  }

  function showQuickReplies(replies) {
    clearQuickReplies();
    if (!replies.length) return;

    el.quickReplies.removeAttribute('hidden');
    replies.forEach(function (label) {
      const btn        = document.createElement('button');
      btn.className    = 'spine-quick-reply';
      btn.textContent  = label;
      btn.addEventListener('click', function () {
        clearQuickReplies();
        handleQuickReply(label);
      });
      el.quickReplies.appendChild(btn);
    });
  }

  function handleQuickReply(label) {
    // Ignore quick reply button clicks once a live agent is present
    if (Chat.isLiveChat || Chat.state === 'live_agent' || Chat.state === 'requesting_agent') return;

    const lower = label.toLowerCase();
    if (lower.includes('agent') || lower.includes('talk to') || lower.includes('specialist')) {
      requestAgent();
    } else if (
      lower.includes('demo') || lower.includes('book') ||
      lower.includes('capture') || lower.includes('details') ||
      lower.includes('follow') || lower.includes('leave') || lower.includes('enquir')
    ) {
      showLeadForm('demo_request');
    } else {
      el.input.value  = label;
      el.sendBtn.disabled = false;
      handleSend();
    }
  }

  function clearQuickReplies() {
    if (el.quickReplies) {
      el.quickReplies.innerHTML = '';
      el.quickReplies.setAttribute('hidden', '');
    }
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // ALTERNATIVES
  // ═══════════════════════════════════════════════════════════════════════════

  function showAlternatives(alts) {
    el.alternatives.removeAttribute('hidden');
    el.alternatives.innerHTML =
      '<span class="spine-alt-title">Related topics — click to explore:</span>';

    alts.forEach(function (alt) {
      const btn = document.createElement('button');
      btn.className = 'spine-alt-item';
      btn.innerHTML =
        '<span class="spine-alt-item__label">' + escHtml(alt.label) + '</span>' +
        '<span class="spine-alt-item__preview">' + escHtml(alt.preview || '') + '</span>';
      btn.addEventListener('click', function () {
        clearAlternatives();
        selectModule(alt.module_id, alt.label);
      });
      el.alternatives.appendChild(btn);
    });

    // Always offer agent as escape hatch
    const agentBtn       = document.createElement('button');
    agentBtn.className   = 'spine-alt-item spine-alt-item--agent';
    agentBtn.textContent = 'None of these — Talk to an Agent';
    agentBtn.addEventListener('click', function () { clearAlternatives(); requestAgent(); });
    el.alternatives.appendChild(agentBtn);
  }

  function clearAlternatives() {
    if (el.alternatives) {
      el.alternatives.innerHTML = '';
      el.alternatives.setAttribute('hidden', '');
    }
  }

  function selectModule(moduleId, label) {
    renderMessageText('Tell me more about ' + label, 'user');
    showTyping();

    ajax('spine_chat_module_select', {
      session_id: Chat.sessionId,
      module_id : moduleId,
    }).then(function (res) {
      hideTyping();
      if (res && res.success) {
        renderMessageHTML(res.data.response, 'bot');
        showBranchQuickReplies(Chat.branch);
        scrollToBottom();
      }
    });
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // AGENT REQUEST
  // ═══════════════════════════════════════════════════════════════════════════

  function showAgentOrLeadOffer() {
    const wrap = document.createElement('div');
    wrap.className = 'spine-chat__agent-offer';

    const agentBtn = document.createElement('button');
    agentBtn.className   = 'spine-chat__btn spine-chat__btn--primary spine-chat__btn--sm';
    agentBtn.textContent = 'Talk to an Agent';
    agentBtn.addEventListener('click', requestAgent);

    const leadBtn = document.createElement('button');
    leadBtn.className   = 'spine-chat__btn spine-chat__btn--secondary spine-chat__btn--sm';
    leadBtn.textContent = 'Book a Demo';
    leadBtn.addEventListener('click', function () { showLeadForm('demo_request'); });

    wrap.appendChild(agentBtn);
    wrap.appendChild(leadBtn);
    el.messages.appendChild(wrap);
    scrollToBottom();
  }

  function requestAgent() {
    if (!Chat.sessionId) return;
    if (Chat.state === 'requesting_agent' || Chat.state === 'live_agent') return;

    Chat.state = 'requesting_agent';
    clearQuickReplies();
    clearAlternatives();
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
        startPoll(false);   // slow poll while waiting for agent

      } else if (d.outcome === 'active') {
        Chat.state     = 'live_agent';
        Chat.isLiveChat= true;
        Chat.agentName = d.agent_name || 'Agent';
        renderMessageText(d.message || 'An agent has joined.', 'bot');
        setStatusLabel(Chat.agentName, 'online');
        unlockInput(false);
        clearQuickReplies();
        clearAlternatives();
        clearAgentOffer();
        showAttachBtn(true);
        startPoll(true);   // fast poll while live

      } else {
        // offline
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
    clearQuickReplies();
    clearAlternatives();

    const typeInput = el.leadFormEl && el.leadFormEl.querySelector('input[name="type"]');
    if (typeInput) typeInput.value = type || 'demo_request';
  }

  function handleLeadSubmit(e) {
    e.preventDefault();
    clearLeadErrors();

    const fd = new FormData(el.leadFormEl);
    const name    = (fd.get('name')    || '').trim();
    const email   = (fd.get('email')   || '').trim();
    const phone   = (fd.get('phone')   || '').trim();
    const company = (fd.get('company') || '').trim();
    const msg     = (fd.get('message') || '').trim();
    const type    = fd.get('type') || 'demo_request';

    // RFC format check via the browser's native validity API
    var emailInput = document.getElementById('spine-lead-email');
    var emailValid = email && emailInput
        ? emailInput.validity.valid
        : /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);

    // Block common personal / free-mail domains — work email only
    var PERSONAL_DOMAINS = [
      'gmail.com','googlemail.com','yahoo.com','yahoo.co.in','yahoo.co.uk',
      'yahoo.com.au','ymail.com','hotmail.com','hotmail.co.uk','hotmail.in',
      'outlook.com','outlook.in','live.com','msn.com','icloud.com','me.com',
      'mac.com','aol.com','mail.com','zoho.com','protonmail.com','proton.me',
      'tutanota.com','rediffmail.com','rocketmail.com','inbox.com',
      'fastmail.com','gmx.com','gmx.net','yandex.com','yandex.ru',
      'tempmail.com','guerrillamail.com','10minutemail.com',
    ];
    var emailDomain = email.split('@')[1] ? email.split('@')[1].toLowerCase() : '';
    var isPersonalDomain = PERSONAL_DOMAINS.indexOf(emailDomain) !== -1;

    const errors = {};
    if (!name)        errors.name    = 'Name is required.';
    if (!email || !emailValid) {
      errors.email = 'A valid email address is required.';
    } else if (isPersonalDomain) {
      errors.email = 'Please use your work email address (personal emails like Gmail are not accepted).';
    }
    if (!phone)       errors.phone   = 'Phone is required.';
    if (!company)     errors.company = 'Company name is required.';

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
        renderMessageText(res.data.message || 'Thank you! We\'ll be in touch shortly.', 'bot');
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

  /**
   * Render a text-only message. textContent used — safe against XSS.
   * Use this for all user-generated and most bot text.
   */
  function renderMessageText(text, role, agentName) {
    if (!text || role === 'system') return;

    const wrap   = buildWrap(role, agentName);
    const bubble = wrap.querySelector('.spine-msg__bubble');

    // **bold** and bullet list rendering on safe text only
    bubble.innerHTML = formatMarkdown(escHtml(text));

    el.messages.appendChild(wrap);
    scrollToBottom();
    updateUnreadBadge(role);

    Chat.lastMsgTime = new Date().toISOString().slice(0, 19).replace('T', ' ');
  }

  /**
   * Render a server-generated HTML message (e.g., mailto link from support
   * branch, "Learn more" anchor from format_answer()).
   * Content is already sanitised server-side with esc_html() / format_answer().
   */
  function renderMessageHTML(html, role, agentName) {
    if (!html || role === 'system') return;

    const wrap   = buildWrap(role, agentName);
    const bubble = wrap.querySelector('.spine-msg__bubble');

    // Trusted server HTML — convert **bold** markers
    bubble.innerHTML = formatMarkdown(html);

    el.messages.appendChild(wrap);
    scrollToBottom();
    updateUnreadBadge(role);

    Chat.lastMsgTime = new Date().toISOString().slice(0, 19).replace('T', ' ');
  }

  /**
   * Alias: render message choosing text vs HTML based on role context.
   * Used during transcript restore where we don't know how content was generated.
   */
  function renderMessageRaw(content, role, agentName, msgObj) {
    if (role === 'system') return;
    // File attachment
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
      .then(function(r) { return r.json(); })
      .then(function(res) {
        hideTyping();
        if (res && res.success) {
          // Replace the "uploading…" placeholder with the real attachment bubble
          var last = el.messages.lastElementChild;
          if (last) last.remove();
          renderFileAttachment({
            file_url:  res.data.file_url,
            file_name: res.data.file_name,
            file_type: res.data.file_type,
          }, 'user', null);
        } else {
          var last2 = el.messages.lastElementChild;
          if (last2) last2.remove();
          renderMessageText('Upload failed. Please try again.', 'bot');
        }
      })
      .catch(function() {
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
    out = out.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    out = out.replace(/^•\s(.+)$/gm, '<li>$1</li>');
    out = out.replace(/(<li>[\s\S]+?<\/li>)/g, '<ul>$1</ul>');
    out = out.replace(/\n/g, '<br>');
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
  // VISITOR POLLING  (setInterval + spine_chat_poll via root-relative AJAX URL)
  // Replaces WordPress Heartbeat which used heartbeatSettings.url from
  // admin_url() — that URL could point to the wrong domain when siteurl is
  // misconfigured.  Our own polling always uses _ajaxUrl (relative path).
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
    if (Chat.state === 'support' || Chat.state === 'closed' || Chat.state === 'idle') {
      stopPoll();
      return;
    }

    ajax('spine_chat_poll', { session_id: Chat.sessionId, since: Chat.lastMsgTime })
      .then(function (res) {
        if (!res || !res.success) return;
        var d = res.data;

        // Render any new agent messages
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

        // Transition: pending → agent accepted → live_agent
        if (d.status === 'active' && !Chat.isLiveChat) {
          Chat.isLiveChat = true;
          Chat.state      = 'live_agent';
          var ai          = d.agent_info || {};
          Chat.agentName  = ai.name || Chat.agentName || 'Agent';
          setStatusLabel(Chat.agentName, 'online');
          unlockInput(false);
          clearQuickReplies();
          clearAlternatives();
          clearAgentOffer();
          showAttachBtn(true);
          startPoll(true);   // switch to fast polling while live
        }

        // Transition: agent closed the session
        if (d.status === 'closed' && Chat.isLiveChat) {
          Chat.isLiveChat = false;
          Chat.state      = 'closed';
          setStatusLabel('Chat ended', 'offline');
          freezeInputClosed();
          stopPoll();
        }
      });
  }

  function initHeartbeat() {
    // Visitor-side polling is now handled by startPoll() / doPoll().
    // This function is intentionally empty; it is kept so nothing breaks if
    // called from a cached version of the page.
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // VISIBILITY / TAB FOCUS TRACKING
  // ═══════════════════════════════════════════════════════════════════════════

  function initVisibilityTracking() {
    document.addEventListener('visibilitychange', function () {
      Chat.focused = !document.hidden;
      // When the tab comes back into focus and we're in an active poll state,
      // restart polling at the appropriate speed so we catch up immediately.
      if (Chat.focused && _pollTimer !== null) {
        startPoll(Chat.isLiveChat);
      }
    });
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // AJAX HELPER
  // ═══════════════════════════════════════════════════════════════════════════

  // PHP now passes a root-relative path (e.g. '/wp-admin/admin-ajax.php')
  // via wp_parse_url(), so there is no domain component to mismatch.
  // We still extract the pathname here as a safety net in case an older
  // cached version of spineChatVars contains an absolute URL.
  var _ajaxUrl = (function () {
    var url = V.ajaxUrl || '/wp-admin/admin-ajax.php';
    // Already relative (starts with '/') — use as-is.
    if (url.charAt(0) === '/') { return url; }
    // Absolute URL (old cached value) — strip domain, keep path only.
    try {
      return new URL(url).pathname;
    } catch (e) {}
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
      // Log to console so the raw server response is visible in DevTools → Network tab.
      if (typeof console !== 'undefined' && console.error) {
        console.error(
          '[SpineChat] AJAX error for action "' + action + '"',
          { status: jqXHR.status, responseText: jqXHR.responseText }
        );
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
