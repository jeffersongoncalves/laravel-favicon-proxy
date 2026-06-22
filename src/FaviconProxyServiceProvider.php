<?php

declare(strict_types=1);

namespace JeffersonGoncalves\FaviconProxy;

use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\FaviconProxy\Http\Controllers\FaviconProxyController;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FaviconProxyServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-favicon-proxy')
            ->hasConfigFile();
    }

    public function packageBooted(): void
    {
        if (! config('favicon-proxy.enabled', true)) {
            return;
        }

        Route::get(ltrim((string) config('favicon-proxy.path', 'favicon-proxy'), '/'), FaviconProxyController::class)
            ->middleware((array) config('favicon-proxy.middleware', []))
            ->name('favicon-proxy');
    }
}
