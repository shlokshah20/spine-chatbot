<?php
/**
 * Agent Management View (Super-Admin)
 *
 * @package SpineChatbot
 * @var WP_User[] $all_users  All WP users eligible to be agents
 * @var int[]     $agent_ids  Currently registered agent user IDs
 */
defined( 'ABSPATH' ) || exit;

$saved = isset( $_GET['saved'] ) && $_GET['saved'] === '1';
?>
<div class="wrap spine-admin spine-agents-page">

    <div class="spine-admin__header">
        <h1 class="spine-admin__title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1d4ed8" stroke-width="2" aria-hidden="true">
                <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                <path d="M19 12v4m2-2h-4" stroke-linecap="round"/>
            </svg>
            Manage Agents
        </h1>
        <p class="spine-admin__subtitle">Select WordPress users to register as live-chat agents. Only registered agents appear in the round-robin routing queue.</p>
    </div>

    <?php if ( $saved ) : ?>
    <div class="spine-notice spine-notice--success">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
        Agent list updated successfully.
    </div>
    <?php endif; ?>

    <!-- Live Agent Status Board -->
    <div class="spine-card">
        <h2 class="spine-card__title">Live Agent Status</h2>
        <div class="spine-agent-board" id="spine-agent-board">
            <?php
            $registered_agents = Spine_Chatbot_DB::get_all_agents();
            if ( empty( $registered_agents ) ) :
            ?>
            <div class="spine-empty-state" style="padding:24px;">
                <p>No agents registered yet. Add agents below to start routing live chats.</p>
            </div>
            <?php else : ?>
            <table class="spine-table">
                <thead>
                    <tr>
                        <th>Agent</th>
                        <th>Status</th>
                        <th>Active Chats</th>
                        <th>Last Active</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $registered_agents as $agent ) :
                        $status    = get_user_meta( $agent->ID, Spine_Chatbot_DB::META_STATUS, true ) ?: 'offline';
                        $last_ping = (int) get_user_meta( $agent->ID, Spine_Chatbot_DB::META_LAST_PING, true );
                        $chat_cnt  = (int) get_user_meta( $agent->ID, Spine_Chatbot_DB::META_CHAT_COUNT, true );
                        $timeout   = SPINE_CHATBOT_AGENT_TIMEOUT;
                        // Auto-degrade display if timed out
                        if ( $last_ping && ( time() - $last_ping ) > $timeout ) {
                            $status = 'away';
                        }
                        $status_class = [
                            'online'  => 'spine-badge--green',
                            'away'    => 'spine-badge--yellow',
                            'offline' => 'spine-badge--gray',
                        ][ $status ] ?? 'spine-badge--gray';
                    ?>
                    <tr id="agent-row-<?php echo esc_attr( $agent->ID ); ?>">
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <?php echo get_avatar( $agent->ID, 32, '', '', [ 'class' => 'spine-avatar-sm' ] ); ?>
                                <div>
                                    <strong><?php echo esc_html( $agent->display_name ); ?></strong>
                                    <br><small style="color:#6b7280;"><?php echo esc_html( $agent->user_email ); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="spine-badge <?php echo esc_attr( $status_class ); ?>">
                                <?php echo esc_html( ucfirst( $status ) ); ?>
                            </span>
                        </td>
                        <td style="font-weight:600;"><?php echo esc_html( $chat_cnt ); ?></td>
                        <td>
                            <?php echo $last_ping
                                ? esc_html( human_time_diff( $last_ping ) . ' ago' )
                                : '<em style="color:#9ca3af;">Never</em>'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add/Remove Agents Form -->
    <div class="spine-card">
        <h2 class="spine-card__title">Register Agents</h2>
        <p style="color:#6b7280;margin-bottom:20px;">Check the box next to each WordPress user you want to designate as a live-chat agent. Un-checking a user will remove their agent status and halt new chat routing to them.</p>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'spine_save_agents', 'spine_agents_nonce' ); ?>
            <input type="hidden" name="action" value="spine_save_agents">

            <div class="spine-user-list">
                <?php foreach ( $all_users as $user ) :
                    $is_checked = in_array( $user->ID, $agent_ids, true );
                    $is_me      = $user->ID === get_current_user_id();
                ?>
                <label class="spine-user-item <?php echo $is_checked ? 'spine-user-item--active' : ''; ?>">
                    <input type="checkbox"
                           name="agent_ids[]"
                           value="<?php echo esc_attr( $user->ID ); ?>"
                           <?php checked( $is_checked ); ?>>
                    <?php echo get_avatar( $user->ID, 40, '', '', [ 'class' => 'spine-user-item__avatar' ] ); ?>
                    <div class="spine-user-item__info">
                        <strong><?php echo esc_html( $user->display_name ); ?></strong>
                        <?php if ( $is_me ) : ?><span class="spine-badge spine-badge--blue">You</span><?php endif; ?>
                        <br>
                        <small style="color:#6b7280;"><?php echo esc_html( $user->user_email ); ?> &bull; <?php echo esc_html( implode( ', ', $user->roles ) ); ?></small>
                    </div>
                    <div class="spine-user-item__status">
                        <?php if ( $is_checked ) : ?>
                            <span class="spine-badge spine-badge--green">Agent</span>
                        <?php else : ?>
                            <span class="spine-badge spine-badge--gray">Not Agent</span>
                        <?php endif; ?>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>

            <?php submit_button( 'Save Agent List', 'primary', 'submit', false, [ 'id' => 'spine-save-agents' ] ); ?>
        </form>
    </div>

    <!-- Routing Info -->
    <div class="spine-card spine-card--info">
        <h3>Round-Robin Routing Rules</h3>
        <ul class="spine-rule-list">
            <li><strong>1 agent online</strong> — all incoming chats routed to that agent.</li>
            <li><strong>Multiple agents online</strong> — chats distributed equally; agent with fewest active chats receives next request.</li>
            <li><strong>Agent goes Away</strong> — pending (unaccepted) sessions are immediately re-dispatched to the next available agent.</li>
            <li><strong>All agents Offline/Away</strong> — visitor is routed to the offline lead-capture form and Book a Demo link.</li>
            <li><strong>Agent timeout</strong> — if an agent's dashboard tab is closed or idle for <?php echo esc_html( SPINE_CHATBOT_AGENT_TIMEOUT ); ?> seconds, they are automatically marked Away.</li>
        </ul>
    </div>

</div><!-- .wrap -->
