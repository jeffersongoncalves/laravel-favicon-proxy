<?php

declare(strict_types=1);

return [
    // When false the proxy route is not registered.
    'enabled' => env('FAVICON_PROXY_ENABLED', true),

    // Route path the proxy is served from.
    'path' => env('FAVICON_PROXY_PATH', 'favicon-proxy'),

    // Middleware applied to the proxy route (e.g. throttle, security headers).
    'middleware' => ['throttle:120,1'],

    // Upstream favicon service. Default is Google's S2 endpoint. The request is
    // `{endpoint}?{query}={domain}&sz={size}`.
    'endpoint' => env('FAVICON_PROXY_ENDPOINT', 'https://www.google.com/s2/favicons'),

    // Query-string parameter that carries the domain (both inbound and upstream).
    'query' => 'domain',

    // Requested icon size in pixels.
    'size' => (int) env('FAVICON_PROXY_SIZE', 64),

    // Upstream request timeout in seconds.
    'timeout' => (int) env('FAVICON_PROXY_TIMEOUT', 6),

    // Cache key prefix (`{cache_prefix}:{domain}`).
    'cache_prefix' => 'favicon',

    // How long a fetched icon is cached (days) and how long a failure is
    // negative-cached (hours) before the upstream is retried.
    'cache_days' => (int) env('FAVICON_PROXY_CACHE_DAYS', 30),
    'negative_cache_hours' => (int) env('FAVICON_PROXY_NEGATIVE_CACHE_HOURS', 6),
];
