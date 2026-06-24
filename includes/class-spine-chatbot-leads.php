<?php
/**
 * Lead Capture & Email Notification
 *
 * Validates, sanitises, stores captured lead data, and fires an immediate
 * HTML email to the site administrator — matching premium form-plugin data
 * models (Fluent Forms, Elementor Forms) for clean, exportable storage.
 *
 * @package SpineChatbot
 */

defined( 'ABSPATH' ) || exit;

final class Spine_Chatbot_Leads {

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Validate, save, and notify for a captured lead.
     *
     * @param string $session_id Active session ID
     * @param array  $raw        Raw (unsanitised) POST payload
     * @return array{success: bool, errors: array}
     */
    public function capture( string $session_id, array $raw ): array {
        $errors = $this->validate( $raw );
        if ( ! empty( $errors ) ) {
            return [ 'success' => false, 'errors' => $errors ];
        }

        $lead = $this->sanitise( $raw );
        $lead['submitted_at'] = current_time( 'mysql' );
        $lead['session_id']   = $session_id;

        // Retrieve conversation snippet (last 6 messages) for the email
        $session  = Spine_Chatbot_DB::get_session( $session_id );
        $messages = json_decode( $session->transcript ?? '[]', true ) ?: [];
        $snippet  = array_slice( $messages, -6 );

        $lead['conversation_snippet'] = $snippet;

        // Persist to the interactions table
        Spine_Chatbot_DB::save_lead( $session_id, $lead );

        // Add a system message to the transcript
        Spine_Chatbot_DB::append_message( $session_id, [
            'role'    => 'system',
            'content' => 'Lead captured: ' . esc_html( $lead['name'] ) . ' (' . esc_html( $lead['email'] ) . ')',
        ] );

        // Fire admin notification
        $this->send_admin_notification( $lead );

        return [ 'success' => true, 'errors' => [] ];
    }

    // ── Validation ─────────────────────────────────────────────────────────────

    private function validate( array $raw ): array {
        $errors = [];

        if ( empty( trim( $raw['name'] ?? '' ) ) ) {
            $errors['name'] = 'Your name is required.';
        }

        if ( empty( $raw['email'] ) || ! is_email( $raw['email'] ) ) {
            $errors['email'] = 'A valid business email address is required.';
        } else {
            $domain = strtolower( substr( $raw['email'], strpos( $raw['email'], '@' ) + 1 ) );
            $personal_domains = [
                'gmail.com','googlemail.com','yahoo.com','yahoo.co.in','yahoo.co.uk',
                'yahoo.com.au','ymail.com','hotmail.com','hotmail.co.uk','hotmail.in',
                'outlook.com','outlook.in','live.com','msn.com','icloud.com','me.com',
                'mac.com','aol.com','mail.com','zoho.com','protonmail.com','proton.me',
                'tutanota.com','rediffmail.com','rocketmail.com','inbox.com',
                'fastmail.com','gmx.com','gmx.net','yandex.com','yandex.ru',
                'tempmail.com','guerrillamail.com','10minutemail.com',
            ];
            if ( in_array( $domain, $personal_domains, true ) ) {
                $errors['email'] = 'Please use your work email address — personal emails like Gmail are not accepted.';
            }
        }

        if ( empty( trim( $raw['phone'] ?? '' ) ) ) {
            $errors['phone'] = 'Your phone number is required.';
        } elseif ( ! preg_match( '/^[+\d\s\-()]{7,20}$/', $raw['phone'] ) ) {
            $errors['phone'] = 'Please enter a valid phone number.';
        }

        if ( empty( trim( $raw['company'] ?? '' ) ) ) {
            $errors['company'] = 'Your company name is required.';
        }

        return $errors;
    }

    // ── Sanitisation ───────────────────────────────────────────────────────────

    private function sanitise( array $raw ): array {
        return [
            'name'    => sanitize_text_field( $raw['name']    ?? '' ),
            'email'   => sanitize_email( $raw['email']        ?? '' ),
            'phone'   => sanitize_text_field( $raw['phone']   ?? '' ),
            'company' => sanitize_text_field( $raw['company'] ?? '' ),
            'message' => sanitize_textarea_field( $raw['message'] ?? '' ),
            'source'  => 'spine_chatbot_widget',
            'type'    => sanitize_key( $raw['type'] ?? 'demo_request' ), // demo_request | offline_ticket
        ];
    }

    // ── Email Notification ─────────────────────────────────────────────────────

    private function send_admin_notification( array $lead ): void {
        $admin_email = get_option( 'spine_chatbot_admin_email', get_option( 'admin_email' ) );
        $site_name   = get_bloginfo( 'name' );
        $type_label  = $lead['type'] === 'demo_request' ? 'Demo Request' : 'Offline Support Ticket';

        $subject = sprintf(
            '[%s] New Chatbot Lead — %s from %s',
            esc_html( $site_name ),
            esc_html( $lead['name'] ),
            esc_html( $lead['company'] )
        );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . esc_html( $site_name ) . ' Chatbot <' . esc_html( $admin_email ) . '>',
        ];

