<?php
/**
 * Admin View: KB Optimization
 *
 * Shows AI-suggested Q&A entries (pending_review) and the unanswered query log.
 * Approve inserts the entry into the live KB; dismiss marks it dismissed.
 *
 * Variables provided by render_kb_optimization():
 *   $upgrades   — array of upgrade rows (pending_review)
 *   $unanswered — array of frequently unanswered query rows
 *   $opt_nonce  — wp nonce string for AJAX calls
 *
 * @package SpineChatbot
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap spine-admin-wrap">
    <h1 class="wp-heading-inline">KB Optimization</h1>
    <p class="description" style="margin-top:4px;">
        AI-generated Q&amp;A suggestions from unanswered visitor queries.
        Approve to add them to the live knowledge base or dismiss to ignore.
    </p>

    <!-- ── Pending AI Suggestions ─────────────────────────────────────────── -->
    <div class="spine-card" style="margin-top:20px;">
        <h2 class="spine-card__title">Pending Suggestions</h2>

        <?php if ( empty( $upgrades ) ) : ?>
            <p style="color:#6b7280;padding:12px 0;">
                No pending suggestions. The daily cron job generates new ones automatically
                once unanswered queries are detected.
            </p>
        <?php else : ?>
        <table class="widefat fixed striped" id="spine-kb-upgrades-table">
            <thead>
                <tr>
                    <th style="width:22%">Trigger Query</th>
                    <th style="width:20%">Suggested Title</th>
                    <th>Suggested Content</th>
                    <th style="width:9%">Confidence</th>
                    <th style="width:12%">Created</th>
                    <th style="width:14%">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $upgrades as $row ) : ?>
                <tr id="kb-upgrade-row-<?php echo (int) $row->id; ?>">
                    <td><?php echo esc_html( $row->trigger_query ); ?></td>
                    <td><?php echo esc_html( $row->suggested_title ); ?></td>
                    <td style="white-space:pre-wrap;font-size:12px;"><?php echo esc_html( $row->suggested_content ); ?></td>
                    <td><?php echo number_format( (float) $row->confidence_score * 100, 0 ); ?>%</td>
                    <td><?php echo esc_html( $row->created_at ); ?></td>
                    <td>
                        <button type="button"
                                class="button button-primary spine-kb-approve"
                                data-id="<?php echo (int) $row->id; ?>">
                            Approve
                        </button>
                        <button type="button"
                                class="button spine-kb-dismiss"
                                data-id="<?php echo (int) $row->id; ?>"
                                style="margin-top:4px;">
                            Dismiss
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- ── Unanswered Query Log ───────────────────────────────────────────── -->
    <div class="spine-card" style="margin-top:24px;">
        <h2 class="spine-card__title">Unanswered Query Log (last 30 days, freq ≥ 1)</h2>

        <?php if ( empty( $unanswered ) ) : ?>
            <p style="color:#6b7280;padding:12px 0;">No unanswered queries logged yet.</p>
        <?php else : ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Query</th>
                    <th style="width:18%">Search Terms</th>
                    <th style="width:12%">Status</th>
                    <th style="width:16%">Created</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $unanswered as $row ) : ?>
                <tr>
                    <td><?php echo esc_html( $row->query_text ); ?></td>
                    <td><?php echo esc_html( $row->search_terms ); ?></td>
                    <td><?php echo esc_html( $row->status ); ?></td>
                    <td><?php echo esc_html( $row->created_at ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
(function ($) {
    'use strict';

    var nonce   = <?php echo wp_json_encode( $opt_nonce ); ?>;
    var ajaxUrl = <?php echo wp_json_encode( wp_parse_url( admin_url( 'admin-ajax.php' ), PHP_URL_PATH ) ); ?>;

    function kbAction(action, id, $btn) {
        $btn.prop('disabled', true).text('…');
        $.post(ajaxUrl, { action: action, nonce: nonce, id: id })
            .done(function (res) {
                if (res && res.success) {
                    $('#kb-upgrade-row-' + id).fadeOut(300, function () { $(this).remove(); });
                } else {
                    alert((res && res.data) ? res.data : 'Something went wrong.');
                    $btn.prop('disabled', false).text($btn.hasClass('spine-kb-approve') ? 'Approve' : 'Dismiss');
                }
            })
            .fail(function () {
                alert('Request failed. Please try again.');
                $btn.prop('disabled', false).text($btn.hasClass('spine-kb-approve') ? 'Approve' : 'Dismiss');
            });
    }

    $(document).on('click', '.spine-kb-approve', function () {
        kbAction('spine_kb_approve_upgrade', $(this).data('id'), $(this));
    });

    $(document).on('click', '.spine-kb-dismiss', function () {
        kbAction('spine_kb_dismiss_upgrade', $(this).data('id'), $(this));
    });

}(jQuery));
</script>
