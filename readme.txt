=== AstroPress ===
Contributors: iamgrisma
Donate link: https://grisma.com.np
Tags: headless, astro, cloudflare pages, cloudflare workers, cloudflare r2, webp, resmush, edge cache, cache warmer, rank math
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.2
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
