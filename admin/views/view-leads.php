<?php
/**
 * Leads & Demo Enquiries — Admin View
 *
 * @var array $leads  WP_DB result rows (status = lead_captured)
 * @package SpineChatbot
 */
defined( 'ABSPATH' ) || exit;

$total       = count( $leads );
$month_start = gmdate( 'Y-m-01 00:00:00' );
$this_month  = count(
    array_filter( $leads, static fn( $l ) => ( $l->created_at ?? '' ) >= $month_start )
);

$branch_labels = [
    'hr_suite'      => 'HR Suite',
    'assets'        => 'Spine Assets',
    'support'       => 'Support',
    'international' => 'International HR',
    ''              => '—',
];
$type_labels = [
    'demo_request'   => 'Demo Request',
    'offline_ticket' => 'Support Ticket',
];
?>
<div class="wrap spine-admin">

    <h1 style="margin-bottom:4px;">Leads &amp; Demo Enquiries</h1>
    <p style="color:#6b7280;margin-top:0;margin-bottom:24px;">
        All leads and demo requests submitted through the chatbot widget.
    </p>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p>Done.</p></div>
    <?php endif; ?>

    <!-- ── Stats ──────────────────────────────────────────────────────────── -->
    <div style="display:flex;gap:16px;margin-bottom:24px;flex-wrap:wrap;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:18px 28px;min-width:130px;text-align:center;">
            <div style="font-size:2rem;font-weight:700;color:#1d4ed8;line-height:1;"><?php echo esc_html( $total ); ?></div>
            <div style="font-size:12px;color:#6b7280;margin-top:4px;text-transform:uppercase;letter-spacing:.05em;">Total Leads</div>
        </div>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:18px 28px;min-width:130px;text-align:center;">
            <div style="font-size:2rem;font-weight:700;color:#0891b2;line-height:1;"><?php echo esc_html( $this_month ); ?></div>
            <div style="font-size:12px;color:#6b7280;margin-top:4px;text-transform:uppercase;letter-spacing:.05em;">This Month</div>
        </div>
    </div>

    <!-- ── Toolbar ────────────────────────────────────────────────────────── -->
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
            <?php wp_nonce_field( 'spine_export_leads', 'spine_leads_nonce' ); ?>
            <input type="hidden" name="action" value="spine_export_leads">
            <button type="submit" class="button button-primary">
                &#x2193;&nbsp; Download CSV
            </button>
        </form>

        <input type="search" id="spine-leads-search"
               placeholder="Search name, email, company…"
               style="padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;width:260px;"
               oninput="spineLeadsFilter(this.value)">
    </div>

    <!-- ── Table ─────────────────────────────────────────────────────────── -->
    <?php if ( empty( $leads ) ) : ?>
    <div style="text-align:center;padding:48px;color:#9ca3af;background:#fff;border:1px solid #e5e7eb;border-radius:10px;">
        <p style="font-size:1.1rem;margin:0;">No leads captured yet.</p>
        <p style="font-size:13px;margin:8px 0 0;">Leads appear here when visitors submit a demo request or offline support ticket via the chatbot widget.</p>
    </div>
    <?php else : ?>

    <table class="wp-list-table widefat fixed striped" id="spine-leads-table">
        <thead>
            <tr>
                <th style="width:140px;">Date</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Company</th>
                <th style="width:120px;">Branch</th>
                <th style="width:130px;">Type</th>
                <th>Message</th>
                <th style="width:60px;"></th>
            </tr>
        </thead>
        <tbody>
        <?php $modal_data = []; foreach ( $leads as $row ) :
            $data    = json_decode( $row->lead_data ?? '{}', true ) ?: [];
            $name    = $row->user_name    ?: ( $data['name']    ?? '—' );
            $email   = $row->user_email   ?: ( $data['email']   ?? '' );
            $phone   = $row->user_phone   ?: ( $data['phone']   ?? '—' );
            $company = $row->user_company ?: ( $data['company'] ?? '—' );
            $branch  = $branch_labels[ $row->branch ?? '' ] ?? esc_html( $row->branch ?? '—' );
            $type    = $type_labels[ $data['type'] ?? '' ]  ?? ucwords( str_replace( '_', ' ', $data['type'] ?? '—' ) );
            $msg     = wp_trim_words( $data['message'] ?? '', 18, '…' );
        ?>
        <tr data-search="<?php echo esc_attr( strtolower( $name . ' ' . $email . ' ' . $company ) ); ?>">
            <td style="white-space:nowrap;font-size:12px;color:#6b7280;"><?php echo esc_html( $row->created_at ); ?></td>
            <td><strong><?php echo esc_html( $name ); ?></strong></td>
            <td>
                <?php if ( $email ) : ?>
                <a href="mailto:<?php echo esc_attr( $email ); ?>" style="color:#1d4ed8;"><?php echo esc_html( $email ); ?></a>
                <?php else : ?>—<?php endif; ?>
            </td>
            <td><?php echo esc_html( $phone ); ?></td>
            <td><?php echo esc_html( $company ); ?></td>
            <td>
                <span style="background:#eff6ff;color:#1d4ed8;padding:2px 8px;border-radius:4px;font-size:12px;">
                    <?php echo esc_html( $branch ); ?>
                </span>
            </td>
            <td>
                <span style="background:#f0fdf4;color:#16a34a;padding:2px 8px;border-radius:4px;font-size:12px;">
                    <?php echo esc_html( $type ); ?>
                </span>
            </td>
            <td style="color:#6b7280;font-size:13px;"><?php echo esc_html( $msg ?: '—' ); ?></td>
            <td>
                <button class="button button-small spine-lead-view-btn"
                        data-lead="<?php echo esc_attr( (int) $row->id ); ?>"
                        style="white-space:nowrap;">View</button>
            </td>
        </tr>
        <?php
            // Store full lead payload as JSON for the modal
            $modal_data[] = [
                'id'      => (int) $row->id,
                'name'    => $name,
                'email'   => $email,
                'phone'   => $phone,
                'company' => $company,
                'branch'  => $branch,
                'type'    => $type,
                'message' => $data['message'] ?? '',
                'date'    => $row->created_at,
                'session' => $row->session_id ?? '',
            ];
        ?>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p style="color:#9ca3af;font-size:12px;margin-top:8px;">
        Showing <strong id="spine-leads-visible"><?php echo esc_html( $total ); ?></strong> of <?php echo esc_html( $total ); ?> leads
    </p>

    <?php endif; ?>

