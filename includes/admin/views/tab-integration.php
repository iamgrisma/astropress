<?php
/**
 * Admin View: Astro Integration Snippets Tab
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

        $secret = $this->core->get_secret();
        $custom_domain = AstroPress_Admin::get_r2_option('custom_domain', 'media.example.com');
        ?>
        <div class="astropress-card" style="max-width:900px;">
            <h2><?php esc_html_e('Astro Project Integration Snippets', 'astropress'); ?></h2>
            <p><?php esc_html_e('Copy and paste these lightweight, production-ready code blocks directly into your Astro project to enable instantaneous cache invalidation, edge redirects, and SEO injection.', 'astropress'); ?></p>

            <div class="astropress-snippet-block">
                <div class="astropress-snippet-header">
                    <span>1. Revalidation Endpoint (<code>src/pages/api/revalidate.ts</code>)</span>
                    <button type="button" class="button button-small astropress-copy-btn" data-target="code-revalidate"><?php esc_html_e('Copy', 'astropress'); ?></button>
                </div>
                <pre><code id="code-revalidate">import type { APIRoute } from 'astro';

export const POST: APIRoute = async ({ request, locals }) => {
  const cfEnv = (globalThis as any).__CF_ENV__ || {};
  const secret = request.headers.get('x-revalidate-secret');
  const expectedSecret = cfEnv.REVALIDATE_SECRET || (locals as any)?.runtime?.env?.REVALIDATE_SECRET || process.env.REVALIDATE_SECRET;

  // Reject unauthorized requests if REVALIDATE_SECRET is configured in environment
  if (expectedSecret && secret !== expectedSecret) {
    return new Response(JSON.stringify({ error: 'Unauthorized: Secret mismatch or missing x-revalidate-secret header' }), { status: 401 });
  }

  try {
    const payload = await request.clone().json().catch(() => ({}));
    const { slug, type, action, urls, full_urls, cf_api_token, cf_zone_id } = payload;

    // Instant handshake for AstroPress connection detection
    if (type === 'test' || action === 'ping' || slug === 'test-connection') {
      return new Response(JSON.stringify({ success: true, status: 'connected', framework: 'Astro SSR' }), { status: 200 });
    }

    // Purge affected URLs at Cloudflare Zone Cache API (do not rely on caches.default in Workers)
    const affected = Array.isArray(full_urls) && full_urls.length > 0
      ? full_urls
      : (Array.isArray(urls) ? urls : (slug ? ['/' + slug] : ['/']))
          .map((u: string) => new URL(u, request.url).href);

    if (cf_api_token && cf_zone_id && affected.length > 0) {
      for (let i = 0; i < affected.length; i += 30) {
        await fetch(`https://api.cloudflare.com/client/v4/zones/${cf_zone_id}/purge_cache`, {
          method: 'POST',
          headers: { Authorization: `Bearer ${cf_api_token}`, 'Content-Type': 'application/json' },
          body: JSON.stringify({ files: affected.slice(i, i + 30) }),
        });
      }
    }

    return new Response(JSON.stringify({
      success: true,
      revalidated: true,
      purged: affected,
      timestamp: Date.now()
    }), { status: 200, headers: { 'Content-Type': 'application/json' } });
  } catch (err: any) {
    return new Response(JSON.stringify({ error: err.message }), { status: 400 });
  }
};</code></pre>
            </div>

            <div class="astropress-snippet-block" style="margin-top:20px;">
                <div class="astropress-snippet-header">
                    <span>2. Dynamic Edge Redirection Middleware (<code>src/middleware.ts</code>)</span>
                    <button type="button" class="button button-small astropress-copy-btn" data-target="code-middleware"><?php esc_html_e('Copy', 'astropress'); ?></button>
                </div>
                <pre><code id="code-middleware">import { defineMiddleware } from 'astro:middleware';

export const onRequest = defineMiddleware(async (context, next) => {
  const url = new URL(context.request.url);
  const slug = url.pathname.replace(/^\/|\/$/g, '');

  if (slug && !slug.startsWith('api/') && !slug.includes('.')) {
    try {
      const wpOrigin = '<?php echo esc_js(home_url()); ?>';
      const res = await fetch(`${wpOrigin}/wp-json/astropress/v1/redirection?slug=${encodeURIComponent(slug)}`, {
        cf: { cacheTtl: 300 }
      } as any);

      if (res.ok) {
        const data = await res.json();
        if (data.redirect_url) {
          return context.redirect(data.redirect_url, data.redirect_type || 301);
        }
      }
    } catch (_) {}
  }

  return next();
});</code></pre>
            </div>

            <div class="astropress-snippet-block" style="margin-top:20px;">
                <div class="astropress-snippet-header">
                    <span>3. Cloudflare Environment Variables (<code>.env</code> / <code>wrangler.toml</code>)</span>
                    <button type="button" class="button button-small astropress-copy-btn" data-target="code-env"><?php esc_html_e('Copy', 'astropress'); ?></button>
                </div>
                <pre><code id="code-env"># .env or Cloudflare Pages > Settings > Environment Variables
REVALIDATE_SECRET=<?php echo esc_html($secret ?: 'your_revalidate_secret_token'); ?>

WP_URL=<?php echo esc_html(home_url()); ?>
WP_API_URL=<?php echo esc_html(home_url()); ?>

CDN_MEDIA_URL=https://<?php echo esc_html($custom_domain); ?>
</code></pre>
                <div style="margin-top:8px; font-size:12px; color:#475569; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:8px 12px;">
                    <strong>Zero-Touch Tip:</strong> If your Cloudflare API Token has the Pages permission, you do not need to configure these manually in the Cloudflare dashboard. Use the <strong>"1-Click Sync to Cloudflare Pages"</strong> button in Setup Wizard or Cloudflare tab to push them automatically via API!
                </div>
            </div>

            <div class="astropress-snippet-block" style="margin-top:20px;">
                <div class="astropress-snippet-header">
                    <span>4. Universal WordPress Backend API Resolver (<code>src/lib/wp.ts</code>)</span>
                    <button type="button" class="button button-small astropress-copy-btn" data-target="code-resolver"><?php esc_html_e('Copy', 'astropress'); ?></button>
                </div>
                <pre><code id="code-resolver">/**
 * Universal WordPress API Endpoint Resolver
 *
 * Automatically resolves the active WordPress backend:
 * 1. Honors explicit WP_API_URL or API_URL environment variables (synced via AstroPress 1-Click Sync)
 * 2. Supports custom worker testers / subpaths (e.g. https://shhshsb-maj.wpbackend.tester.dev/xeaa)
 * 3. In request context, falls back dynamically to https://wp.{rootDomain}
 */
export function getWordPressApiUrl(request?: Request): string {
  // 1. Explicit Runtime / Environment Variable (Recommended)
  const cfEnv = typeof globalThis !== 'undefined' ? (globalThis as any).__CF_ENV__ : undefined;
  const envUrl = cfEnv?.WP_URL || cfEnv?.WORDPRESS_URL || cfEnv?.WP_API_URL
    || import.meta.env.WP_URL || import.meta.env.WP_API_URL
    || (typeof process !== 'undefined' ? (process.env.WP_URL || process.env.WP_API_URL || process.env.API_URL) : undefined);
  if (envUrl) {
    return String(envUrl).replace(/\/+$/, '');
  }

  // 2. Derive https://wp.{rootDomain} from current incoming request
  if (request) {
    try {
      const url = new URL(request.url);
      const hostParts = url.hostname.split('.');
      if (hostParts.length >= 2) {
        const rootDomain = hostParts.slice(-2).join('.');
        return `https://wp.${rootDomain}`;
      }
    } catch (_) {}
  }

  // 3. Fallback to Origin
  return '<?php echo esc_js(home_url()); ?>';
}</code></pre>
            </div>
        </div>
