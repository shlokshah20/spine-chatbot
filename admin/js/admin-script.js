/**
 * Spine HR Chatbot — Agent Dashboard Script
 *
 * Handles:
 *  - Agent presence ping via WordPress Heartbeat (focused = 3 s, blurred = 15 s)
 *  - Chat session list rendering and selection
 *  - Live conversation — agent sends messages, receives user replies via Heartbeat
 *  - Super-admin global session + agent monitoring
 *  - WordPress Media Uploader for custom icon (settings page)
 *
 * @version 1.0.0
 */
/* global spineAdminVars, wp */
(function ($) {
  'use strict';

  if (typeof spineAdminVars === 'undefined') return;

  // ── State ──────────────────────────────────────────────────────────────────
  const State = {
    isOnline:        false,
    activeSessionId: null,
    lastMsgTime:     '',
    focused:         !document.hidden,
    filterStatus:    '',
    sessions:        {},   // sessionId → session object
  };

  // ──────────────────────────────────────────────────────────────────────────
  // INIT
  // ──────────────────────────────────────────────────────────────────────────
  function init() {
    // Only run on dashboard page
    if (document.getElementById('spine-session-list')) {
      initDashboard();
    }

    // Always init media uploader if on settings page
    if (document.getElementById('spine-upload-icon')) {
      initMediaUploader();
    }
  }

  // ──────────────────────────────────────────────────────────────────────────
  // DASHBOARD
  // ──────────────────────────────────────────────────────────────────────────
  function initDashboard() {
    bindPresenceToggle();
    bindSendReply();
    bindCloseChat();
    bindRefreshList();
    bindFilterStatus();
    bindVisibilityTracking();
    bindAgentFileUpload();
    initHeartbeat();

    // Initial load of sessions
    if (spineAdminVars.isSuperAdmin) {
      loadAllSessions();
      loadAgentList();
    } else {
      loadAgentSessions();
    }
  }

  // ── Presence ────────────────────────────────────────────────────────────────
  function bindPresenceToggle() {
    const goOnlineBtn  = document.getElementById('spine-go-online');
    const goOfflineBtn = document.getElementById('spine-go-offline');
    if (!goOnlineBtn) return;

    goOnlineBtn.addEventListener('click', () => {
      State.isOnline = true;
      pingAgent();
      updatePresenceUI(true);
    });

    goOfflineBtn.addEventListener('click', () => {
      State.isOnline = false;
      updatePresenceUI(false);
    });
  }

  function updatePresenceUI(online) {
    const dot    = document.getElementById('presence-dot');
    const label  = document.getElementById('presence-label');
    const goOn   = document.getElementById('spine-go-online');
    const goOff  = document.getElementById('spine-go-offline');
    if (!dot) return;

    dot.className   = 'spine-presence-dot' + (online ? ' spine-presence-dot--online' : '');
    label.textContent = online ? 'Online' : 'Offline';
    goOn.hidden  = online;
    goOff.hidden = !online;
  }

  // ── Heartbeat (presence ping + incoming notifications only) ──────────────────
  function initHeartbeat() {
    if (typeof wp === 'undefined' || !wp.heartbeat) return;

    $(document).on('heartbeat-send.spineAdmin', function (e, data) {
      if (!spineAdminVars.isAgent && !spineAdminVars.isSuperAdmin) return;
      data.spine_agent = {
        nonce:   spineAdminVars.nonce,
        focused: State.focused,
        since:   State.lastMsgTime,
      };
    });

    $(document).on('heartbeat-tick.spineAdmin', function (e, data) {
      if (!data.spine_agent) return;
      handleHeartbeatResponse(data.spine_agent);
    });

    wp.heartbeat.interval(State.focused
      ? spineAdminVars.heartbeatFast
      : spineAdminVars.heartbeatSlow);
  }

  function handleHeartbeatResponse(payload) {
    if (payload.error) return;

    if (payload.server_time) State.lastMsgTime = payload.server_time;

    if (payload.agents_online !== undefined) {
      const el = document.getElementById('stat-online-agents');
      if (el) el.textContent = payload.agents_online;
    }

    if (payload.incoming_chats && payload.incoming_chats.length > 0) {
      payload.incoming_chats.forEach(chat => {
        showIncomingNotification(chat);
        addOrUpdateSession(chat);
      });
    }
  }

  // ── setInterval polling for messages in the active session ───────────────────
  const POLL_MS     = 2500;
  let   _pollTimer  = null;

  function startAgentPoll(sessionId) {
    stopAgentPoll();
    _pollTimer = setInterval(() => pollSession(sessionId), POLL_MS);
  }

  function stopAgentPoll() {
    if (_pollTimer !== null) {
      clearInterval(_pollTimer);
      _pollTimer = null;
    }
  }

  async function pollSession(sessionId) {
    if (!sessionId) return;
    const res = await ajaxReq('spine_agent_session_poll', {
      session_id: sessionId,
      since:      State.lastMsgTime,
    });
    if (!res || !res.success) return;
    const d = res.data;

    if (d.messages && d.messages.length > 0) {
      d.messages.forEach(msg => {
        if (msg.role === 'user') {
          appendMessageBubble(msg.content, 'user', null, msg);
          highlightSessionItem(sessionId);
        }
      });
    }
    if (d.server_time) State.lastMsgTime = d.server_time;
  }

  // ── Ping ─────────────────────────────────────────────────────────────────────
  async function pingAgent() {
    return ajaxReq('spine_agent_ping', {});
  }

  // ── Sessions list ─────────────────────────────────────────────────────────────
  async function loadAgentSessions() {
    showListLoading();
    const res = await ajaxReq('spine_agent_get_sessions', {});
    hideListLoading();

    if (res && res.success && res.data.sessions) {
      res.data.sessions.forEach(s => addOrUpdateSession(s));
      renderSessionList();
    } else {
      showListEmpty();
    }
  }

  async function loadAllSessions() {
    showListLoading();
    const res = await ajaxReq('spine_admin_all_sessions', { nonce: spineAdminVars.adminNonce });
    hideListLoading();

    if (res && res.success) {
      const sessions = res.data.sessions || [];
      sessions.forEach(s => addOrUpdateSession(s));
      renderSessionList();

      // Update stats
      const counts = res.data.counts || {};
      setStatEl('stat-active',  counts.active  || 0);
      setStatEl('stat-pending', counts.pending_agent || 0);
      setStatEl('stat-leads',   counts.lead_captured || 0);
      setStatEl('stat-bot',     counts.bot || 0);
    } else {
      showListEmpty();
    }
  }

  async function loadAgentList() {
    const res = await ajaxReq('spine_admin_agent_list', { nonce: spineAdminVars.adminNonce });
    if (!res || !res.success) return;

    const container = document.getElementById('spine-agent-list');
    if (!container) return;

    const agents = res.data.agents || [];
    setStatEl('stat-online-agents', agents.filter(a => a.status === 'online').length);

    container.innerHTML = '';
    agents.forEach(agent => {
      const chip = document.createElement('div');
      chip.className = 'spine-agent-chip';
      const statusColor = { online: '#4ade80', away: '#facc15', offline: '#9ca3af' }[agent.status] || '#9ca3af';
      chip.innerHTML = `
        <img class="spine-agent-chip__avatar" src="${escAttr(agent.avatar_url)}" alt="">
        <div class="spine-agent-chip__info">
          <div class="spine-agent-chip__name">${escHtml(agent.name)}</div>
          <div class="spine-agent-chip__meta">${escHtml(agent.active_chats)} chats &bull; ${escHtml(agent.last_ping)}</div>
        </div>
        <span class="spine-badge" style="background:${statusColor}15;color:${statusColor};">${escHtml(ucfirst(agent.status))}</span>
      `;
      container.appendChild(chip);
    });
  }

  function addOrUpdateSession(s) {
    State.sessions[s.session_id] = s;
  }

  function renderSessionList() {
    const ul = document.getElementById('spine-session-items');
    if (!ul) return;

    const sessions = Object.values(State.sessions)
      .filter(s => !State.filterStatus || s.status === State.filterStatus);

    ul.innerHTML = '';

    if (sessions.length === 0) {
      showListEmpty();
      return;
    }

    document.getElementById('spine-list-empty').hidden = true;

    sessions.forEach(s => {
      const isActive = s.session_id === State.activeSessionId;
      const li = document.createElement('li');
      li.className   = 'spine-session-item' + (isActive ? ' spine-session-item--active' : '');
      li.dataset.sid = s.session_id;
      li.innerHTML   = `
        <div class="spine-session-item__header">
          <span class="spine-session-item__name">${escHtml(s.user_name || 'Visitor')}</span>
          <span class="spine-session-item__time">${relativeTime(s.updated_at)}</span>
        </div>
        ${s.user_company ? `<div class="spine-session-item__company">${escHtml(s.user_company)}</div>` : ''}
        <div class="spine-session-item__preview">${escHtml(s.last_message?.content || '')}</div>
      `;
      li.addEventListener('click', () => openSession(s));
      ul.appendChild(li);
    });
  }

  function highlightSessionItem(sid) {
    const item = document.querySelector(`.spine-session-item[data-sid="${CSS.escape(sid)}"]`);
    if (item) {
      item.style.borderColor = 'var(--sa-accent)';
      setTimeout(() => { item.style.borderColor = ''; }, 3000);
    }
  }

  // ── Open a session in the main panel ─────────────────────────────────────────
  async function openSession(s) {
    stopAgentPoll();
    State.activeSessionId = s.session_id;
    State.lastMsgTime     = '';

    // Update sidebar selection
    document.querySelectorAll('.spine-session-item').forEach(el => {
      el.classList.toggle('spine-session-item--active', el.dataset.sid === s.session_id);
    });

    // Switch panels
    document.getElementById('spine-main-empty').hidden = true;
    document.getElementById('spine-chat-view').hidden  = false;

    // User info
    document.getElementById('chat-user-name').textContent = s.user_name || 'Visitor';
    document.getElementById('chat-user-meta').textContent =
      [s.user_email, s.user_company].filter(Boolean).join(' • ');

    // Status badge
    const badge = document.getElementById('chat-status-badge');
    if (badge) {
      badge.textContent = ucfirst(s.status);
      badge.style.background = s.status === 'active' ? '#dcfce7' : '#fef9c3';
      badge.style.color      = s.status === 'active' ? '#166534' : '#854d0e';
    }

    // Load transcript
    const res = await ajaxReq('spine_agent_get_messages', {
      session_id: s.session_id,
      since: '',
    });

    const msgArea = document.getElementById('chat-view-messages');
    msgArea.innerHTML = '';

    if (res && res.success && res.data.messages) {
      res.data.messages.forEach(msg => {
        appendMessageBubble(msg.content, msg.role, msg.agent_name, msg);
      });
      const msgs = res.data.messages;
      if (msgs.length > 0 && msgs[msgs.length - 1].timestamp) {
        State.lastMsgTime = msgs[msgs.length - 1].timestamp;
      }
    }

    // Show accept panel if it's pending
    const acceptPanel = document.getElementById('spine-incoming-accept');
    if (acceptPanel) {
      if (s.status === 'pending_agent' && !spineAdminVars.isSuperAdmin) {
        acceptPanel.hidden = false;
        document.getElementById('accept-user-name').textContent = s.user_name || 'Visitor';
        document.getElementById('spine-accept-btn').onclick = () => acceptChat(s.session_id);
        document.getElementById('spine-decline-btn').onclick = () => { acceptPanel.hidden = true; };
      } else {
        acceptPanel.hidden = true;
      }
    }

    // Show input area only for active sessions
    const inputArea = document.getElementById('chat-view-input-area');
    if (inputArea) inputArea.hidden = s.status !== 'active';

    // Start live polling for new visitor messages
    if (s.status === 'active' || s.status === 'pending_agent') {
      startAgentPoll(s.session_id);
    }
  }

  // ── Accept chat ──────────────────────────────────────────────────────────────
  async function acceptChat(sessionId) {
    const res = await ajaxReq('spine_agent_accept', { session_id: sessionId });
    if (res && res.success) {
      document.getElementById('spine-incoming-accept').hidden = true;
      document.getElementById('chat-view-input-area').hidden  = false;

      const s = res.data;
      if (s.transcript) {
        const msgArea = document.getElementById('chat-view-messages');
        msgArea.innerHTML = '';
        s.transcript.forEach(msg => appendMessageBubble(msg.content, msg.role, msg.agent_name, msg));
      }

      if (State.sessions[sessionId]) {
        State.sessions[sessionId].status = 'active';
      }

      renderSessionList();
      startAgentPoll(sessionId);
    }
  }

  // ── Send reply ───────────────────────────────────────────────────────────────
  function bindSendReply() {
    const sendBtn   = document.getElementById('agent-reply-send');
    const inputEl   = document.getElementById('agent-reply-input');
    if (!sendBtn || !inputEl) return;

    sendBtn.addEventListener('click', () => sendReply(inputEl));

    inputEl.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendReply(inputEl);
      }
    });
  }

  async function sendReply(inputEl) {
    const text = inputEl.value.trim();
    if (!text || !State.activeSessionId) return;

    inputEl.value = '';

    const me = wp.getCurrentUser ? wp.getCurrentUser().data : null;
    appendMessageBubble(text, 'agent', 'You');

    await ajaxReq('spine_agent_message', {
      session_id: State.activeSessionId,
      message:    text,
    });
  }

  // ── Close chat ───────────────────────────────────────────────────────────────
  function bindCloseChat() {
    const btn = document.getElementById('spine-close-chat');
    if (!btn) return;
    btn.addEventListener('click', () => {
      if (!State.activeSessionId) return;
      if (!confirm('End this chat session?')) return;
      closeChat(State.activeSessionId);
    });
  }

  async function closeChat(sessionId) {
    const res = await ajaxReq('spine_agent_close', { session_id: sessionId });
    if (res && res.success) {
      stopAgentPoll();
      document.getElementById('chat-view-input-area').hidden = true;
      appendMessageBubble('You closed this chat.', 'system');

      if (State.sessions[sessionId]) State.sessions[sessionId].status = 'closed';
      State.activeSessionId = null;

      renderSessionList();
    }
  }

  // ── Incoming notification ─────────────────────────────────────────────────────
  function showIncomingNotification(chat) {
    const container = document.getElementById('spine-incoming-chats');
    const list      = document.getElementById('spine-incoming-list');
    if (!container || !list) return;

    container.hidden = false;

    const existing = list.querySelector(`[data-sid="${CSS.escape(chat.session_id)}"]`);
    if (existing) return; // already shown

    const li = document.createElement('li');
    li.className    = 'spine-incoming-item';
    li.dataset.sid  = chat.session_id;
    li.innerHTML    = `
      <div>
        <strong>${escHtml(chat.user_name || 'Visitor')}</strong>
        ${chat.user_company ? `<small> &bull; ${escHtml(chat.user_company)}</small>` : ''}
      </div>
      <button class="spine-btn spine-btn--primary spine-btn--sm"
              data-accept="${escAttr(chat.session_id)}">Accept</button>
    `;

    li.querySelector('[data-accept]').addEventListener('click', async () => {
      const sid = chat.session_id;
      await acceptChat(sid);
      li.remove();
      if (!list.children.length) container.hidden = true;
      addOrUpdateSession(Object.assign({}, chat, { status: 'active' }));
      renderSessionList();
      openSession(State.sessions[sid]);
    });

    list.prepend(li);

    // Play notification sound if available
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const osc = ctx.createOscillator();
      osc.connect(ctx.destination);
      osc.frequency.value = 880;
      osc.start();
      setTimeout(() => osc.stop(), 180);
    } catch (_) {}
  }

  // ── Append message bubble (admin view) ────────────────────────────────────────
  function appendMessageBubble(content, role, agentName, msgObj) {
    const msgArea = document.getElementById('chat-view-messages');
    if (!msgArea) return;

    const wrap = document.createElement('div');
    wrap.className = 'spine-msg spine-msg--' + (role === 'system' ? 'system' : role);

    const bubble = document.createElement('div');
    bubble.className = 'spine-msg__bubble';

    let html = '';
    if (role === 'agent' && agentName) {
      html += `<span class="spine-msg__agent-name">${escHtml(agentName)}</span>`;
    }

    // File attachment rendering
    const fileUrl  = msgObj && msgObj.file_url;
    const fileName = msgObj && msgObj.file_name;
    const fileType = msgObj && msgObj.file_type;

    if (fileUrl) {
      if (fileType && fileType.startsWith('image/')) {
        html += `<div class="spine-msg__file-wrap">
          <a href="${escAttr(fileUrl)}" target="_blank" rel="noopener">
            <img class="spine-msg__file-img" src="${escAttr(fileUrl)}" alt="${escAttr(fileName || 'image')}">
          </a>
        </div>`;
      } else {
        html += `<div class="spine-msg__file-wrap">
          <a class="spine-msg__file-link" href="${escAttr(fileUrl)}" target="_blank" rel="noopener">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            ${escHtml(fileName || 'Attachment')}
          </a>
        </div>`;
      }
    } else if (content) {
      html += escHtml(content).replace(/\n/g, '<br>');
    }

    bubble.innerHTML = html;

    wrap.appendChild(bubble);
    msgArea.appendChild(wrap);
    msgArea.scrollTop = msgArea.scrollHeight;
  }

  // ── Agent file upload ─────────────────────────────────────────────────────────
  function bindAgentFileUpload() {
    const fileInput = document.getElementById('spine-agent-file-input');
    if (!fileInput) return;

    fileInput.addEventListener('change', async () => {
      const file = fileInput.files[0];
      fileInput.value = '';
      if (!file || !State.activeSessionId) return;

      const allowed = ['image/jpeg', 'image/png', 'application/pdf'];
      if (!allowed.includes(file.type)) {
        alert('Only JPG, PNG, and PDF files are allowed.');
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        alert('File must be under 5 MB.');
        return;
      }

      const fd = new FormData();
      fd.append('action',     'spine_agent_upload');
      fd.append('nonce',      spineAdminVars.nonce);
      fd.append('session_id', State.activeSessionId);
      fd.append('file',       file);

      try {
        const resp = await fetch(_adminAjaxUrl, { method: 'POST', body: fd });
        const data = await resp.json();
        if (data.success) {
          appendMessageBubble(
            '[Attached: ' + data.data.file_name + ']',
            'agent',
            'You',
            { file_url: data.data.file_url, file_name: data.data.file_name, file_type: data.data.file_type }
          );
        } else {
          alert(data.data || 'Upload failed.');
        }
      } catch (err) {
        alert('Upload failed. Please try again.');
      }
    });
  }

  // ── Refresh & filter ─────────────────────────────────────────────────────────
  function bindRefreshList() {
    const btn = document.getElementById('spine-refresh-list');
    if (!btn) return;
    btn.addEventListener('click', () => {
      State.sessions = {};
      if (spineAdminVars.isSuperAdmin) { loadAllSessions(); loadAgentList(); }
      else loadAgentSessions();
    });
  }

  function bindFilterStatus() {
    const sel = document.getElementById('spine-filter-status');
    if (!sel) return;
    sel.addEventListener('change', () => {
      State.filterStatus = sel.value;
      renderSessionList();
    });
  }

  // ── Visibility tracking ───────────────────────────────────────────────────────
  function bindVisibilityTracking() {
    document.addEventListener('visibilitychange', () => {
      State.focused = !document.hidden;
      if (typeof wp !== 'undefined' && wp.heartbeat) {
        wp.heartbeat.interval(State.focused
          ? spineAdminVars.heartbeatFast
          : spineAdminVars.heartbeatSlow);
      }
      // When tab refocuses, force a fresh data load
      if (State.focused && spineAdminVars.isSuperAdmin) {
        loadAgentList();
      }
    });
  }

  // ── Media uploader (settings page) ───────────────────────────────────────────
  function initMediaUploader() {
    const uploadBtn  = document.getElementById('spine-upload-icon');
    const removeBtn  = document.getElementById('spine-remove-icon');
    const iconIdInput= document.getElementById('spine_chatbot_icon_id');
    const preview    = document.getElementById('spine-icon-preview');
    let mediaFrame   = null;

    if (!uploadBtn) return;

    uploadBtn.addEventListener('click', e => {
      e.preventDefault();

      if (mediaFrame) { mediaFrame.open(); return; }

      mediaFrame = wp.media({
        title:    'Select or Upload a Chat Icon',
        button:   { text: 'Use This Image' },
        library:  { type: 'image' },
        multiple: false,
      });

      mediaFrame.on('select', () => {
        const attachment = mediaFrame.state().get('selection').first().toJSON();
        iconIdInput.value   = attachment.id;
        uploadBtn.textContent = 'Change Icon';

        // Update preview
        if (preview) {
          const url   = (attachment.sizes && attachment.sizes.thumbnail)
            ? attachment.sizes.thumbnail.url
            : attachment.url;
          preview.innerHTML =
            `<img src="${escAttr(url)}" alt="Launcher icon" style="width:60px;height:60px;border-radius:50%;object-fit:cover;">`;
        }
      });

      mediaFrame.open();
    });

    if (removeBtn) {
      removeBtn.addEventListener('click', e => {
        e.preventDefault();
        iconIdInput.value    = '0';
        if (preview) {
          preview.innerHTML =
            `<div class="spine-media-placeholder">
               <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5">
                 <rect x="3" y="3" width="18" height="18" rx="2"/>
                 <circle cx="8.5" cy="8.5" r="1.5"/>
                 <path d="m21 15-5-5L5 21"/>
               </svg>
             </div>`;
        }
        uploadBtn.textContent = 'Upload Icon';
        removeBtn.hidden = true;
      });
    }
  }

  // ── AJAX helper ────────────────────────────────────────────────────────────────

  // PHP now passes a root-relative path via wp_parse_url() so the domain is
  // never in the URL.  Safety net: if an old absolute URL is in the cache,
  // strip the domain and keep only the path.
  var _adminAjaxUrl = (function () {
    var url = spineAdminVars.ajaxUrl || '/wp-admin/admin-ajax.php';
    if (url.charAt(0) === '/') { return url; }
    try { return new URL(url).pathname; } catch (e) {}
    return '/wp-admin/admin-ajax.php';
  }());

  function ajaxReq(action, extraData) {
    return $.ajax({
      url:    _adminAjaxUrl,
      method: 'POST',
      data:   Object.assign({ action: action, nonce: spineAdminVars.nonce }, extraData),
    }).then(function (r) { return r; }).catch(function () { return null; });
  }

  // ── UI state helpers ──────────────────────────────────────────────────────────
  function showListLoading() {
    const loadEl  = document.getElementById('spine-list-loading');
    const emptyEl = document.getElementById('spine-list-empty');
    if (loadEl)  loadEl.hidden  = false;
    if (emptyEl) emptyEl.hidden = true;   // hide stale empty state while reloading
  }
  function hideListLoading() {
    const el = document.getElementById('spine-list-loading');
    if (el) el.hidden = true;
  }
  function showListEmpty() {
    const loadEl  = document.getElementById('spine-list-loading');
    const emptyEl = document.getElementById('spine-list-empty');
    if (loadEl)  loadEl.hidden  = true;   // ensure spinner is gone
    if (emptyEl) emptyEl.hidden = false;
  }
  function setStatEl(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  }

  // ── String helpers ────────────────────────────────────────────────────────────
  function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = String(str || '');
    return d.innerHTML;
  }

  function escAttr(str) {
    return String(str || '').replace(/"/g, '&quot;');
  }

  function ucfirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  function relativeTime(dateStr) {
    if (!dateStr) return '';
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60)   return diff + 's ago';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    return Math.floor(diff / 3600) + 'h ago';
  }

  // ── Bootstrap ─────────────────────────────────────────────────────────────────
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

}(jQuery));
