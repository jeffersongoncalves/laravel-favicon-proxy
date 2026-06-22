<?php

declare(strict_types=1);

namespace JeffersonGoncalves\FaviconProxy\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddTestHeaderMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Favicon-Middleware', 'applied');

        return $response;
    }
}
