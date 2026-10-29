=== AstroPress ===
Contributors: iamgrisma
Donate link: https://grisma.com.np
Tags: headless, astro, cloudflare pages, cloudflare workers, cloudflare r2, webp, resmush, edge cache, cache warmer, rank math
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.0.11
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Universal WordPress Headless Bridge & Native Cloudflare R2 Offloader for Astro on Cloudflare Pages and Cloudflare Workers.

== Description ==

**AstroPress** is the ultimate unified companion plugin for WordPress sites paired with an Astro frontend hosted on Cloudflare Pages or Cloudflare Workers, featuring native Cloudflare R2 media offloading.

It seamlessly combines:
1. **Direct Cloudflare Edge POP Purge**: Eliminates middleman webhooks and shared secrets. Purges Cloudflare Zone POP Edge caches directly via Cloudflare API v4 on content changes.
2. **Cloudflare Ruleset Cache Rules Manager**: Direct management of modern Cloudflare Ruleset Engine Zone Cache Rules (`http_request_cache_settings`), with 1-click presets for Astro SSR, Static Assets, and R2 Media CDN.
3. **Gated Setup Wizard Lifecycle**: 3-step setup with pre-filled scoped token creator, auto-derived root domain frontend URL, and media mode selection.
4. **Cloudflare 1-Click Quick Connect & Auto-Discovery**: Auto-discovers Account ID, matching Zone ID, and R2 Buckets with 24-hour persistent transient caching.
5. **Pure PHP Cloudflare R2 Offloader**: Lightweight, pure PHP AWS SigV4 implementation with zero heavy AWS SDKs or third-party bloat (< 35KB footprint).
6. **Smart Dual-Stage Image Optimizer & Gateway Routing**: Dual-engine image compression with smart 5MB threshold routing (reSmush.it cloud API for < 5MB with zero server CPU load, and server GD/Imagick for >= 5MB to prevent API rejection), on-the-fly WebP and AVIF conversion, responsive srcset rewriting, EXIF stripping, and Gutenberg block inspector controls (Option B).
7. **Rank Math & Yoast SEO Bridge**: Complete headless SEO delivery exposing fully-rendered `<head>`, structured JSON-LD schema, estimated reading times, and dynamic redirects via REST API.
8. **Danger Zone Disconnect & Reset**: 1-click credential wipe and clean reset to fresh installation state.
9. **Native GitHub Updates & Instant Rollback**: Built-in update pipeline powered by GitHub Releases (`iamgrisma/astropress`).

== Installation ==

1. Download `astropress.zip` from the latest release at https://github.com/iamgrisma/astropress/releases.
2. Go to WordPress Admin -> **Plugins** -> **Add New Plugin** -> **Upload Plugin**.
3. Upload `astropress.zip` and click **Install Now**.
4. Activate the plugin and navigate to **AstroPress** in the admin sidebar.

== Changelog ==

= 2.0.11 =
* Strict SSL Certificate Verification: Enforced default HTTPS TLS certificate validation (`sslverify => true`) across all remote HTTP requests (warmer sitemap crawling, frontend detection, and CDN health auditing) with an extensible `astropress_sslverify` filter.
* Autoload Option Protection: Fixed background warmer job state options to explicitly disable WordPress autoload (`autoload = false`), preventing large URL queue arrays from degrading memory on every request.
* Bounded SQL Queries with Pagination: Added limits and cursor chunking to post, page, and taxonomy queries in the cache warmer and database migration tools, preventing PHP out-of-memory errors on large sites.
* Custom Post Type (CPT) & Taxonomy Invalidation: Added extensible `astropress_supported_post_types` filter and dynamic taxonomy resolution, enabling automatic edge cache purging across all custom post types and custom taxonomy terms.
* Complete Uninstall Cleanup: Updated uninstall lifecycle to purge all active `astropress_*` configuration keys, unschedule active background cron workers, and drop modern postmeta keys upon plugin deletion.

= 2.0.10 =
* Fixed Temp File Extension Detection for reSmush.it API: Resolved an issue where upload preview and test tools passed extensionless PHP temp files (`/tmp/phpXXXXXX`), causing reSmush.it to reject uploads with "files cannot be sent to reSmush.it (it accepts JPG, PNG, GIF, BMP, TIFF only)".
* Binary MIME & Magic Byte Sniffing: Added robust image extension detection (`AstroPress_Optimizer::detect_image_extension`) using original upload filename hints, image magic bytes (`getimagesize`), and EXIF image types (`exif_imagetype`).
* Sanitized Multipart Form Payloads: Ensured all outbound reSmush.it multipart requests carry valid filenames and matching `Content-Type` headers.
* Extension-Suffixed Test Temp Files: Updated AJAX compression tester to operate on extension-suffixed temporary files, ensuring proper format recognition across GD, Imagick, and external optimization APIs.

