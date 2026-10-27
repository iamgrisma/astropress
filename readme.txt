=== AstroPress ===
Contributors: iamgrisma
Donate link: https://grisma.com.np
Tags: headless, astro, cloudflare pages, cloudflare workers, cloudflare r2, webp, resmush, edge cache, cache warmer, rank math
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Universal WordPress Headless Bridge & Native Cloudflare R2 Offloader for Astro on Cloudflare Pages and Cloudflare Workers.

== Description ==

**AstroPress** is the ultimate unified companion plugin for WordPress sites paired with an Astro frontend hosted on Cloudflare Pages or Cloudflare Workers, featuring native Cloudflare R2 media offloading.

It seamlessly combines:
1. **Headless Invalidation & Edge Cache Warmer**: Real-time non-blocking webhooks to `/api/revalidate` on post/page save, menu edits, and taxonomy updates. Pre-warms edge POP caches in the background.
2. **Pure PHP Cloudflare R2 Offloader**: Lightweight, pure PHP AWS SigV4 implementation with zero heavy AWS SDKs or third-party bloat (< 35KB footprint).
3. **Dual-Stage Image Optimizer**: Dual-engine image compression (reSmush.it cloud API + local GD/Imagick fallback), on-the-fly WebP and AVIF conversion, responsive srcset rewriting, and EXIF stripping.
4. **Rank Math & Yoast SEO Bridge**: Complete headless SEO delivery exposing fully-rendered `<head>`, structured JSON-LD schema, estimated reading times, and dynamic redirects via REST API.
5. **Native GitHub Updates & Instant Rollback**: Built-in update pipeline powered by GitHub Releases (`iamgrisma/astropress`) that hooks into WordPress Core's native `Plugin_Upgrader` sandbox with 1-click upgrades and rollbacks.

== Installation ==

1. Download `astropress.zip` from the latest release at https://github.com/iamgrisma/astropress/releases.
2. Go to WordPress Admin -> **Plugins** -> **Add New Plugin** -> **Upload Plugin**.
3. Upload `astropress.zip` and click **Install Now**.
4. Activate the plugin and navigate to **AstroPress** in the admin sidebar.

== Changelog ==

= 1.0.0 =
* Initial unified release merging Headless WordPress Bridge and Cloudflare R2 Offloader.
* Full backward compatibility with legacy `r2_by_grisma` and `topnepali_headless` installations.
