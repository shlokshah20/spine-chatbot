# Spine HR Chatbot — Claude Code Project Guide

## What this project is

A self-contained WordPress plugin (`spine-chatbot`) that embeds an AI-powered chat widget on the Spine Technologies website. It uses the Anthropic Claude API (via PHP `wp_remote_post`) to answer prospect and support queries in real time.

**Current version:** 2.1.0  
**GitHub:** https://github.com/shlokshah20/spine-chatbot  
**Local test install:** `/Users/shlokshah/spine website/wordpress/` (served at `localhost:8080`)  
**Live site:** spinetechnologies.com (Plesk-hosted; outbound API currently firewall-blocked — pending hosting team fix)

---

## Architecture in one paragraph

`spine-chatbot.php` is the plugin entry point. It boots a singleton `Spine_Chatbot_Core` which instantiates five sub-systems: `AI`, `Router`, `Leads`, `Ajax`, `Heartbeat`. The frontend is a vanilla JS state machine (`chatbot-script.js`) paired with a scoped CSS design system (`chatbot-style.css`). On message send, JS calls `admin-ajax.php` → `Spine_Chatbot_Ajax` → `Spine_Chatbot_AI` → Anthropic Messages API → agentic tool-use loop → response. No jQuery in the chatbot JS (jQuery only used in admin panel).

---

## File map

```
spine-chatbot.php                          Plugin entry — version constants, boot guard, singleton init
includes/
  class-spine-chatbot-core.php             Orchestrator: hooks, asset enqueue, HTML render
  class-spine-chatbot-ai.php               Anthropic API client — agentic loop, tool execution, system prompt
  class-spine-chatbot-ajax.php             WordPress AJAX handlers (init, send, poll, lead, file upload)
  class-spine-chatbot-router.php           Session state machine (bot|pending_agent|active|closed|...)
  class-spine-chatbot-leads.php            Lead capture + DB write
  class-spine-chatbot-heartbeat.php        2s polling for live agent state changes
  class-spine-chatbot-db.php               dbDelta() schema — runs on activation and DB version bump
  class-spine-chatbot-search.php           FULLTEXT + LIKE fallback KB search
  class-spine-chatbot-kb-importer.php      Bulk import from data/knowledgebase-v1.php
  knowledgebase-v1.php                     (legacy location — canonical copy is data/)
public/
  css/chatbot-style.css                    Complete widget CSS (v2.1 — #0044EB theme, mobile fullscreen)
  js/chatbot-script.js                     Frontend state machine (idle → product_chat)
admin/
  class-spine-chatbot-admin.php            Admin panel bootstrap
  class-spine-chatbot-settings.php         Settings page (API key, bot name, etc.)
  views/                                   Admin page templates
data/
  knowledgebase-v1.php                     123-entry KB — source of truth, DO NOT hand-edit
```

---

## Non-obvious constraints — read before editing

### DB
- `dbDelta()` requires DB version stored as a **string** `'4'`, not integer `4`
- TEXT/BLOB columns must have **no DEFAULT** value in `dbDelta()` schema
- DB version constant: `SPINE_CHATBOT_DB_VERSION = '4'` in `spine-chatbot.php`

### AJAX
- AJAX URL uses `wp_parse_url(admin_url('admin-ajax.php'), PHP_URL_PATH)` — root-relative, never `admin_url()` directly (breaks when siteurl ≠ CDN domain)
- All handlers verify nonce: `spine_chat_nonce`
- JS sends `branch: ''` for all messages (branch-select flow was removed in v2.1)

### AI / Anthropic
- **Model:** `claude-haiku-4-5-20251001` (updated Sept 2026 — `claude-3-5-sonnet-20241022` is deprecated)
- **Workspace-scoped API key required** — non-scoped keys need `anthropic-workspace-id` header (code supports this via `spine_chatbot_anthropic_workspace_id` WP option)
- Agentic loop: max 6 iterations (`MAX_LOOPS = 6`), max 1024 output tokens per call
- Two-stream routing in system prompt: Stream 1 = existing customer support (no KB search), Stream 2 = prospect/feature enquiry (always call `search_knowledge_base` first)
- `[HANDOVER]` prefix in AI response triggers `pending_agent` state