= 2.0.09 =
* Smart 5MB Dual Engine Routing: Added intelligent payload thresholding where images under 5MB automatically route to the free reSmush.it Cloud API for zero server CPU load, while images 5MB or larger route to Server GD/Imagick to prevent HTTP 413 and API rejection errors.
* Option A (Automated Media Library & External Gates): Streamlined uploads across the WordPress Media Library (`upload.php`, `media-new.php`), REST API, XML-RPC, and CLI into a completely non-blocking, silent pipeline with zero queue freezing.
* Option B (Gutenberg Block Inspector Extension): Added an AstroPress Image Inspector panel for `core/image` blocks in Gutenberg, providing real-time compression metrics (bytes and percentage saved), Cloudflare R2 sync status, format selection, quality slider, and 1-click on-demand re-compression.
* Comprehensive Image Optimizer Settings: Expanded the Image Optimizer dashboard tab with granular controls for Primary Engine selection, Smart 5MB routing toggle, output formats (WebP, AVIF, JPEG, Keep Original), quick presets (Balanced, High Quality, Aggressive, Lossless), max width/height downscaling, EXIF stripping, auto-orientation, original file backup, and live compression tester.
* Enhanced Media Modal Details Pane: Embedded interactive re-compression and R2 status badges directly into the WordPress Media Modal attachment details sidebar.

= 2.0.08 =
* Deep Architectural Debloat: Retired legacy TopNepali Headless class aliases and shims, eliminating over 640 lines of duplicate and dead code across PHP, JS, and CSS.
* Purged Duplicate Database Writes: Removed unnecessary dual-writes to legacy `topnepali_headless_*` options across all settings forms.
* Clean Cache Warmer Standardization: Fully migrated Warmer UI IDs, classes, and AJAX hooks to the unified `astropress-*` namespace.
* XML Sitemap CDATA Support: Enhanced XML sitemap crawler to robustly extract `<loc>` URLs containing CDATA wrappers or whitespace, resolving "No URLs found for selected scope".
* Background Warmer Progress Sync: Fixed background job progress counter so live batches accurately increment UI counts and progress bar percentages in real time.
* Cleaned 270+ Lines of Dead CSS: Pruned obsolete wizard steps, onboarding banners, and unused selector blocks from admin stylesheets.

= 2.0.04 =
* Radical DOM Flattening: Slashed Setup Wizard DOM nesting depth from 10 nested wrappers down to 2 semantic levels (`.astropress-wizard > .wizard-section`), eliminating container traps and excess wrapper padding.
* Responsive Vertical Checklists: Replaced rigid 3-column CSS grids (`.astropress-cf-resource-grid`, `.astropress-abilities-grid`, `.astropress-preflight-grid`, `.astropress-preset-grid`) with fluid flex vertical checklists (`.astropress-audit-checklist`, `.astropress-flat-list`), preventing card clipping and horizontal overflow on mobile and tablet screens.
* Fluid Metrics & Mode Cards: Restructured metrics and architecture setup modes to use fluid flexbox wrapping (`flex: 1 1 240px`) instead of forced 3-column grid layouts.
* Simplified Pre-Flight Status: Collapsed bulky pre-flight server check cards into an ultra-clean, 1-line verified status banner.

= 2.0.03 =
* Resolved Scoped Token Prefill: Fixed internal permission keys for `cache_settings`, `zone_dns`, `workers_r2`, and `page`, ensuring all 8 essential permissions pre-populate accurately in Cloudflare's dashboard.
* Actionable Cache Rules Error Detection: Replaced generic "Authentication error" with guided 1-click token re-generation when `Zone > Cache Rules: Edit` scope is absent.
* Smart Token Re-verification: Enabled "Check Connection" to seamlessly re-verify saved credentials without triggering false "Please enter token first" prompts, keeping drawer closed on success and opening on error.
* Fully Responsive Setup Wizard: Eliminated mobile table and button overflows, ensuring 100% viewport containment and zero horizontal scrolling on mobile viewports.
* Retired Legacy Webhook Ping: Streamlined frontend reachability indicator to verify Cloudflare Edge deployment and Astro SSR status cleanly.

