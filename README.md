# AstroPress

**Universal Headless WordPress & Cloudflare R2 Suite for Astro on Cloudflare Pages and Workers.**

AstroPress seamlessly combines high-performance headless cache invalidation, automated edge cache warming, pure PHP Cloudflare R2 media offloading, on-the-fly WebP/AVIF optimization, Rank Math / Yoast SEO bridge, and a native GitHub core update pipeline into a single, cohesive companion plugin.

---

## Key Architecture & Features

### 1. Instant Edge Cache Invalidation & Revalidation Webhook
- **Event-Driven Purging**: Automatically triggers authenticated revalidation webhooks to your Astro frontend (`/api/revalidate`) when posts, pages, categories, tags, or navigation menus are created, updated, or deleted.
- **Asynchronous & Non-Blocking**: Dispatches HTTP POST requests via non-blocking sockets (`blocking => false`), guaranteeing zero publishing latency in the WordPress block editor.
- **Micro-Queue Debouncing**: Consolidates multiple concurrent edits and bulk updates within a single request onto PHP `shutdown` to eliminate webhook storms.
- **LiteSpeed Cache Sync**: Intercepts LiteSpeed Cache purge actions to keep Cloudflare edge caches in lockstep.

### 2. Direct Cloudflare Edge Invalidation & Deploy Hooks
- **Direct Cloudflare Zone Cache API Purge**: Directly instructs Cloudflare Edge POP nodes to purge files or entire zone caches via Cloudflare API v4.
- **Pages Deploy Hook**: Triggers on-demand static builds in Cloudflare Pages upon full cache purges.
- **Webhook Delivery Audit Log**: Complete delivery history with latency, HTTP response codes, payload preview, and 1-click resend capability.
- **Automated Cron Retries**: Failed webhooks are safely queued with exponential backoff and retried automatically.

### 3. Edge Cache Warmer (Interactive & Background Cron)
- **Zero SSR Cold Boots**: Pre-warms Cloudflare POP edge caches for recently updated content, top categories, and sitemaps so visitors experience instant 20ms page loads.
- **Dual Mode**:
  - **Interactive AJAX Warmer**: Live visual progress bar with granular delay and batch controls.
  - **Background WP-Cron Warmer**: Runs asynchronously in the background with pause, resume, and cancellation support.

### 4. Pure PHP Cloudflare R2 Offloader (Zero AWS SDK Bloat)
- **Pure SigV4 Engine**: 100% native PHP AWS Signature Version 4 implementation (< 35KB footprint) without bloated 50MB vendor SDKs.
- **Dual Storage vs. R2-Only**:
  - `Dual Storage (Recommended)`: Retains local copies alongside Cloudflare R2 backup.
  - `R2 Only (Disk Saver)`: Automatically removes local server media upon successful upload to Cloudflare R2 to conserve server disk space.
- **Live Bucket Discovery**: Discovers and lists R2 buckets under your Cloudflare Account directly inside the admin dashboard.
- **Custom CDN Domain**: Automatically rewrites media URLs, `srcset` attributes, and post content to your custom public CDN domain (e.g. `media.example.com`).

### 5. Dual-Stage Image Optimizer & Next-Gen Formats
- **Dual Engine**: reSmush.it cloud compression API as primary offload engine with automatic, seamless fallback to native Server GD / Imagick.
- **On-the-Fly WebP & AVIF Conversion**: Automatically converts raster images (JPEG, PNG) to lightweight WebP or AVIF format on upload.
- **Dimensions & EXIF**: Automatically downscales oversized camera photos and strips unnecessary EXIF metadata.
- **Upload Interceptor**: Optional visual upload interceptor bar with per-upload format selection.

### 6. Rank Math & Yoast SEO Bridge
- **Rendered `<head>` REST API**: Exposes the fully rendered `<head>` HTML (canonical tags, meta robots, OpenGraph, Twitter Cards, JSON-LD Schema) on `/wp/v2/posts`, `/wp/v2/pages`, and `/wp-json/astropress/v1/head`.
- **Domain Normalization**: Rewrites internal origin URLs in canonical links, OpenGraph tags, and sitemaps directly to the production Astro domain.
- **Dynamic Redirection**: Detects Rank Math 301/302 redirects and exposes them via `/wp-json/astropress/v1/redirection?slug=...` for instant edge redirects in Astro middleware.
- **404 Monitor Log API**: Exposes Rank Math 404 access logs via authenticated REST endpoint for automated monitoring.

