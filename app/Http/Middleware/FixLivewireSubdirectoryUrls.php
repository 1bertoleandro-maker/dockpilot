<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Livewire emits root-absolute URLs (/livewire/...).
 * Under Apache Alias /dockpilot those must be /dockpilot/livewire/...
 */
class FixLivewireSubdirectoryUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $basePath = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

        if ($basePath === '' || $basePath === '/') {
            return $response;
        }

        if (! method_exists($response, 'getContent')) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '' || ! str_contains($content, '/livewire')) {
            return $response;
        }

        $content = str_replace(
            [
                '"/livewire/',
                "'/livewire/",
                '\/livewire\/',
            ],
            [
                '"'.$basePath.'/livewire/',
                "'".$basePath.'/livewire/',
                '\/'.trim($basePath, '/').'\/livewire\/',
            ],
            $content
        );

        $response->setContent($content);

        return $response;
    }
}