= 2.0.02 =
* Setup Wizard Polish: Added masked token connected card with Check Connection and Change Token drawers, preventing raw token exposure.
* Fixed Scope Blueprint Collapse: Eliminated duplicate click listener that caused the blueprint accordion to collapse immediately.
* Enhanced Cloudflare Scope Pre-fill: Corrected permissionGroupKeys template mapping for workers_r2_storage, pages, and page_rules.
* High-Throughput Warmer: Implemented time-budgeted batch loop and ping_cron keepalive, enabling fast warming for 300+ URLs.
* Cache Status Clarity: Added WORKER-HIT and CF-HIT status detection and 1-click Astro SSR Cache Rule deployment.
* Removed Legacy Webhook Test: Replaced obsolete webhook handshake button with direct Cloudflare Zone Cache purge.

= 2.0.01 =
* Core Update Release: Incremented version to ensure seamless one-click WordPress update detection and migration from initial 2.0.0.
* Dynamic Cloudflare Scope Remediation: Pre-selects only missing token permissionGroupKeys in 1-click Cloudflare link when token audit reveals incomplete permissions.
* Warmer Engine & Contract Hardening: Unified response signatures (status/ok and code/success) for background worker and instant UI reporting.
* Dual Invalidation Engine: Synchronized direct Cloudflare Edge POP purge with non-blocking /api/revalidate ping to purge worker isolate RAM and caches.default.
* Responsive & Overflow Fixes: Mobile horizontal scrolling for settings tabs, responsive audit log tables, and zero raw Unicode emojis.

= 2.0.0 =
* Complete Architectural Rehaul to AstroPress v2.0: Clean-slate fresh installation architecture without legacy baggage.
* Single Scoped API Token: One token powers Zone detection, Direct POP Cache Purge, Ruleset Cache Rules, DNS, and R2 Media Offloading. Zero AWS S3 credentials needed.
* Complete Elimination of REVALIDATE_SECRET: Webhook middleman to `/api/revalidate` is permanently removed. WordPress invalidates Cloudflare Edge POP caches directly via Cloudflare API v4 (`POST /zones/{zone_id}/purge_cache`).
* Complete Elimination of Legacy Page Rules: Upgraded exclusively to modern Cloudflare Ruleset Engine Zone Cache Rules (`http_request_cache_settings` phase).
* Gated Setup Wizard Lifecycle: Unconfigured or disconnected installations lock all navigation tabs to the guided 3-step Setup Wizard until setup is completed.
* Cloudflare Ruleset Cache Rules Manager: New dedicated tab for viewing, deploying 1-click presets (Astro Static Assets, Astro SSR Origin Headers, R2 Media CDN), toggling on/off, and deleting cache rules.
* Direct Zone Cache Purge Controls: 1-click Purge All Zone Cache and targeted Purge URLs tools directly inside WordPress.
* Danger Zone (Disconnect & Reset): Safe 1-click reset button in Settings tab to clear all stored credentials, reset setup status, and return to fresh installation state.
* Frontend URL Auto-Derivation & Collision Shield: Automatically extracts root domain (e.g. `wp.example.com` -> `https://example.com`) and strictly blocks configuration where frontend equals WordPress backend.

= 1.3.0 =
* Cloudflare Scoped Token Blueprint Fix: Fixed permissionGroupKeys URL template mapping for Cache Purge (type: "purge") and Cloudflare Pages (key: "page"), preventing Cloudflare dashboard from excluding them during token creation.
* Complete Debloating of Revalidation Secret UX: Eliminated view/copy buttons, eye visibility toggles, and plain text secret exposures. Stored secrets remain masked as credentials with clean Test Connection and Rotate Secret flows.
* Streamlined Headless Bridge Tab: Unified credential management exclusively into the Setup Wizard with read-only security status badge in the Bridge tab.

= 1.2.9 =
* Single Scoped Module Architecture: Centralized all credential input into the Setup Wizard. Completely removed redundant Cloudflare API Token and Zone ID input forms from Headless Bridge and Overview tabs.
* Debloated R2 Pipeline: Eliminated mandatory S3 Access Key ID / Secret Key requirements. R2 operates 100% natively via the single Scoped API Token with bucket discovery, inline bucket creation, and connection testing.
* Subdomain Governance: Strictly validated Custom CDN Domain inputs to ensure only subdomains of the hosted site domain or Astro frontend domain are permitted (both frontend and server-side).
* R2 CDN Edge Tools: Added live CDN Domain Audit, 1-Click Cache Rule deployment, and 1-Click Purge CDN Edge Cache buttons directly inside the Cloudflare R2 tab.

