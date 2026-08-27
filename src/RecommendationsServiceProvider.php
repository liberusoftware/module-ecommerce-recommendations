<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations;

use Illuminate\Support\ServiceProvider;

/**
 * Installing this package boots nothing: `extra.laravel.providers` is absent on
 * purpose and the host's module manager registers the provider only when the
 * module is named in `MODULES_ENABLED`. It binds no seam — an unbound seam is
 * the module saying nobody answered, which is not the same as an answer.
 */
class RecommendationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/recommendations.php', 'recommendations');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/recommendations.php' => $this->app->configPath('recommendations.php'),
            ], 'recommendations-config');
        }
    }
}
