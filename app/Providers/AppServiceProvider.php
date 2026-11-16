<?php

namespace App\Providers;

use App\Livewire\PrefixedHandleRequests;
use App\Services\Ai\Contracts\ExtractionDriver;
use App\Services\Ai\FakeExtractionDriver;
use App\Services\Ai\OpenAiExtractionDriver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ExtractionDriver::class, function ($app) {
            $driver = (string) config('ai.driver', 'fake');

            return match ($driver) {
                'openai' => $app->make(OpenAiExtractionDriver::class),
                default => $app->make(FakeExtractionDriver::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '') {
            URL::forceRootUrl($appUrl);
        }

        $basePath = rtrim((string) parse_url($appUrl, PHP_URL_PATH), '/');

        if ($basePath !== '' && $basePath !== '/') {
            // Browser must load JS from /dockpilot/livewire/..., not /livewire/...
            config([
                'livewire.asset_url' => $basePath.'/livewire/livewire.js',
            ]);

            // Livewire injects scripts after middleware; prefix update URI at the source.
            $this->app->instance(HandleRequests::class, new PrefixedHandleRequests);
        }
    }
}