= 1.2.8 =
* Modern Cloudflare Cache Rules: Integrated modern Cloudflare Ruleset Engine (`http_request_cache_settings`), creating and verifying 1-year Edge and Browser TTL rules with automatic fallback to Page Rules.
* 1-Click Purge CDN Edge Cache: Added instantaneous zone & CDN edge purging from both the Setup Wizard and live CDN Domain Audit cards.
* Consolidated Single Pipeline: Eliminated duplicate token input forms from Dashboard/Overview and R2 tabs, standardizing the Setup Wizard as the sole canonical onboarding and credentials hub.
* Scoped Token Audit & Blueprint: Added `Zone > Cache Rules: Edit` scope to the pre-configured token template URL, clipboard recipe, and real-time permission healthcheck.
* Delegated Event Listeners: Ensured dynamic buttons (Deploy Cache Rule, Purge CDN Cache, Attach R2 Domain) work seamlessly within async audit containers.

= 1.2.7 =
* Push-Based Snapshot Updates: Automatically serializes and embeds full REST post and page payloads (`/wp/v2/posts/:id` and `/wp/v2/pages/:id` with `_embed=true`) directly in revalidation webhook payloads, feeding Astro SSR in-memory snapshot caches even when edge subrequests are firewalled.
* Cloudflare Token Forwarding: Forwards configured Cloudflare API Bearer token and Zone ID in webhook payloads and `x-cf-api-token` request headers, allowing edge workers to orchestrate secondary CDN purges seamlessly.
* Cross-Plugin Compatibility: Added bi-directional option fallbacks for `topnepali_headless_cf_zone_id` and `topnepali_headless_cf_api_token`.
* Cloudflare Edge Purge by Default: Enabled direct Cloudflare Zone API purging by default whenever Zone ID and API Token credentials are configured.

= 1.2.6 =
* Security & Privacy: Completely eliminated plaintext secret exposure and DOM attributes (`data-secret`); Revalidation Secret is strictly concealed and AES-256 encrypted at rest.
* Test & Rotate Pattern: Implemented seamless server-side `[Test Connection]` with automatic 401 failover to on-demand Rotate drawer; existing secrets continue untouched if left unchanged.
* Cloudflare Scoped Token Audit: Comprehensive real-time verification across all 7 permissions (`Cache Purge: Purge`, `Cache Rules: Edit`, `DNS: Edit`, `Zone: Read`, `Workers R2 Storage: Edit`, `Cloudflare Pages: Edit`, `Account Settings: Read`).
* DNS CNAME Automation: Integrated automatic Cloudflare DNS CNAME record verification and edge proxying (`proxied: true`) when attaching custom CDN domains to R2.
* Unified Architecture: Standardized Setup Wizard as the master configuration pipeline supporting both single scoped tokens and granular S3-compatible credentials with comprehensive inline documentation.

= 1.2.5 =
* Security Audit & Hardening: Patched CSRF vulnerabilities across administrative AJAX endpoints with mandatory multi-nonce verification. Added strict path traversal containment checks in delete_local_files to prevent arbitrary file deletion outside wp-content/uploads.
* SSRF Guard: Introduced AstroPress_Security::is_safe_external_url to block outbound requests to internal loopback and reserved private IP ranges.
* Handshake Testing in Setup Wizard: Added instant 1-click webhook handshake testing in Step 1 of the Setup Wizard.
* Backend Protection: Disallowed XML-RPC pingback attacks, blocked author query string enumeration, and enforced baseline X-Content-Type-Options: nosniff and X-Frame-Options: SAMEORIGIN headers.
* Frontend Security: Removed hardcoded Cloudflare Zone IDs and default secret fallbacks from Grisma-AstroPress /api/revalidate.

= 1.2.4 =
* Automatic Activation Redirect: New installations immediately redirect to the Setup Wizard on plugin activation for seamless zero-friction onboarding.
* Live Media Health Audit: Added automated HEAD inspection of offloaded media files against your Cloudflare CDN domain in the Media Sync dashboard, identifying missing or healthy assets and auditing local server copies.
* Full Frontend Synchronization: Fully synced with Grisma-AstroPress frontend engine for dynamic backend host derivation, tester endpoints, and CDN image rendering.

