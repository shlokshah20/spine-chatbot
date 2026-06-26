<?php
/**
 * Knowledge Base — Admin CRUD View (v2.0)
 *
 * Variables injected by render_kb() in class-spine-chatbot-admin.php:
 *   $kb_entries   array    rows from wp_spine_kb_entries for current page
 *   $total        int      total row count (optionally filtered by $filter_mod)
 *   $total_pages  int
 *   $page         int      current page
 *   $per_page     int
 *   $filter_mod   string   active module filter ('')
 *   $modules      array    distinct module names in the table
 *
 * @package SpineChatbot
 */
defined( 'ABSPATH' ) || exit;

$nonce       = wp_create_nonce( 'spine_admin_nonce' );
$ajax_url    = wp_parse_url( admin_url( 'admin-ajax.php' ), PHP_URL_PATH );
$entry_types = [ 'FAQ', 'Feature', 'Overview', 'General' ];

$type_badge = [
    'FAQ'      => 'background:#eff6ff;color:#1d4ed8;',
    'Feature'  => 'background:#faf5ff;color:#7c3aed;',
    'Overview' => 'background:#f0fdf4;color:#16a34a;',
    'General'  => 'background:#f9fafb;color:#374151;',
];
?>
<div class="wrap spine-admin">

    <h1 style="margin-bottom:4px;">Knowledge Base</h1>
    <p style="color:#6b7280;margin-top:0;margin-bottom:24px;">
        Manage the AI knowledge base. All entries are used by Claude for RAG (retrieval-augmented generation) to answer visitor questions.
    </p>

    <!-- ── Stats ──────────────────────────────────────────────────────────── -->
    <div style="display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px 22px;min-width:110px;text-align:center;">
            <div style="font-size:1.75rem;font-weight:700;color:#1d4ed8;line-height:1;" id="spine-kb-total"><?php echo esc_html( Spine_Chatbot_DB::count_kb_entries() ); ?></div>
            <div style="font-size:11px;color:#6b7280;margin-top:4px;text-transform:uppercase;letter-spacing:.05em;">Total Entries</div>
        </div>
        <?php foreach ( $modules as $mod ) : ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px 22px;min-width:110px;text-align:center;">
            <div style="font-size:1.75rem;font-weight:700;color:#0891b2;line-height:1;"><?php echo esc_html( Spine_Chatbot_DB::count_kb_entries( $mod ) ); ?></div>
            <div style="font-size:11px;color:#6b7280;margin-top:4px;text-transform:uppercase;letter-spacing:.05em;"><?php echo esc_html( $mod ); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Actions bar ────────────────────────────────────────────────────── -->
    <div style="display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap;">
        <button class="button button-primary" onclick="spineKbShowAddForm()">+ Add Entry</button>

        <!-- Seed from static KB -->
        <button class="button" id="spine-kb-seed-btn" onclick="spineKbSeed()">
            Import from Legacy KB
        </button>

        <span style="color:#6b7280;font-size:13px;margin-left:auto;">
            <?php echo esc_html( $total ); ?> entr<?php echo $total === 1 ? 'y' : 'ies'; ?>
            <?php if ( $filter_mod ) : ?>
                in <strong><?php echo esc_html( $filter_mod ); ?></strong> —
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=spine-chat-kb' ) ); ?>">Clear filter</a>
            <?php endif; ?>
        </span>
    </div>

    <!-- ── File Import Zone ───────────────────────────────────────────────── -->
    <div class="spine-card" style="margin-bottom:20px;">
        <h2 class="spine-card__title" style="font-size:14px;margin-bottom:12px;">Import File (PDF / DOCX / TXT)</h2>
        <div id="spine-kb-drop-zone"
             style="border:2px dashed #d1d5db;border-radius:10px;padding:24px;text-align:center;cursor:pointer;transition:border-color .2s;background:#fafafa;"
             ondragover="event.preventDefault();this.style.borderColor='#1d4ed8';"
             ondragleave="this.style.borderColor='#d1d5db';"
             ondrop="spineKbHandleDrop(event)">
            <p style="margin:0 0 8px;color:#374151;font-weight:600;">Drop your file here</p>
            <p style="margin:0;color:#9ca3af;font-size:13px;">or</p>
            <label style="display:inline-block;margin-top:8px;">
                <input type="file" id="spine-kb-file-pick" accept=".pdf,.docx,.txt" style="display:none;" onchange="spineKbUploadFile(this.files[0])">
                <span class="button">Browse file…</span>
            </label>
            <div style="display:flex;gap:12px;margin-top:14px;justify-content:center;flex-wrap:wrap;">
                <div>
                    <label style="font-size:12px;color:#6b7280;">Module</label>
                    <select id="spine-kb-import-module" style="margin-left:6px;padding:4px 8px;border:1px solid #d1d5db;border-radius:5px;font-size:13px;">
                        <?php foreach ( array_merge( $modules, [ 'General' ] ) as $m ) : ?>
                        <option value="<?php echo esc_attr( $m ); ?>"><?php echo esc_html( $m ); ?></option>
                        <?php endforeach; ?>
                        <option value="__new__">+ New module…</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;color:#6b7280;">Type</label>
                    <select id="spine-kb-import-type" style="margin-left:6px;padding:4px 8px;border:1px solid #d1d5db;border-radius:5px;font-size:13px;">
                        <?php foreach ( $entry_types as $t ) : ?>
                        <option value="<?php echo esc_attr( $t ); ?>"><?php echo esc_html( $t ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <div id="spine-kb-import-status" style="margin-top:10px;font-size:13px;display:none;"></div>
    </div>

    <!-- ── Add Entry Form (hidden by default) ────────────────────────────── -->
    <div id="spine-kb-add-form" class="spine-card" style="display:none;margin-bottom:20px;">
        <h2 class="spine-card__title" style="font-size:14px;margin-bottom:12px;">Add Knowledge Base Entry</h2>
        <textarea id="spine-kb-add-content" rows="5"
                  placeholder="Enter knowledge base content here. Be descriptive — the AI will use this text to answer visitor questions."
                  style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;resize:vertical;"></textarea>
        <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap;align-items:center;">
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:3px;">Module</label>
                <input type="text" id="spine-kb-add-module" list="spine-kb-module-list"
                       value="General"
                       style="padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;width:180px;">
                <datalist id="spine-kb-module-list">
                    <?php foreach ( $modules as $m ) : ?>
                    <option value="<?php echo esc_attr( $m ); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:3px;">Type</label>
                <select id="spine-kb-add-type" style="padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                    <?php foreach ( $entry_types as $t ) : ?>
                    <option value="<?php echo esc_attr( $t ); ?>"><?php echo esc_html( $t ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-top:18px;display:flex;gap:8px;">
                <button class="button button-primary" onclick="spineKbAdd()">Save Entry</button>
                <button class="button" onclick="spineKbHideAddForm()">Cancel</button>
            </div>
        </div>
        <div id="spine-kb-add-status" style="margin-top:8px;font-size:13px;display:none;"></div>
    </div>

    <!-- ── Module filter ──────────────────────────────────────────────────── -->
    <?php if ( ! empty( $modules ) ) : ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center;">
        <span style="font-size:12px;color:#6b7280;font-weight:600;">Filter by module:</span>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=spine-chat-kb' ) ); ?>"
           class="button button-small<?php echo $filter_mod === '' ? ' button-primary' : ''; ?>"
           style="font-size:12px;">All</a>
        <?php foreach ( $modules as $mod ) : ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=spine-chat-kb&module=' . urlencode( $mod ) ) ); ?>"
           class="button button-small<?php echo $filter_mod === $mod ? ' button-primary' : ''; ?>"
           style="font-size:12px;"><?php echo esc_html( $mod ); ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── Entries table ──────────────────────────────────────────────────── -->
    <?php if ( empty( $kb_entries ) ) : ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:40px;text-align:center;color:#9ca3af;">
        <p style="font-size:16px;margin:0 0 8px;">No knowledge base entries yet.</p>
        <p style="margin:0;font-size:13px;">Add entries manually above, import a file, or click <strong>"Import from Legacy KB"</strong> to migrate the built-in content.</p>
    </div>
    <?php else : ?>
    <table class="wp-list-table widefat fixed" id="spine-kb-table" style="table-layout:auto;">
        <thead>
            <tr>
                <th style="width:50px;">ID</th>
                <th style="width:130px;">Module</th>
                <th style="width:90px;">Type</th>
                <th>Content</th>
                <th style="width:130px;">Added</th>
                <th style="width:120px;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $kb_entries as $i => $entry ) :
            $ts = $type_badge[ $entry->entry_type ] ?? $type_badge['General'];
        ?>
        <tr id="spine-kb-row-<?php echo esc_attr( $entry->id ); ?>"
            style="<?php echo ( $i % 2 === 0 ) ? '' : 'background:#f9fafb;'; ?>">
            <td style="font-size:12px;color:#9ca3af;"><?php echo esc_html( $entry->id ); ?></td>
            <td>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=spine-chat-kb&module=' . urlencode( $entry->module ) ) ); ?>"
                   style="font-size:12px;font-weight:600;color:#374151;text-decoration:none;">
                    <?php echo esc_html( $entry->module ); ?>
                </a>
            </td>
            <td>
                <span style="<?php echo esc_attr( $ts ); ?>padding:2px 7px;border-radius:4px;font-size:11px;font-weight:600;">
                    <?php echo esc_html( $entry->entry_type ); ?>
                </span>
            </td>
            <td style="font-size:13px;color:#374151;word-break:break-word;line-height:1.5;">
                <!-- View mode -->
                <div class="spine-kb-content-view" id="spine-kb-content-view-<?php echo esc_attr( $entry->id ); ?>">
                    <?php echo nl2br( esc_html( wp_trim_words( $entry->content, 40, '…' ) ) ); ?>
                    <?php if ( str_word_count( $entry->content ) > 40 ) : ?>
                    <a href="#" onclick="spineKbToggleFull(<?php echo esc_attr( $entry->id ); ?>,event)" style="font-size:12px;">Show more</a>
                    <div class="spine-kb-full-content" id="spine-kb-full-<?php echo esc_attr( $entry->id ); ?>" style="display:none;">
                        <?php echo nl2br( esc_html( $entry->content ) ); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- Edit mode -->
                <div class="spine-kb-content-edit" id="spine-kb-content-edit-<?php echo esc_attr( $entry->id ); ?>" style="display:none;">
                    <textarea id="spine-kb-edit-content-<?php echo esc_attr( $entry->id ); ?>"
                              rows="5"
                              style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:5px;font-size:13px;resize:vertical;"><?php echo esc_textarea( $entry->content ); ?></textarea>
                    <div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap;">
                        <input type="text" id="spine-kb-edit-module-<?php echo esc_attr( $entry->id ); ?>"
                               value="<?php echo esc_attr( $entry->module ); ?>"
                               placeholder="Module"
                               style="padding:5px 8px;border:1px solid #d1d5db;border-radius:5px;font-size:12px;width:140px;">
                        <select id="spine-kb-edit-type-<?php echo esc_attr( $entry->id ); ?>"
                                style="padding:5px 8px;border:1px solid #d1d5db;border-radius:5px;font-size:12px;">
                            <?php foreach ( $entry_types as $t ) : ?>
                            <option value="<?php echo esc_attr( $t ); ?>"<?php selected( $entry->entry_type, $t ); ?>><?php echo esc_html( $t ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="button button-primary button-small" onclick="spineKbSaveEdit(<?php echo esc_attr( $entry->id ); ?>)">Save</button>
                        <button class="button button-small" onclick="spineKbCancelEdit(<?php echo esc_attr( $entry->id ); ?>)">Cancel</button>
                    </div>
                </div>
            </td>
            <td style="font-size:11px;color:#9ca3af;"><?php echo esc_html( date_i18n( 'd M Y', strtotime( $entry->created_at ) ) ); ?></td>
            <td>
                <button class="button button-small" style="margin-bottom:4px;" onclick="spineKbStartEdit(<?php echo esc_attr( $entry->id ); ?>)">Edit</button>
                <button class="button button-small spine-kb-delete-btn" onclick="spineKbDelete(<?php echo esc_attr( $entry->id ); ?>,this)">Delete</button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- ── Pagination ──────────────────────────────────────────────────── -->
    <?php if ( $total_pages > 1 ) : ?>
    <div style="margin-top:16px;display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
        <?php for ( $p = 1; $p <= $total_pages; $p++ ) :
            $url = admin_url( 'admin.php?page=spine-chat-kb&kb_page=' . $p . ( $filter_mod ? '&module=' . urlencode( $filter_mod ) : '' ) );
        ?>
        <a href="<?php echo esc_url( $url ); ?>"
           class="button<?php echo $p === $page ? ' button-primary' : ''; ?>"
           style="font-size:12px;"><?php echo esc_html( $p ); ?></a>
        <?php endfor; ?>
        <span style="font-size:12px;color:#9ca3af;">Page <?php echo esc_html( $page ); ?> of <?php echo esc_html( $total_pages ); ?></span>
    </div>
    <?php endif; ?>

    <?php endif; ?>

</div><!-- .wrap -->

<script>
(function($) {
    var AJAX  = <?php echo wp_json_encode( $ajax_url ); ?>;
    var NONCE = <?php echo wp_json_encode( $nonce ); ?>;

    // ── Add form ────────────────────────────────────────────────────────────
    window.spineKbShowAddForm = function() {
        document.getElementById('spine-kb-add-form').style.display = '';
        document.getElementById('spine-kb-add-content').focus();
    };
    window.spineKbHideAddForm = function() {
        document.getElementById('spine-kb-add-form').style.display = 'none';
    };

    window.spineKbAdd = function() {
        var content    = document.getElementById('spine-kb-add-content').value.trim();
        var module     = document.getElementById('spine-kb-add-module').value.trim() || 'General';
        var entry_type = document.getElementById('spine-kb-add-type').value;
        var statusEl   = document.getElementById('spine-kb-add-status');

        if ( content.length < 10 ) {
            spineKbStatus(statusEl, 'Content must be at least 10 characters.', 'error');
            return;
        }

        spineKbStatus(statusEl, 'Saving…', 'info');

        $.post(AJAX, { action:'spine_kb_add', nonce:NONCE, content:content, module:module, entry_type:entry_type })
          .done(function(r) {
              if (r.success) {
                  spineKbStatus(statusEl, '✓ Entry added. Reloading…', 'success');
                  setTimeout(function(){ location.reload(); }, 800);
              } else {
                  spineKbStatus(statusEl, r.data.message || 'Error saving entry.', 'error');
              }
          })
          .fail(function(){ spineKbStatus(statusEl, 'Network error.', 'error'); });
    };

    // ── Edit inline ─────────────────────────────────────────────────────────
    window.spineKbStartEdit = function(id) {
        document.getElementById('spine-kb-content-view-' + id).style.display = 'none';
        document.getElementById('spine-kb-content-edit-' + id).style.display = '';
    };
    window.spineKbCancelEdit = function(id) {
        document.getElementById('spine-kb-content-view-' + id).style.display = '';
        document.getElementById('spine-kb-content-edit-' + id).style.display = 'none';
    };
    window.spineKbSaveEdit = function(id) {
        var content    = document.getElementById('spine-kb-edit-content-' + id).value.trim();
        var module     = document.getElementById('spine-kb-edit-module-'  + id).value.trim() || 'General';
        var entry_type = document.getElementById('spine-kb-edit-type-'    + id).value;

        if ( content.length < 10 ) { alert('Content too short.'); return; }

        var row = document.getElementById('spine-kb-row-' + id);
        row.style.opacity = '0.5';

        $.post(AJAX, { action:'spine_kb_update', nonce:NONCE, id:id, content:content, module:module, entry_type:entry_type })
          .done(function(r) {
              row.style.opacity = '1';
              if (r.success) {
                  location.reload();
              } else {
                  alert(r.data.message || 'Error updating entry.');
              }
          })
          .fail(function(){ row.style.opacity='1'; alert('Network error.'); });
    };

    // ── Delete ──────────────────────────────────────────────────────────────
    window.spineKbDelete = function(id, btn) {
        if ( !confirm('Delete this KB entry permanently?') ) return;
        btn.disabled = true;
        $.post(AJAX, { action:'spine_kb_delete', nonce:NONCE, id:id })
          .done(function(r) {
              if (r.success) {
                  var row = document.getElementById('spine-kb-row-' + id);
                  row.style.transition = 'opacity .3s';
                  row.style.opacity = '0';
                  setTimeout(function(){ row.remove(); }, 300);
                  var tot = document.getElementById('spine-kb-total');
                  if (tot) tot.textContent = parseInt(tot.textContent, 10) - 1;
              } else {
                  btn.disabled = false;
                  alert(r.data.message || 'Error.');
              }
          })
          .fail(function(){ btn.disabled = false; alert('Network error.'); });
    };

    // ── Seed legacy KB ──────────────────────────────────────────────────────
    window.spineKbSeed = function() {
        var btn = document.getElementById('spine-kb-seed-btn');
        if (!confirm('Import all entries from the legacy static knowledge base into the database? This only runs if the KB table is currently empty.')) return;
        btn.disabled = true;
        btn.textContent = 'Importing…';
        $.post(AJAX, { action:'spine_kb_seed', nonce:NONCE })
          .done(function(r) {
              btn.disabled = false;
              btn.textContent = 'Import from Legacy KB';
              alert(r.success ? r.data.message : (r.data.message || 'Error.'));
              if (r.success && r.data.imported > 0) location.reload();
          })
          .fail(function(){ btn.disabled=false; btn.textContent='Import from Legacy KB'; alert('Network error.'); });
    };

    // ── File import ─────────────────────────────────────────────────────────
    window.spineKbHandleDrop = function(e) {
        e.preventDefault();
        document.getElementById('spine-kb-drop-zone').style.borderColor = '#d1d5db';
        var file = e.dataTransfer.files[0];
        if (file) spineKbUploadFile(file);
    };

    window.spineKbUploadFile = function(file) {
        if (!file) return;
        var allowed = ['application/pdf',
                       'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                       'text/plain'];
        if (allowed.indexOf(file.type) === -1 && !/\.(pdf|docx|txt)$/i.test(file.name)) {
            spineKbStatus(document.getElementById('spine-kb-import-status'), 'Only PDF, DOCX, and TXT files are allowed.', 'error');
            return;
        }

        var modEl  = document.getElementById('spine-kb-import-module');
        var module = modEl.value === '__new__'
            ? (prompt('Enter new module name:') || 'General')
            : modEl.value;
        var entry_type = document.getElementById('spine-kb-import-type').value;

        var statusEl = document.getElementById('spine-kb-import-status');
        spineKbStatus(statusEl, 'Uploading and parsing ' + file.name + '…', 'info');

        var fd = new FormData();
        fd.append('action',     'spine_kb_import_file');
        fd.append('nonce',      NONCE);
        fd.append('file',       file);
        fd.append('module',     module);
        fd.append('entry_type', entry_type);

        $.ajax({ url:AJAX, type:'POST', data:fd, processData:false, contentType:false })
          .done(function(r) {
              if (r.success) {
                  spineKbStatus(statusEl, '✓ ' + r.data.message, 'success');
                  setTimeout(function(){ location.reload(); }, 1200);
              } else {
                  spineKbStatus(statusEl, r.data.message || 'Import failed.', 'error');
              }
          })
          .fail(function(){ spineKbStatus(statusEl, 'Network error during upload.', 'error'); });
    };

    // ── Toggle full content view ────────────────────────────────────────────
    window.spineKbToggleFull = function(id, e) {
        e.preventDefault();
        var el   = document.getElementById('spine-kb-full-' + id);
        var link = e.target;
        if (el.style.display === 'none') {
            el.style.display = '';
            link.textContent = 'Show less';
        } else {
            el.style.display = 'none';
            link.textContent = 'Show more';
        }
    };

    // ── Status helper ───────────────────────────────────────────────────────
    function spineKbStatus(el, msg, type) {
        var colors = { info:'#374151', success:'#16a34a', error:'#dc2626' };
        el.style.display   = '';
        el.style.color     = colors[type] || colors.info;
        el.textContent     = msg;
    }

})(jQuery);
</script>