### 7. Native GitHub Updates & 1-Click Rollback
- **Direct GitHub Releases Pipeline**: Automatically checks for updates directly from [iamgrisma/astropress](https://github.com/iamgrisma/astropress) releases (`astropress.zip`).
- **Core Upgrader Sandbox**: Uses WordPress Core's native `Plugin_Upgrader` sandbox, eliminating false-positive security warnings from hosting providers.
- **1-Click Rollback**: Switch or rollback to any previous version directly from the admin dashboard.

### 8. Guided Onboarding Wizard & Cloudflare Quick Connect
- **Cloudflare 1-Click Quick Connect**: Auto-discovers Cloudflare Account ID, matching Zone ID, R2 buckets, and Pages projects directly from a scoped API token.
- **Step-by-Step Onboarding**: Streamlined setup wizard guiding you through connecting Astro, entering R2 credentials, selecting image optimization presets, and configuring edge cache rules.
- **Pre-Flight Readiness Check**: Verifies server cryptographic extensions (OpenSSL / libsodium), cURL, GD/Imagick, WebP/AVIF format support, and security salts.
- **Copyable Astro Code Snippets**: Production-ready code templates for Astro API routes (`/api/revalidate.ts`), edge redirection middleware (`src/middleware.ts`), and `.env` configs.

---

## Installation

### Option 1: Native GitHub Release Zip (Recommended)
1. Download `astropress.zip` from the [Latest Release](https://github.com/iamgrisma/astropress/releases/latest).
2. In your WordPress admin, go to **Plugins > Add New Plugin > Upload Plugin**.
3. Choose `astropress.zip` and click **Install Now**.
4. Activate the plugin and navigate to **AstroPress** in the sidebar.

### Option 2: Git Clone
```bash
cd wp-content/plugins
git clone https://github.com/iamgrisma/astropress.git astropress
```

---

## Configuration

### 1. Headless Bridge
- **Astro Frontend URL**: Your production Astro website domain (e.g., `https://example.com`).
- **Revalidation Secret Key**: Secret token shared between WordPress and Cloudflare Pages/Workers (`REVALIDATE_SECRET`).
- Click **Test Handshake** to verify live connectivity.

### 2. Cloudflare R2
- **Cloudflare Account ID**: Found on your Cloudflare dashboard sidebar.
- **R2 Access Key ID & Secret Access Key**: Generated from Cloudflare R2 > Manage R2 API Tokens.
- **R2 Bucket Name**: Select from discovered buckets or enter manually.
- **Custom CDN Domain**: Your custom public domain configured on the R2 bucket (e.g., `media.example.com`).

---

## Astro Integration Example

In your Astro project running on Cloudflare Pages or Workers:

```ts
// src/pages/api/revalidate.ts
import type { APIRoute } from 'astro';

export const POST: APIRoute = async ({ request, locals }) => {
  const payload = await request.clone().json().catch(() => ({}));
  const { slug, type, action, urls } = payload;

  // Instant handshake for AstroPress connection verification
  if (type === 'test' || action === 'ping' || slug === 'test-connection') {
    return new Response(JSON.stringify({ success: true, status: 'connected', framework: 'Astro SSR' }), { status: 200 });
  }

  // Invalidate affected routes from Cloudflare Worker caches.default
  const affected = Array.isArray(urls) ? urls : (slug ? ['/' + slug] : ['/']);
  try {
    const cache = (globalThis as any).caches?.default;
    if (cache) {
      for (const u of affected) {
        await cache.delete(new Request(new URL(u, request.url).href));
      }
    }
  } catch {}

  return new Response(JSON.stringify({ success: true, revalidated: true, purged: affected }), { status: 200 });
};
```

---

## Backward Compatibility

AstroPress is engineered with 100% backward compatibility for existing installations of:
- **R2 by Grisma**: Aliases `R2_By_Grisma`, `r2_by_grisma()`, `R2G_*` classes, `r2g_*` options, and `_r2g_*` postmeta.
- **TopNepali Headless Bridge**: Aliases `TopNepali_Headless`, `topnepali_headless()`, `TopNepali_Headless_*` classes, and `topnepali_headless_*` options.

Existing installations upgrade seamlessly without reconfiguring credentials or breaking database schema.

---

## Author & Developer

- **Author**: Grisma
- **Developer**: Grisma Bhandari
- **Website**: [grisma.com.np](https://grisma.com.np) | [grisma.info.np](https://grisma.info.np)
- **Repository**: [iamgrisma/astropress](https://github.com/iamgrisma/astropress)

---

## License

GPL-2.0-or-later. Created by Grisma Bhandari.