= 1.2.3 =
* Flexible Architecture Modes: Introduced 1-Click Full Architecture mode (Astro Pages frontend, Cloudflare Edge, cache purge, cache warmer, R2 media storage, CDN cache rules, subdomain protection) and Modular/Selective mode for users requiring targeted submodules.
* Zero-Touch Cloudflare Pages Secret Sync: Direct push of REVALIDATE_SECRET, WP_API_URL, and API_URL directly into Cloudflare Pages Production & Preview deployment configs via Cloudflare REST API (eliminating manual dashboard copy-pasting).
* Live Frontend Auto-Detection: Real-time URL detector measuring ping latency, HTTP status code, Cloudflare Edge ray headers, Astro framework presence, and /api/revalidate webhook endpoint reachability.
* Public CDN Domain & Edge Cache Rule Audit: Automated inspection of DNS proxying, R2 bucket custom domain attachment, and 1-Year Cache Everything rules, featuring 1-click domain attachment and rule deployment.
* Cloudflare DNS Scoped Permission Integration: Added Zone > DNS: Edit to the token permission blueprint and live quick connect audit for seamless custom CDN domain attachment.
* Universal Astro Backend API Resolver: Added production-ready TypeScript snippet supporting explicit WP_API_URL / API_URL environment variables, tester worker endpoints, and dynamic fallback to https://wp.{rootDomain}.

= 1.2.2 =
* Unified Single-Token Architecture: Support native Cloudflare Bearer REST API for R2 object storage, uploads, deletions, and bucket discovery—eliminating mandatory S3 credentials for normal users while maintaining full SigV4 compatibility.
* Complete Revalidation Secret Concealment: Masked AES-256 encrypted status panel with 1-click Copy Secret button, Change Key toggle, and password input with eye visibility toggle across both Setup Wizard and Bridge settings.
* Deep Token Abilities & Health Audit: Added comprehensive pre-execution permission auditing testing Account, Zone, Cache Purge, Page/Cache Rules, R2 Storage, and Pages permissions before attempting edge operations.
* Resilient Edge Rule Deployment: Added permission check before rule deployment, duplicate rule detection, and automatic fallback from Page Rules to Modern Cache Rules (Rulesets API `http_request_cache_settings`) if plan limits are reached.
* Cloudflare Scoped Token Blueprint: Added accurate `permissionGroupKeys` URL template parameters alongside an interactive 1-click "Copy Permission Blueprint" recipe table.

= 1.2.1 =
* Restored dedicated SEO & Security settings tab with full Rank Math schema bridge, permalink rewrites, and Subdomain 301 Protection Shield.
* Restored full Edge Cache Warmer capabilities with 12 scope presets, request gap throttling, live edge activity console, and persistent WP-Cron background worker.
* Fixed fatal undefined method call `AstroPress_Encryption::is_encrypted()`.
* Added exception shielding to master tab renderer to prevent site-wide fatal crashes.
* Completely removed `overflow-x: hidden` and implemented fluid responsive layouts for WordPress mobile viewports.
* Standardized R2 bucket discovery dropdown, manual input toggle, inline bucket creation, and CDN edge cache rule across all views.
* Added resilient multi-nonce verification for all background and interactive AJAX requests.

= 1.2.0 =
* Complete UI/UX redesign with secret key concealment and dynamic rotation.
* Added Cloudflare R2 bucket discovery, manual input toggle, and inline bucket creation.
* Added 1-Click Cloudflare Edge Cache Everything rule deployment for custom media domains.
* Full media sync parity with existing offload records import and verified file cleanup.

= 1.1.0 =
* Added Direct Cloudflare Zone Cache API purge (`purge_everything` and targeted URL files).
* Added Cloudflare Pages / Workers Deploy Hook trigger on full cache purge.
* Added Debounced Micro-Queue on PHP shutdown to prevent webhook request storms during bulk operations.
* Added Extended Site Identity hooks (`site_icon`, `page_on_front`, `page_for_posts`, `show_on_front`, `customize_save_after`).
* Added Webhook Delivery Audit Log & History dashboard with 1-click resend and latency tracking.
* Added Automated Cron Retry Queue with exponential backoff for failed webhook deliveries.
* Added Cloudflare 1-Click Quick Connect Assistant with auto-discovery of Account, Zone, R2 buckets, and Pages projects.

= 1.0.0 =
* Initial unified release merging Headless WordPress Bridge and Cloudflare R2 Offloader.
* Full backward compatibility with legacy `r2_by_grisma` and `topnepali_headless` installations.
