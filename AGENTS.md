# AstroPress Plugin Architecture & AI Coding Guidelines (AGENTS.md)

> **NOTICE FOR AI ASSISTANTS & DEVELOPERS:**  
> This file is the single source of truth for the **AstroPress** companion WordPress plugin. Read this document thoroughly before inspecting, modifying, or creating releases.

---

## 1. Plugin Overview & Purpose

- **Name:** AstroPress
- **Repository:** `https://github.com/iamgrisma/astropress`
- **Current Version:** `2.4.9`
- **Role:** Enterprise WordPress companion suite for Astro headless websites deployed on Cloudflare Pages and Workers.
- **Core Pillars:**
  1. Multi-Tier Cache Invalidation & On-Demand Revalidation (`includes/class-cache.php`).
  2. Pure PHP AWS SigV4 Cloudflare R2 Offloading (`includes/class-r2-client.php`, `class-media-handler.php`).
  3. Next-Gen Image Optimization (WebP/AVIF via reSmush.it & GD/Imagick fallback in `includes/class-optimizer.php`).
  4. Rank Math & Yoast Headless SEO Bridge (`includes/class-seo.php`).
  5. Native GitHub Core Updates & Sandboxed Rollback (`includes/class-updater.php`).

---

## 2. Multi-Tier Caching & Invalidation Architecture

### The Cascade on Post Update / Trash (`on_save_post`)
When a post or taxonomy is updated:
1. **Tier 1 (Origin Sync):**
   - Calls `litespeed_purge_all` and `headless_litespeed_auto_purge()`.
   - Sends `X-LiteSpeed-Purge: *` header to invalidate LiteSpeed REST cache and flushes Redis object cache.
2. **Tier 2 (Cloudflare Global Edge CDN Purge):**
   - Directly calls Cloudflare Zone Purge API (`POST /zones/{zone_id}/purge_cache`).
   - Purges exact canonical URLs with and without trailing slash across all 330+ Cloudflare data centers in <150ms.
3. **Tier 3 (Astro Worker Invalidation & Edge Pre-Warming):**
   - Authenticated POST to `https://{frontend_url}/api/revalidate` with `x-astropress-ticket` and `x-revalidate-secret`.
   - The Astro Worker invalidates in-memory query cache and immediately fires HTTP GET requests with `x-astropress-warmer: 1` to warm the canonical edge cache.
4. **Self-Healing Modern Cache Rule Deployment:**
   - Method: `ensure_cache_rule($zone_id, $host, $token)`.
   - During CDN audits or preflight checks, AstroPress checks the Cloudflare Ruleset Engine (`http_request_cache_settings`).
   - If missing, it programmatically deploys the rule `(http.host eq "{host}")` with `edge_ttl: respect_origin` and `browser_ttl: respect_origin`.

---

## 3. Cloudflare R2 Storage (SigV4 Engine)

- **Pure PHP Engine:** 100% native PHP SigV4 request signer in `includes/class-r2-client.php` (<35KB). Zero heavy AWS SDK dependencies.
- **Storage Modes:**
  - `Dual Storage`: Retains local server copy and mirrors to R2 bucket.
  - `R2 Only`: Deletes local server media file upon successful R2 upload to save origin disk space.
- **URL Rewriting:** Rewrites `wp-content/uploads/` URLs in REST API, post content, and image metadata to the custom R2 CDN domain (`objects.{domain}`).

---

## 4. Rank Math & Headless SEO Bridge

- **Headless Variable Resolution:** In headless mode, Rank Math template variables (`%title%`, `%currentyear%`, `%sep%`) fail to resolve when WordPress loops are bypassed. AstroPress pre-processes and resolves these variables in `resolve_seo_template_vars()` before rendering `<head>`.
- **Rendered `<head>` REST Endpoint:** Exposes rendered SEO `<head>` tags directly on `/wp/v2/posts`, `/wp/v2/pages`, and `/wp-json/astropress/v1/head`.

---

## 5. Development & Release Workflow

1. **Version Bumping:**
   - Update `Version: x.y.z` in `astropress.php` header.
   - Update `ASTROPRESS_VERSION` constant in `astropress.php`.
2. **Syntax Validation:**
   - Run `find . -name "*.php" -exec php -l {} +` to ensure zero syntax errors before committing.
3. **Release Pipeline:**
   - Commit and tag: `git tag vx.y.z && git push origin main && git push origin vx.y.z`.
   - Create clean zip: `zip -r -q /tmp/astropress.zip astropress -x "astropress/.git/*" "astropress/node_modules/*"`.
   - Create GitHub release with `gh release create vx.y.z /tmp/astropress.zip --repo iamgrisma/astropress`.
4. **Production Deployment:**
   - Sync clean files to WordPress plugin directory via rsync.
