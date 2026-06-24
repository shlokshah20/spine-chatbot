<?php
/**
 * Knowledge Base Review — Admin View
 *
 * Shows all Q&A entries from knowledgebase-v1.php in a searchable table
 * so the admin can review, verify, and spot inaccuracies before they
 * reach visitors.
 *
 * @var array $kb  Full KB array returned by knowledgebase-v1.php
 * @package SpineChatbot
 */
defined( 'ABSPATH' ) || exit;

// ── Flatten all entries into one list for the table ─────────────────────────
$entries = [];

// Global general FAQs
foreach ( $kb['global']['general_faqs'] ?? [] as $faq ) {
    $entries[] = [
        'module'   => 'General / Company',
        'module_id'=> 'general',
        'question' => $faq['question'] ?? '',
        'answer'   => $faq['answer']   ?? '',
        'keywords' => implode( ', ', $faq['keywords'] ?? [] ),
        'type'     => 'FAQ',
    ];
}

// Module FAQs
foreach ( $kb['modules'] ?? [] as $id => $module ) {
    $module_title = $module['title'] ?? ucwords( str_replace( '-', ' ', $id ) );

    // Module overview → show as a synthetic entry for easy review
    if ( ! empty( $module['overview'] ) ) {
        $entries[] = [
            'module'   => $module_title,
            'module_id'=> $id,
            'question' => 'Module Overview',
            'answer'   => $module['overview'],
            'keywords' => implode( ', ', $module['keywords'] ?? [] ),
            'type'     => 'Overview',
        ];
    }

    // FAQs
    foreach ( $module['faqs'] ?? [] as $faq ) {
        $entries[] = [
            'module'   => $module_title,
            'module_id'=> $id,
            'question' => $faq['question'] ?? '',
            'answer'   => $faq['answer']   ?? '',
            'keywords' => implode( ', ', $faq['keywords'] ?? [] ),
            'type'     => 'FAQ',
        ];
    }

    // Features → show as answer entries so admin can verify feature descriptions
    foreach ( $module['features'] ?? [] as $feat ) {
        $entries[] = [
            'module'   => $module_title,
            'module_id'=> $id,
            'question' => 'Feature: ' . ( $feat['name'] ?? '' ),
            'answer'   => $feat['desc'] ?? '',
            'keywords' => '',
            'type'     => 'Feature',
        ];
    }
}

$total = count( $entries );

// Build module list for the filter dropdown
$module_names = [ '' => 'All Modules' ];
foreach ( $entries as $e ) {
    $module_names[ $e['module_id'] ] = $e['module'];
}
$module_names = array_unique( $module_names );