</div><!-- .wrap -->

<?php if ( ! empty( $leads ) ) : ?>
<!-- ── Lead Detail Modal ────────────────────────────────────────────────────── -->
<div id="spine-lead-modal-overlay"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;">
    <div id="spine-lead-modal"
         style="background:#fff;border-radius:12px;width:560px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);padding:28px 32px;position:relative;">

        <button id="spine-lead-modal-close"
                style="position:absolute;top:14px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:#9ca3af;line-height:1;"
                aria-label="Close">&times;</button>

        <h2 style="margin:0 0 20px;font-size:17px;color:#1f2937;" id="spine-lead-modal-title">Lead Detail</h2>

        <table style="width:100%;border-collapse:collapse;font-size:13.5px;" id="spine-lead-modal-table">
            <tbody></tbody>
        </table>

        <div id="spine-lead-modal-message" style="margin-top:20px;" hidden>
            <p style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9ca3af;margin:0 0 6px;">Message / Query</p>
            <div id="spine-lead-modal-msg-body"
                 style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;font-size:13.5px;color:#374151;line-height:1.6;white-space:pre-wrap;"></div>
        </div>
    </div>
</div>

<script>
(function () {
    var leads = <?php echo wp_json_encode( $modal_data ?? [] ); ?>;
    var leadsById = {};
    leads.forEach(function (l) { leadsById[l.id] = l; });

    var overlay = document.getElementById('spine-lead-modal-overlay');
    var modal   = document.getElementById('spine-lead-modal');

    function openModal(id) {
        var l = leadsById[id];
        if (!l) return;

        document.getElementById('spine-lead-modal-title').textContent = l.name || 'Lead Detail';

        var rows = [
            ['Date',       l.date],
            ['Name',       l.name],
            ['Email',      l.email],
            ['Phone',      l.phone],
            ['Company',    l.company],
            ['Branch',     l.branch],
            ['Type',       l.type],
            ['Session ID', l.session],
        ];
        var tbody = document.querySelector('#spine-lead-modal-table tbody');
        tbody.innerHTML = '';
        rows.forEach(function (r) {
            if (!r[1]) return;
            var tr = document.createElement('tr');
            tr.innerHTML = '<td style="padding:6px 0;width:110px;color:#9ca3af;font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.03em;vertical-align:top;">' +
                           r[0] + '</td><td style="padding:6px 0;color:#1f2937;">' + escHtml(r[1]) + '</td>';
            tbody.appendChild(tr);
        });

        var msgWrap = document.getElementById('spine-lead-modal-message');
        var msgBody = document.getElementById('spine-lead-modal-msg-body');
        if (l.message) {
            msgBody.textContent = l.message;
            msgWrap.hidden = false;
        } else {
            msgWrap.hidden = true;
        }

        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        overlay.style.display = 'none';
        document.body.style.overflow = '';
    }

    document.getElementById('spine-lead-modal-close').addEventListener('click', closeModal);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

    document.querySelectorAll('.spine-lead-view-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { openModal(parseInt(this.dataset.lead, 10)); });
    });

    function escHtml(str) {
        var d = document.createElement('div');
        d.textContent = String(str || '');
        return d.innerHTML;
    }
}());
</script>

<script>
function spineLeadsFilter(q) {
    q = q.toLowerCase().trim();
    var rows    = document.querySelectorAll('#spine-leads-table tbody tr');
    var visible = 0;
    rows.forEach(function(r) {
        var match = !q || r.dataset.search.indexOf(q) !== -1;
        r.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    var el = document.getElementById('spine-leads-visible');
    if (el) el.textContent = visible;
}
</script>
<?php endif; ?>
