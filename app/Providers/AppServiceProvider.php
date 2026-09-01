<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Support\Settings\Settings::class);
        $this->app->singleton(\App\Support\Audit\AuditLogger::class);
        $this->app->singleton(\App\Domain\Authorization\PermissionRegistrar::class);

        // Search engine abstraction — SEARCH_DRIVER=sql|meilisearch (ADR: no direct engine coupling)
        $this->app->bind(\App\Domain\Search\SearchProviderInterface::class, function () {
            if (config('services.search.driver') === 'meilisearch') {
                $meili = \App\Domain\Search\MeilisearchSearchProvider::fromConfig();
                if ($meili) {
                    return $meili;
                }
            }

            return new \App\Domain\Search\SqlSearchProvider;
        });

        $this->app->bind(\App\Domain\Location\GeoServiceInterface::class, \App\Domain\Location\GeoService::class);

        $this->app->singleton(\App\Domain\Payment\Contracts\PaymentGatewayInterface::class, function () {
            $driver = config('services.payments.driver', 'sandbox');

            return match ($driver) {
                'sandbox' => new \App\Domain\Payment\Gateways\SandboxGateway(
                    config('services.payments.sandbox_secret', 'sandbox-secret'),
                ),
                'xendit' => \App\Domain\Payment\Gateways\XenditPaymentGateway::fromConfig(),
                'midtrans' => \App\Domain\Payment\Gateways\MidtransPaymentGateway::fromConfig(),
                default => throw new \RuntimeException("Payment driver [{$driver}] not configured. Register its adapter."),
            };
        });

        // AI provider — optional; null = rule-based fallbacks (ADR-010)
        $this->app->singleton(\App\Domain\Ai\AiManager::class, function () {
            $driver = config('services.ai.driver');

            $provider = null;
            if ($driver && class_exists($driver)) {
                $provider = app($driver);
                if (! $provider instanceof \App\Domain\Ai\AiProviderInterface) {
                    $provider = null;
                }
            }

            return new \App\Domain\Ai\AiManager($provider);
        });
    }

    public function boot(): void
    {
        Route::aliasMiddleware('permission', \App\Http\Middleware\EnsurePermission::class);

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('webhook', fn (Request $r) => Limit::perMinute(600)->by($r->ip()));

        // Model availability for policy auto-discovery
    }
}