        $body = $this->build_email_html( $lead, $type_label );

        wp_mail( $admin_email, $subject, $body, $headers );
    }

    private function build_email_html( array $lead, string $type_label ): string {
        $snippet_html = '';
        foreach ( $lead['conversation_snippet'] as $msg ) {
            if ( in_array( $msg['role'] ?? '', [ 'bot', 'agent', 'user' ], true ) ) {
                $role  = ucfirst( esc_html( $msg['role'] ) );
                $text  = nl2br( esc_html( $msg['content'] ?? '' ) );
                $color = $msg['role'] === 'user' ? '#1e40af' : '#374151';
                $snippet_html .= "<tr>
                    <td style='padding:6px 12px;color:{$color};font-weight:bold;width:80px;'>{$role}</td>
                    <td style='padding:6px 12px;color:#374151;'>{$text}</td>
                </tr>";
            }
        }

        $message_row = '';
        if ( ! empty( $lead['message'] ) ) {
            $message_row = '<tr><td style="padding:10px 24px;background:#f9fafb;" colspan="2">
                <strong>Message:</strong><br>' . nl2br( esc_html( $lead['message'] ) ) . '</td></tr>';
        }

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Inter,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
<tr><td align="center">
<table width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">

  <!-- Header -->
  <tr>
    <td style="background:linear-gradient(135deg,#1d4ed8 0%,#0891b2 100%);padding:28px 32px;">
      <h1 style="margin:0;color:#fff;font-size:20px;font-weight:700;">New <?php echo esc_html( $type_label ); ?></h1>
      <p style="margin:4px 0 0;color:#bfdbfe;font-size:14px;">Received via Spine HR Chatbot Widget</p>
    </td>
  </tr>

  <!-- Lead Details -->
  <tr><td style="padding:28px 32px 0;">
    <h2 style="margin:0 0 16px;font-size:16px;color:#111827;">Contact Information</h2>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
      <tr style="background:#f9fafb;">
        <td style="padding:10px 16px;color:#6b7280;font-size:13px;width:130px;">Name</td>
        <td style="padding:10px 16px;color:#111827;font-size:14px;font-weight:600;"><?php echo esc_html( $lead['name'] ); ?></td>
      </tr>
      <tr>
        <td style="padding:10px 16px;color:#6b7280;font-size:13px;">Email</td>
        <td style="padding:10px 16px;"><a href="mailto:<?php echo esc_attr( $lead['email'] ); ?>" style="color:#1d4ed8;text-decoration:none;"><?php echo esc_html( $lead['email'] ); ?></a></td>
      </tr>
      <tr style="background:#f9fafb;">
        <td style="padding:10px 16px;color:#6b7280;font-size:13px;">Phone</td>
        <td style="padding:10px 16px;color:#111827;font-size:14px;"><?php echo esc_html( $lead['phone'] ); ?></td>
      </tr>
      <tr>
        <td style="padding:10px 16px;color:#6b7280;font-size:13px;">Company</td>
        <td style="padding:10px 16px;color:#111827;font-size:14px;font-weight:600;"><?php echo esc_html( $lead['company'] ); ?></td>
      </tr>
      <tr style="background:#f9fafb;">
        <td style="padding:10px 16px;color:#6b7280;font-size:13px;">Submitted</td>
        <td style="padding:10px 16px;color:#111827;font-size:14px;"><?php echo esc_html( $lead['submitted_at'] ); ?></td>
      </tr>
    </table>
  </td></tr>

  <?php if ( $message_row ) : ?>
  <!-- Message -->
  <tr><td style="padding:20px 32px 0;">
    <h2 style="margin:0 0 10px;font-size:16px;color:#111827;">Their Message</h2>
    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;color:#374151;font-size:14px;line-height:1.6;">
      <?php echo nl2br( esc_html( $lead['message'] ) ); ?>
    </div>
  </td></tr>
  <?php endif; ?>

  <!-- Conversation Snippet -->
  <?php if ( $snippet_html ) : ?>
  <tr><td style="padding:20px 32px 0;">
    <h2 style="margin:0 0 10px;font-size:16px;color:#111827;">Conversation Snapshot (last 6 messages)</h2>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;font-size:13px;">
      <?php echo $snippet_html; // Already escaped above ?>
    </table>
  </td></tr>
  <?php endif; ?>

  <!-- CTA -->
  <tr><td style="padding:24px 32px;">
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=spine-chat-control' ) ); ?>"
       style="display:inline-block;background:#1d4ed8;color:#fff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:600;font-size:14px;">
      Open Chat Dashboard →
    </a>
  </td></tr>

  <!-- Footer -->
  <tr>
    <td style="background:#f9fafb;padding:16px 32px;border-top:1px solid #e5e7eb;">
      <p style="margin:0;color:#9ca3af;font-size:12px;">
        Sent by Spine HR Chatbot Widget &bull; <?php echo esc_html( get_bloginfo( 'url' ) ); ?>
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}