### Frontend JS
- State machine states: `idle | product_chat | requesting_agent | live_agent | lead_form | closed`
- Polling: `POLL_FAST_MS = 2000` (2s) for live agent, `POLL_SLOW_MS = 4000` waiting
- On init: session opens directly into `product_chat` — no branch selection step
- Welcome message: `"Hi! I'm your Spine AI Assistant. How can I help you with our software or services today?"`

### CSS
- All selectors scoped under `#spine-chat-root` / `.spine-chat` — no global styles leak out
- Primary accent: `#0044EB` (set as `--spine-accent` CSS custom property)
- Mobile breakpoint `≤768px`: widget becomes `100vw × 100dvh` fixed fullscreen
- PHP injects an inline `<style>:root{--spine-accent:...}</style>` from WP options — if the stored option is still the old `#1d4ed8`, it will override the CSS file default

### Plugin safety
- Duplicate-load guard at top of `spine-chatbot.php`: `if (defined('SPINE_CHATBOT_VERSION')) { return; }` — never remove this
- Personal email domain blocklist enforced both client-side (JS) AND server-side (PHP) in `book_product_demo` tool

---

## Active copy workflow (local testing)

After editing any PHP or CSS file in the dev repo, copy it to the local WordPress install:

```bash
cp "includes/class-spine-chatbot-ai.php" "/Users/shlokshah/spine website/wordpress/wp-content/plugins/spine-chatbot/includes/class-spine-chatbot-ai.php"
```

Replace `includes/class-spine-chatbot-ai.php` with whichever file was edited. JS and CSS files live under `public/`.

To rebuild the distributable ZIP:
```bash
zip -r spine-chatbot-v2.1.0.zip spine-chatbot.php includes/ public/ admin/ data/
```

---

## Known issues & pending work

| Issue | Status | Resolution |
|---|---|---|
| Live Plesk server blocks outbound 443 to `api.anthropic.com` | Open | User must contact hosting provider to whitelist `api.anthropic.com` on egress |
| KB search doesn't match "HR Software" → "HR Suite" | Open | Add synonym-aware entries to KB (drafted, awaiting user review) |
| `spine_chatbot_anthropic_workspace_id` not in Settings UI | Open | Option exists in DB, but no admin field yet — must set via WP-CLI or DB directly |

---

## WP options reference

| Option key | Default | Purpose |
|---|---|---|
| `spine_chatbot_anthropic_key` | `''` | Anthropic API key (workspace-scoped) |
| `spine_chatbot_anthropic_workspace_id` | `''` | Optional workspace ID header |
| `spine_chatbot_bot_name` | `'Spine Assistant'` | Displayed in header and system prompt |
| `spine_chatbot_welcome_message` | `'Hi! I'm your Spine AI Assistant...'` | First message on widget open |
| `spine_chatbot_support_email` | `'support@spinetechnologies.com'` | Injected into Stream 1 system prompt |
| `spine_chatbot_accent_color` | `'#0044EB'` | CSS `--spine-accent` override via inline style |
| `spine_chatbot_demo_url` | `'https://spinetechnologies.com/request-demo/'` | Book demo CTA link |
| `spine_chatbot_position` | `'bottom-right'` | Widget position (`bottom-right` or `bottom-left`) |
| `spine_chatbot_enabled` | `'1'` | Master on/off switch |
| `spine_chatbot_db_version` | `'4'` | Triggers `dbDelta()` upgrade on mismatch |

---

## Git hygiene

- Branch: `main` (single branch — direct commits)
- Commit attribution: end messages with `Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>`
- ZIP is gitignored — rebuild after each release
- GitHub remote: `https://github.com/shlokshah20/spine-chatbot.git`
- Push requires a valid GitHub PAT with `repo` scope (classic tokens, not fine-grained)

---

## Things to never do

- Do not hardcode the Anthropic API key anywhere in source files
- Do not edit `data/knowledgebase-v1.php` directly — use the WP Admin KB importer
- Do not remove the duplicate-load guard in `spine-chatbot.php`
- Do not add DEFAULT values to TEXT/BLOB columns in `class-spine-chatbot-db.php`
- Do not use `admin_url()` for the AJAX endpoint — use `wp_parse_url()` root-relative path
- Do not change `SPINE_CHATBOT_DB_VERSION` without writing a corresponding migration in `Spine_Chatbot_DB::install()`
