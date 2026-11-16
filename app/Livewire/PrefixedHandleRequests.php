<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Mechanisms\HandleRequests\HandleRequests;

class PrefixedHandleRequests extends HandleRequests
{
    public function getUpdateUri(): string
    {
        $uri = parent::getUpdateUri();
        $basePath = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

        if ($basePath === '' || $basePath === '/') {
            return $uri;
        }

        if (str_starts_with($uri, $basePath.'/')) {
            return $uri;
        }

        return $basePath.$uri;
    }
}
