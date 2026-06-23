<?php

declare(strict_types=1);

namespace JeffersonGoncalves\FaviconProxy\Http\Controllers;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Same-origin favicon proxy. Instead of pointing an external-link <img> straight
 * at a third-party favicon service (a cross-origin connection + a privacy leak
 * of which links are shown), this fetches the icon server-side once, caches the
 * bytes, and serves them from your own host — the browser never talks to the
 * upstream.
 *
 * Not an SSRF vector: the fetch target host is the fixed configured endpoint
 * (Google S2 by default); the visitor only controls the `domain` query that the
 * upstream itself resolves. Keep `favicon-proxy.endpoint` a trusted constant —
 * never derive it from request input.
 */
class FaviconProxyController
{
    private const TRANSPARENT_PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xb4\x00\x00\x00\x00IEND\xaeB`\x82";

    /**
     * Raster image types only. `image/svg+xml` is deliberately excluded: an SVG
     * served same-origin can carry <script>, so proxying one would turn the
     * cache into a stored-XSS vector. Anything not on this allowlist is rejected.
     *
     * @var array<int, string>
     */
    private const ALLOWED_TYPES = [
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
        'image/x-icon',
        'image/vnd.microsoft.icon',
    ];

    public function __invoke(Request $request): Response
    {
        $domain = strtolower((string) $request->query((string) config('favicon-proxy.query', 'domain'), ''));

        // Reject anything that isn't a bare hostname before it reaches the
        // upstream URL (defence-in-depth even though the host is fixed).
        if ($domain === '' || filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            return $this->fallback();
        }

        $key = (string) config('favicon-proxy.cache_prefix', 'favicon').':'.$domain;

        // Cache::remember treats null as a miss, so a failing domain would
        // re-hit the upstream on every request. Store a `false` sentinel for
        // failures (short negative TTL) so they aren't re-fetched.
        $icon = Cache::get($key);

        if ($icon === null) {
            $icon = $this->resolve($key, $domain);
        }

        if (! is_array($icon)) {
            return $this->fallback();
        }

        return response($icon['body'], 200)
            ->header('Content-Type', $icon['type'])
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'public, max-age=2592000, immutable');
    }

    /**
     * Fetch + cache the icon for a cache miss, serialised with a per-domain lock
     * so a burst of concurrent misses for the same domain hits the upstream once
     * (cache-stampede guard) instead of N times.
     *
     * @return array{body: string, type: string}|false
     */
    private function resolve(string $key, string $domain): array|false
    {
        try {
            $lock = Cache::lock($key.':lock', 10);
        } catch (Throwable) {
            // The active cache store does not support atomic locks; fall back to
            // an unsynchronised fetch + cache.
            return $this->fetchAndStore($key, $domain);
        }

        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            // Another worker is already fetching this domain. Serve a one-off
            // fetch without writing the cache so we neither stampede the
            // upstream nor block the request.
            return $this->fetch($domain) ?? false;
        }

        try {
            // Re-check inside the lock: the worker we waited on may already have
            // populated the cache.
            $cached = Cache::get($key);

            if ($cached !== null) {
                return is_array($cached) ? $cached : false;
            }

            return $this->fetchAndStore($key, $domain);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{body: string, type: string}|false
     */
    private function fetchAndStore(string $key, string $domain): array|false
    {
        $fetched = $this->fetch($domain);

        $ttl = $fetched !== null
            ? now()->addDays((int) config('favicon-proxy.cache_days', 30))
            : now()->addHours((int) config('favicon-proxy.negative_cache_hours', 6));

        Cache::put($key, $fetched ?? false, $ttl);

        return $fetched ?? false;
    }

    /**
     * @return array{body: string, type: string}|null
     */
    private function fetch(string $domain): ?array
    {
        $maxBytes = max(1, (int) config('favicon-proxy.max_bytes', 102400));

        try {
            $response = Http::timeout((int) config('favicon-proxy.timeout', 6))
                ->withOptions([
                    // Abort the transfer as soon as the upstream advertises a
                    // body larger than the cap, before the bytes are buffered
                    // into memory (DoS guard for an oversized response).
                    'on_headers' => function (ResponseInterface $response) use ($maxBytes): void {
                        $length = $response->getHeaderLine('Content-Length');

                        if ($length !== '' && (int) $length > $maxBytes) {
                            throw new RuntimeException('Favicon response exceeds the configured size cap.');
                        }
                    },
                ])
                ->get((string) config('favicon-proxy.endpoint', 'https://www.google.com/s2/favicons'), [
                    (string) config('favicon-proxy.query', 'domain') => $domain,
                    'sz' => (int) config('favicon-proxy.size', 64),
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful() || $response->body() === '') {
            return null;
        }

        // Reject bodies over the cap as a backstop for chunked responses that
        // never sent a Content-Length the on_headers guard above could check.
        if (strlen($response->body()) > $maxBytes) {
            return null;
        }

        // Only cache + serve allowlisted raster images, so neither a non-image
        // upstream response nor an SVG (which can carry script) can ever be
        // served back under an image content-type (stored-XSS guard).
        $type = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));

        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            return null;
        }

        return [
            'body' => $response->body(),
            'type' => $type,
        ];
    }

    private function fallback(): Response
    {
        // 1×1 transparent PNG — keeps the decorative <img> from showing a
        // broken-image glyph when the upstream icon is unavailable.
        return response(self::TRANSPARENT_PNG, 200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
