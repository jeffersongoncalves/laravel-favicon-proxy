<?php

namespace JeffersonGoncalves\FaviconProxy\Tests;

use JeffersonGoncalves\FaviconProxy\FaviconProxyServiceProvider;
use JeffersonGoncalves\FaviconProxy\Tests\Fixtures\AddTestHeaderMiddleware;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FaviconProxyServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $configPath = __DIR__.'/../config/favicon-proxy.php';

        if (file_exists($configPath)) {
            $app['config']->set('favicon-proxy', require $configPath);
        }

        // Swap the default throttle middleware for a header-adding one so route
        // middleware is provably wired (asserted below) without throttle state
        // leaking between tests.
        $app['config']->set('favicon-proxy.middleware', [AddTestHeaderMiddleware::class]);
    }
}
