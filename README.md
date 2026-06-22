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
- Validates the upstream content-type and sends `X-Content-Type-Options: nosniff` (a non-image response is never served under an image type).
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
| `cache_prefix` | `favicon` | Cache key prefix. |
| `cache_days` | `30` | How long a fetched icon is cached. |
| `negative_cache_hours` | `6` | How long a failure is negative-cached. |

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
