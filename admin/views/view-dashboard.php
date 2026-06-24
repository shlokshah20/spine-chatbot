<?php
/**
 * Agent Chat Dashboard View
 * Shown to: Agents (own sessions only) | Super-Admins (all sessions + global stats)
 *
 * @package SpineChatbot
 * @var bool   $is_super_admin
 * @var bool   $is_agent
 * @var array  $stats          Keyed by status → count
 * @var WP_User $current_user
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap spine-admin spine-dashboard">

    <!-- ── Page header ─────────────────────────────────────────────────── -->
    <div class="spine-admin__header">
        <div class="spine-admin__header-left">
            <h1 class="spine-admin__title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="spine-icon" aria-hidden="true">
                    <path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2Z" fill="#1d4ed8"/>
                    <circle cx="8" cy="10" r="1.2" fill="white"/><circle cx="12" cy="10" r="1.2" fill="white"/><circle cx="16" cy="10" r="1.2" fill="white"/>
                </svg>
                Spine Chat Control
            </h1>
            <p class="spine-admin__subtitle">
                <?php if ( $is_super_admin ) : ?>
                    Super-Admin View — monitoring all active conversations and agents.
                <?php else : ?>
                    Agent View — your assigned conversations appear below.
                <?php endif; ?>
            </p>
        </div>

        <?php if ( $is_agent || $is_super_admin ) : ?>
        <div class="spine-admin__header-right">
            <div class="spine-presence-toggle" id="spine-presence-toggle">
                <span class="spine-presence-dot" id="presence-dot"></span>
                <span id="presence-label">Offline</span>
                <button class="spine-btn spine-btn--sm" id="spine-go-online">Go Online</button>
                <button class="spine-btn spine-btn--sm spine-btn--danger" id="spine-go-offline" hidden>Go Offline</button>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Stats strip (super-admin only) ──────────────────────────────── -->
    <?php if ( $is_super_admin ) : ?>
    <div class="spine-stats-strip" id="spine-stats-strip">
        <div class="spine-stat-card">
            <span class="spine-stat-card__value" id="stat-active"><?php echo absint( $stats['active'] ?? 0 ); ?></span>
            <span class="spine-stat-card__label">Active Chats</span>
        </div>
        <div class="spine-stat-card">
            <span class="spine-stat-card__value" id="stat-pending"><?php echo absint( $stats['pending_agent'] ?? 0 ); ?></span>
            <span class="spine-stat-card__label">Queued</span>
        </div>
        <div class="spine-stat-card">
            <span class="spine-stat-card__value" id="stat-leads"><?php echo absint( $stats['lead_captured'] ?? 0 ); ?></span>
            <span class="spine-stat-card__label">Leads Captured</span>
        </div>
        <div class="spine-stat-card">
            <span class="spine-stat-card__value" id="stat-bot"><?php echo absint( $stats['bot'] ?? 0 ); ?></span>
            <span class="spine-stat-card__label">Bot Sessions</span>
        </div>
        <div class="spine-stat-card">
            <span class="spine-stat-card__value" id="stat-online-agents">—</span>
            <span class="spine-stat-card__label">Agents Online</span>
        </div>
    </div>
    <?php endif; ?>

    <div class="spine-dashboard__layout">

        <!-- ── Left panel: session queue / list ─────────────────────────── -->
        <aside class="spine-dashboard__sidebar">
            <div class="spine-sidebar__header">
                <h2>
                    <?php echo $is_super_admin ? 'All Sessions' : 'My Chats'; ?>
                </h2>
                <button class="spine-btn spine-btn--xs" id="spine-refresh-list" title="Refresh">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                </button>
            </div>

            <?php if ( $is_super_admin ) : ?>
            <!-- Agent filter (super-admin only) -->
            <div class="spine-sidebar__filters">
                <select id="spine-filter-status" class="spine-select">
                    <option value="">All Statuses</option>
                    <option value="pending_agent">Queued</option>
                    <option value="active">Active</option>
                    <option value="lead_captured">Lead Captured</option>
                    <option value="closed">Closed</option>
                </select>
            </div>
            <?php endif; ?>

            <!-- Agent list strip (super-admin) -->
            <?php if ( $is_super_admin ) : ?>
            <div class="spine-agent-strip" id="spine-agent-strip">
                <p class="spine-sidebar__section-label">Agents</p>
                <div id="spine-agent-list">
                    <span class="spine-loading-text">Loading…</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Session list -->
            <div class="spine-session-list" id="spine-session-list">
                <div class="spine-loading-state" id="spine-list-loading">
                    <div class="spine-spinner"></div>
                    <span>Loading chats…</span>
                </div>
                <div class="spine-empty-state" id="spine-list-empty" hidden>
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5"><path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2Z"/></svg>
                    <p>No active chats</p>
                </div>
                <ul class="spine-session-items" id="spine-session-items"></ul>
            </div>

            <!-- Incoming chat notifications -->
            <div class="spine-incoming-chats" id="spine-incoming-chats" hidden>
                <p class="spine-sidebar__section-label">
                    <span class="spine-pulse-dot"></span> Incoming Requests
                </p>
                <ul id="spine-incoming-list"></ul>
            </div>
        </aside>

        <!-- ── Right panel: active chat conversation ─────────────────────── -->
        <main class="spine-dashboard__main" id="spine-main-panel">
            <!-- Empty state when no chat selected -->
            <div class="spine-main__empty" id="spine-main-empty">
                <div class="spine-main__empty-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none"><path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2Z" fill="#e0e7ff"/><circle cx="8" cy="10" r="1.2" fill="#6366f1"/><circle cx="12" cy="10" r="1.2" fill="#6366f1"/><circle cx="16" cy="10" r="1.2" fill="#6366f1"/></svg>
                </div>
                <h3>Select a chat to begin</h3>
                <p>Click any session on the left to open the conversation.</p>
            </div>

            <!-- Active chat view (hidden until session selected) -->
            <div class="spine-chat-view" id="spine-chat-view" hidden>

                <!-- Chat header -->
                <div class="spine-chat-view__header" id="chat-view-header">
                    <div class="spine-chat-view__user-info">
                        <div class="spine-avatar" id="chat-user-avatar">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                        </div>
                        <div>
                            <div class="spine-chat-view__user-name" id="chat-user-name">Visitor</div>
                            <div class="spine-chat-view__user-meta" id="chat-user-meta"></div>
                        </div>
                    </div>
                    <div class="spine-chat-view__actions">
                        <span class="spine-status-badge" id="chat-status-badge">Active</span>
                        <?php if ( $is_agent ) : ?>
                        <button class="spine-btn spine-btn--danger spine-btn--sm" id="spine-close-chat">
                            End Chat
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Conversation -->
                <div class="spine-chat-view__messages" id="chat-view-messages" role="log" aria-live="polite"></div>

                <!-- Typing indicator -->
                <div class="spine-chat-view__typing" id="chat-view-typing" hidden>
                    <div class="spine-typing-dots"><span></span><span></span><span></span></div>
                    <small>User is typing…</small>
                </div>

                <!-- Agent reply area (only for assigned agent or super-admin) -->
                <div class="spine-chat-view__input-area" id="chat-view-input-area" hidden>
                    <label class="spine-agent-attach-btn" id="spine-agent-attach-btn"
                           title="Attach file (JPG, PNG, PDF · max 5 MB)"
                           aria-label="Attach file">
                        <input type="file" id="spine-agent-file-input"
                               accept=".jpg,.jpeg,.png,.pdf"
                               style="display:none;" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66L9.41 17.42a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                        </svg>
                    </label>
                    <textarea id="agent-reply-input"
                              class="spine-chat-view__input"
                              placeholder="Type your reply…"
                              rows="2"
                              maxlength="1000"></textarea>
                    <button class="spine-btn spine-btn--primary" id="agent-reply-send">
                        Send
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M22 2L11 13M22 2L15 22L11 13M22 2L2 9L11 13"/></svg>
                    </button>
                </div>

                <!-- Incoming request accept panel -->
                <div class="spine-incoming-accept" id="spine-incoming-accept" hidden>
                    <p>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                        New chat request from <strong id="accept-user-name"></strong>
                    </p>
                    <div class="spine-incoming-accept__actions">
                        <button class="spine-btn spine-btn--primary" id="spine-accept-btn">Accept Chat</button>
                        <button class="spine-btn" id="spine-decline-btn">Decline</button>
                    </div>
                </div>
            </div><!-- #spine-chat-view -->
        </main>

    </div><!-- .spine-dashboard__layout -->

    <!-- Hidden state store -->
    <input type="hidden" id="spine-active-session" value="">
    <input type="hidden" id="spine-last-message-time" value="">
    <input type="hidden" id="spine-is-super-admin" value="<?php echo $is_super_admin ? '1' : '0'; ?>">

</div><!-- .wrap -->
