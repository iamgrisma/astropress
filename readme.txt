=== AstroPress ===
Contributors: iamgrisma
Donate link: https://grisma.com.np
Tags: headless, astro, cloudflare pages, cloudflare workers, cloudflare r2, webp, resmush, edge cache, cache warmer, rank math
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Universal WordPress Headless Bridge & Native Cloudflare R2 Offloader for Astro on Cloudflare Pages and Cloudflare Workers.

== Description ==

**AstroPress** is the ultimate unified companion plugin for WordPress sites paired with an Astro frontend hosted on Cloudflare Pages or Cloudflare Workers, featuring native Cloudflare R2 media offloading.

It seamlessly combines:
1. **Headless Invalidation & Edge Cache Warmer**: Real-time non-blocking webhooks to `/api/revalidate` on post/page save, menu edits, site identity changes, and taxonomy updates. Pre-warms edge POP caches in the background.
2. **Direct Cloudflare Edge Purging & Deploy Hooks**: Purge Cloudflare Edge POP caches directly via Cloudflare API v4 and trigger Cloudflare Pages deployment webhooks on full site rebuilds.
3. **Webhook Audit Logging & Automated Retries**: Detailed delivery history table with latency, HTTP response status, URL targets, and 1-click resend, backed by an automated cron retry queue.
4. **Cloudflare 1-Click Quick Connect Assistant**: Auto-discover your Account ID, matching Zone ID, and R2 Buckets in one click using a scoped Cloudflare API Token.
5. **Pure PHP Cloudflare R2 Offloader**: Lightweight, pure PHP AWS SigV4 implementation with zero heavy AWS SDKs or third-party bloat (< 35KB footprint).
6. **Dual-Stage Image Optimizer**: Dual-engine image compression (reSmush.it cloud API + local GD/Imagick fallback), on-the-fly WebP and AVIF conversion, responsive srcset rewriting, and EXIF stripping.
7. **Rank Math & Yoast SEO Bridge**: Complete headless SEO delivery exposing fully-rendered `<head>`, structured JSON-LD schema, estimated reading times, and dynamic redirects via REST API.
8. **Native GitHub Updates & Instant Rollback**: Built-in update pipeline powered by GitHub Releases (`iamgrisma/astropress`) that hooks into WordPress Core's native `Plugin_Upgrader` sandbox with 1-click upgrades and rollbacks.

== Installation ==

1. Download `astropress.zip` from the latest release at https://github.com/iamgrisma/astropress/releases.
2. Go to WordPress Admin -> **Plugins** -> **Add New Plugin** -> **Upload Plugin**.
3. Upload `astropress.zip` and click **Install Now**.
4. Activate the plugin and navigate to **AstroPress** in the admin sidebar.

== Changelog ==

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
