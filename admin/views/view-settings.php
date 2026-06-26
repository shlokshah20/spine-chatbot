<?php
/**
 * Plugin Settings Page
 *
 * @package SpineChatbot
 */
defined( 'ABSPATH' ) || exit;

$saved        = isset( $_GET['saved'] ) && $_GET['saved'] === '1';
$position     = get_option( 'spine_chatbot_position',        'bottom-right' );
$icon_id      = (int) get_option( 'spine_chatbot_icon_id',  0 );
$icon_url     = $icon_id ? wp_get_attachment_image_url( $icon_id, 'medium' ) : '';
$accent       = get_option( 'spine_chatbot_accent_color',   '#1d4ed8' );
$primary      = get_option( 'spine_chatbot_primary_color',  '#0891b2' );
?>
<div class="wrap spine-admin spine-settings-page">

    <div class="spine-admin__header">
        <h1 class="spine-admin__title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1d4ed8" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
            Chatbot Settings
        </h1>
    </div>

    <?php if ( $saved ) : ?>
    <div class="spine-notice spine-notice--success">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
        Settings saved successfully.
    </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="spine-settings-form">
        <?php wp_nonce_field( 'spine_save_settings', 'spine_settings_nonce' ); ?>
        <input type="hidden" name="action" value="spine_save_settings">

        <!-- ── General ─────────────────────────────────────────────────────── -->
        <div class="spine-card">
            <h2 class="spine-card__title">General</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="spine_chatbot_enabled">Enable Widget</label></th>
                    <td>
                        <label class="spine-toggle">
                            <input type="checkbox" id="spine_chatbot_enabled" name="spine_chatbot_enabled" value="1"
                                   <?php checked( get_option( 'spine_chatbot_enabled', '1' ), '1' ); ?>>
                            <span class="spine-toggle__slider"></span>
                        </label>
                        <span class="description">Show/hide the chat widget across your site.</span>
                    </td>
                </tr>
                <tr>
                    <th><label for="spine_chatbot_bot_name">Bot Display Name</label></th>
                    <td>
                        <input type="text" id="spine_chatbot_bot_name" name="spine_chatbot_bot_name"
                               value="<?php echo esc_attr( get_option( 'spine_chatbot_bot_name', 'Spine Assistant' ) ); ?>"
                               class="regular-text">
                        <p class="description">Name shown in the chat header.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="spine_chatbot_welcome_message">Welcome Message</label></th>
                    <td>
                        <textarea id="spine_chatbot_welcome_message" name="spine_chatbot_welcome_message"
                                  class="large-text" rows="3"><?php echo esc_textarea( get_option( 'spine_chatbot_welcome_message', "Hello! 👋 I'm the Spine HR Assistant. How can I help you today?" ) ); ?></textarea>
                        <p class="description">First message the bot sends when a visitor opens the chat.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="spine_chatbot_admin_email">Lead Notification Email</label></th>
                    <td>
                        <input type="email" id="spine_chatbot_admin_email" name="spine_chatbot_admin_email"
                               value="<?php echo esc_attr( get_option( 'spine_chatbot_admin_email', get_option( 'admin_email' ) ) ); ?>"
                               class="regular-text">
                        <p class="description">HTML lead notifications are sent to this address.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="spine_chatbot_demo_url">Book a Demo URL</label></th>
                    <td>
                        <input type="url" id="spine_chatbot_demo_url" name="spine_chatbot_demo_url"
                               value="<?php echo esc_attr( get_option( 'spine_chatbot_demo_url', 'https://spinetechnologies.com/request-demo/' ) ); ?>"
                               class="large-text">
                    </td>
                </tr>
                <tr>
                    <th><label for="spine_chatbot_support_email">Support Email Address</label></th>
                    <td>
                        <input type="email" id="spine_chatbot_support_email" name="spine_chatbot_support_email"
                               value="<?php echo esc_attr( get_option( 'spine_chatbot_support_email', 'support@spinetechnologies.com' ) ); ?>"
                               class="regular-text"
                               placeholder="support@yourcompany.com">
                        <p class="description">
                            When a visitor clicks <strong>"Support"</strong> in the chat, they are shown a polite message
                            with a clickable <code>mailto:</code> link to this address. The chat input is then frozen
                            — no further messages can be sent.
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── AI Configuration ─────────────────────────────────────────────── -->
        <div class="spine-card">
            <h2 class="spine-card__title">AI Configuration</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="spine_chatbot_anthropic_key">Anthropic API Key</label></th>
                    <td>
                        <input type="password" id="spine_chatbot_anthropic_key"
                               name="spine_chatbot_anthropic_key"
                               value="<?php echo esc_attr( get_option( 'spine_chatbot_anthropic_key', '' ) ); ?>"
                               class="large-text"
                               autocomplete="off"
                               placeholder="sk-ant-…">
                        <p class="description">
                            Claude API key from <a href="https://console.anthropic.com" target="_blank" rel="noopener">console.anthropic.com</a>.
                            Stored in <code>wp_options</code>. The chatbot will not function without a valid key.
                        </p>
                        <?php if ( get_option( 'spine_chatbot_anthropic_key' ) ) : ?>
                        <p style="color:#16a34a;font-size:12px;margin-top:4px;">
                            ✓ API key is set. Leave blank to keep the existing key, or enter a new value to replace it.
                        </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Appearance ───────────────────────────────────────────────────── -->
        <div class="spine-card">
            <h2 class="spine-card__title">Appearance</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="spine_chatbot_position">Widget Position</label></th>
                    <td>
                        <select id="spine_chatbot_position" name="spine_chatbot_position" class="spine-select-lg">
                            <option value="bottom-right" <?php selected( $position, 'bottom-right' ); ?>>Bottom-Right</option>
                            <option value="bottom-left"  <?php selected( $position, 'bottom-left'  ); ?>>Bottom-Left</option>
                        </select>
                        <p class="description">Where the floating chat launcher button appears on your site.</p>
                    </td>
                </tr>
                <tr>
                    <th>Custom Launcher Icon</th>
                    <td>
                        <div class="spine-media-field">
                            <div class="spine-media-preview" id="spine-icon-preview">
                                <?php if ( $icon_url ) : ?>
                                    <img src="<?php echo esc_url( $icon_url ); ?>" alt="Launcher icon" style="width:60px;height:60px;border-radius:50%;object-fit:cover;">
                                <?php else : ?>
                                    <div class="spine-media-placeholder">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="spine-media-controls">
                                <input type="hidden" id="spine_chatbot_icon_id" name="spine_chatbot_icon_id" value="<?php echo esc_attr( $icon_id ); ?>">
                                <button type="button" class="spine-btn spine-btn--secondary" id="spine-upload-icon">
                                    <?php echo $icon_url ? 'Change Icon' : 'Upload Icon'; ?>
                                </button>
                                <?php if ( $icon_url ) : ?>
                                <button type="button" class="spine-btn spine-btn--link" id="spine-remove-icon">Remove</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="description">Upload a custom image for the floating chat launcher. Leave empty to use the default chat icon. Recommended: 64×64 px PNG with transparency.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="spine_chatbot_accent_color">Accent Colour</label></th>
                    <td>
                        <input type="color" id="spine_chatbot_accent_color" name="spine_chatbot_accent_color"
                               value="<?php echo esc_attr( $accent ); ?>"
                               class="spine-color-picker">
                        <span class="description">Chat header gradient start & button background colour.</span>
                    </td>
                </tr>
                <tr>
                    <th><label for="spine_chatbot_primary_color">Primary Colour</label></th>
                    <td>
                        <input type="color" id="spine_chatbot_primary_color" name="spine_chatbot_primary_color"
                               value="<?php echo esc_attr( $primary ); ?>"
                               class="spine-color-picker">
                        <span class="description">User message bubble & secondary accent colour.</span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Submit ──────────────────────────────────────────────────────── -->
        <div class="spine-card spine-card--no-pad">
            <?php submit_button( 'Save Settings', 'primary', 'submit', false ); ?>
        </div>

    </form>
</div><!-- .wrap -->
