<div class="filament-hidden">

![Laravel Favicon Proxy](https://raw.githubusercontent.com/jeffersongoncalves/laravel-favicon-proxy/master/art/jeffersongoncalves-laravel-favicon-proxy.png)

</div>

# Laravel Favicon Proxy

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jeffersongoncalves/laravel-favicon-proxy.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-favicon-proxy)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-favicon-proxy/run-tests.yml?branch=master&label=tests&style=flat-square)](https://github.com/jeffersongoncalves/laravel-favicon-proxy/actions?query=workflow%3Arun-tests+branch%3Amaster)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-favicon-proxy/fix-php-code-style-issues.yml?branch=master&label=code%20style&style=flat-square)](https://github.com/jeffersongoncalves/laravel-favicon-proxy/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/jeffersongoncalves/laravel-favicon-proxy.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-favicon-proxy)

Proxy website favicons through your own host. Instead of pointing an external-link `<img>` straight at a third-party favicon service (a cross-origin connection on every page, leaking which links a visitor sees), this fetches the icon server-side once, caches the bytes, and serves them same-origin — the browser never talks to the upstream.

- Default upstream is Google's S2 favicon service; configurable.
- Allowlists raster image content-types (`image/png`, `image/jpeg`, `image/gif`, `image/webp`, `image/x-icon`, `image/vnd.microsoft.icon`) and sends `X-Content-Type-Options: nosniff`. `image/svg+xml` and any non-image response are rejected — an SVG served same-origin can carry `<script>`, so it would otherwise be a stored-XSS vector.
- Caps the upstream response size (`max_bytes`, default 100 KB) to guard against an oversized body.
- Serialises concurrent misses for the same domain with an atomic cache lock (cache-stampede guard).
- Negative-caches failures, falls back to a transparent 1×1 pixel.

## Installation

```bash
composer require jeffersongoncalves/laravel-favicon-proxy
```

The `/favicon-proxy` route is registered automatically. Point your icons at it:

```blade
<img src="{{ route('favicon-proxy', ['domain' => 'laravel.com']) }}" alt="" width="16" height="16">
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag="favicon-proxy-config"
```

## Configuration

| Key | Default | Description |
| --- | --- | --- |
| `enabled` | `true` | Register the proxy route. |
| `path` | `favicon-proxy` | Route path. |
| `middleware` | `['throttle:120,1']` | Middleware on the route. |
| `endpoint` | `https://www.google.com/s2/favicons` | Upstream favicon service. |
| `query` | `domain` | Query parameter carrying the domain. |
| `size` | `64` | Requested icon size (px). |
| `timeout` | `6` | Upstream request timeout (s). |
| `max_bytes` | `102400` | Maximum upstream response size (bytes); larger responses are rejected. |
| `cache_prefix` | `favicon` | Cache key prefix. |
| `cache_days` | `30` | How long a fetched icon is cached. |
| `negative_cache_hours` | `6` | How long a failure is negative-cached. |

## Security

The proxy only fetches from the fixed `endpoint` host (the visitor controls only the `domain` query string, which the upstream service itself resolves), so it is **not** an open SSRF gateway. This guarantee depends entirely on `favicon-proxy.endpoint` being a **trusted constant** — never derive it from request input, a database value an end user can edit, or any other untrusted source, or you reintroduce SSRF. Keep the route behind the default `throttle` middleware (or your own) as well.

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