// Count by type
$type_counts = array_count_values( array_column( $entries, 'type' ) );
?>
<div class="wrap spine-admin">

    <h1 style="margin-bottom:4px;">Knowledge Base Review</h1>
    <p style="color:#6b7280;margin-top:0;margin-bottom:24px;">
        Review every question, answer, feature description, and keyword that the chatbot uses to respond to visitors.
        If any information is inaccurate, contact your Spine Technologies account manager to update the knowledge base.
    </p>

    <!-- ── Stats ────────────────────────────────────────────────────────── -->
    <div style="display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap;">
        <?php
        $stat_items = [
            [ 'val' => $total,                        'lbl' => 'Total Entries',    'color' => '#1d4ed8' ],
            [ 'val' => $type_counts['FAQ']      ?? 0, 'lbl' => 'FAQs',             'color' => '#0891b2' ],
            [ 'val' => $type_counts['Feature']  ?? 0, 'lbl' => 'Features',         'color' => '#7c3aed' ],
            [ 'val' => $type_counts['Overview'] ?? 0, 'lbl' => 'Module Overviews', 'color' => '#059669' ],
            [ 'val' => count( $module_names ) - 1,    'lbl' => 'Modules',          'color' => '#d97706' ],
        ];
        foreach ( $stat_items as $s ) : ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px 22px;min-width:110px;text-align:center;">
            <div style="font-size:1.75rem;font-weight:700;color:<?php echo esc_attr( $s['color'] ); ?>;line-height:1;"><?php echo esc_html( $s['val'] ); ?></div>
            <div style="font-size:11px;color:#6b7280;margin-top:4px;text-transform:uppercase;letter-spacing:.05em;"><?php echo esc_html( $s['lbl'] ); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Toolbar ──────────────────────────────────────────────────────── -->
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">

        <input type="search" id="spine-kb-search"
               placeholder="Search question, answer, or keyword…"
               style="padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;width:300px;"
               oninput="spineKbFilter()">

        <select id="spine-kb-module"
                onchange="spineKbFilter()"
                style="padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
            <?php foreach ( $module_names as $mid => $mlabel ) : ?>
            <option value="<?php echo esc_attr( $mid ); ?>"><?php echo esc_html( $mlabel ); ?></option>
            <?php endforeach; ?>
        </select>

        <select id="spine-kb-type"
                onchange="spineKbFilter()"
                style="padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
            <option value="">All Types</option>
            <option value="FAQ">FAQs only</option>
            <option value="Feature">Features only</option>
            <option value="Overview">Overviews only</option>
        </select>

        <span style="color:#9ca3af;font-size:13px;margin-left:auto;">
            Showing <strong id="spine-kb-visible"><?php echo esc_html( $total ); ?></strong> of <?php echo esc_html( $total ); ?> entries
        </span>
    </div>

    <!-- ── Table ────────────────────────────────────────────────────────── -->
    <table class="wp-list-table widefat fixed" id="spine-kb-table"
           style="table-layout:auto;">
        <thead>
            <tr>
                <th style="width:180px;">Module</th>
                <th style="width:80px;">Type</th>
                <th style="width:28%;">Question / Feature</th>
                <th>Answer / Description</th>
                <th style="width:200px;">Keywords</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $type_styles = [
            'FAQ'      => 'background:#eff6ff;color:#1d4ed8;',
            'Feature'  => 'background:#faf5ff;color:#7c3aed;',
            'Overview' => 'background:#f0fdf4;color:#16a34a;',
        ];
        foreach ( $entries as $i => $e ) :
            $ts = $type_styles[ $e['type'] ] ?? '';
            $search_str = strtolower( $e['module'] . ' ' . $e['question'] . ' ' . $e['answer'] . ' ' . $e['keywords'] );
        ?>
        <tr data-search="<?php echo esc_attr( $search_str ); ?>"
            data-module="<?php echo esc_attr( $e['module_id'] ); ?>"
            data-type="<?php echo esc_attr( $e['type'] ); ?>"
            style="<?php echo ( $i % 2 === 0 ) ? '' : 'background:#f9fafb;'; ?>">
            <td style="font-size:12px;font-weight:600;color:#374151;"><?php echo esc_html( $e['module'] ); ?></td>
            <td>
                <span style="<?php echo esc_attr( $ts ); ?>padding:2px 7px;border-radius:4px;font-size:11px;font-weight:600;">
                    <?php echo esc_html( $e['type'] ); ?>
                </span>
            </td>
            <td style="font-size:13px;font-weight:500;color:#111827;word-break:break-word;">
                <?php echo esc_html( $e['question'] ); ?>
            </td>
            <td style="font-size:13px;color:#374151;word-break:break-word;line-height:1.55;">
                <?php echo nl2br( esc_html( $e['answer'] ) ); ?>
            </td>
            <td style="font-size:11px;color:#9ca3af;word-break:break-word;">
                <?php echo esc_html( $e['keywords'] ?: '—' ); ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p style="color:#9ca3af;font-size:12px;margin-top:8px;">
        KB version: <strong><?php echo esc_html( $kb['version'] ?? '—' ); ?></strong>
        &bull; Generated: <strong><?php echo esc_html( $kb['generated_at'] ?? '—' ); ?></strong>
        &bull; To update KB content, edit <code>includes/knowledgebase-v1.php</code> and re-upload the plugin.
    </p>

</div>

<script>
function spineKbFilter() {
    var q      = (document.getElementById('spine-kb-search').value  || '').toLowerCase().trim();
    var module = (document.getElementById('spine-kb-module').value  || '').toLowerCase();
    var type   = (document.getElementById('spine-kb-type').value    || '');
    var rows   = document.querySelectorAll('#spine-kb-table tbody tr');
    var visible = 0;

    rows.forEach(function(r) {
        var matchQ  = !q      || r.dataset.search.indexOf(q) !== -1;
        var matchM  = !module || r.dataset.module === module;
        var matchT  = !type   || r.dataset.type   === type;
        var show    = matchQ && matchM && matchT;
        r.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    var el = document.getElementById('spine-kb-visible');
    if (el) el.textContent = visible;
}
</script>
