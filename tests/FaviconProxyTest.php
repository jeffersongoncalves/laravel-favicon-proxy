<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Cache::flush());

it('proxies and serves a favicon for a valid domain', function () {
    Http::fake(['www.google.com/s2/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png'])]);

    $this->get('/favicon-proxy?domain=example.com')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('serves a transparent fallback for an invalid domain', function () {
    $this->get('/favicon-proxy?domain=not a domain!!')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('serves a fallback when the upstream icon is unavailable', function () {
    Http::fake(['www.google.com/s2/*' => Http::response('', 404)]);

    $this->get('/favicon-proxy?domain=broken.test')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('rejects a non-image upstream content-type and serves the fallback', function () {
    Http::fake(['www.google.com/s2/*' => Http::response('<script>alert(1)</script>', 200, ['Content-Type' => 'text/html'])]);

    $response = $this->get('/favicon-proxy?domain=evil.test')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect($response->getContent())->not->toContain('<script>');
});

it('rejects an svg upstream content-type and serves the fallback (XSS guard)', function () {
    Http::fake(['www.google.com/s2/*' => Http::response('<svg onload="alert(1)"></svg>', 200, ['Content-Type' => 'image/svg+xml'])]);

    $response = $this->get('/favicon-proxy?domain=evil.test')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect($response->getContent())->not->toContain('<svg');
});

it('serves the upstream body and content-type verbatim on a hit', function () {
    Http::fake(['www.google.com/s2/*' => Http::response('GIFDATA', 200, ['Content-Type' => 'image/gif'])]);

    $response = $this->get('/favicon-proxy?domain=example.com')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/gif');

    expect($response->getContent())->toBe('GIFDATA');
});

it('normalizes a content-type that carries parameters', function () {
    Http::fake(['www.google.com/s2/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png; charset=binary'])]);

    $this->get('/favicon-proxy?domain=example.com')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('rejects an upstream response larger than the size cap', function () {
    config()->set('favicon-proxy.max_bytes', 4);
    Http::fake(['www.google.com/s2/*' => Http::response('PNGDATATOOBIG', 200, ['Content-Type' => 'image/png'])]);

    $response = $this->get('/favicon-proxy?domain=big.test')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect($response->getContent())->not->toBe('PNGDATATOOBIG');
});

it('caches a fetched icon so a second request does not hit the upstream', function () {
    Http::fake(['www.google.com/s2/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png'])]);

    $this->get('/favicon-proxy?domain=example.com')->assertOk();
    $this->get('/favicon-proxy?domain=example.com')->assertOk();

    Http::assertSentCount(1);
});

it('negative-caches a failure so it is not refetched immediately', function () {
    Http::fake(['www.google.com/s2/*' => Http::response('', 404)]);

    $this->get('/favicon-proxy?domain=broken.test')->assertOk();
    $this->get('/favicon-proxy?domain=broken.test')->assertOk();

    Http::assertSentCount(1);
});

it('applies the configured route middleware', function () {
    Http::fake(['www.google.com/s2/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png'])]);

    $this->get('/favicon-proxy?domain=example.com')->assertHeader('X-Favicon-Middleware', 'applied');
});
