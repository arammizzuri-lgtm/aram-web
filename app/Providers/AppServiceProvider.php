<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Ai\AiProvider;
use App\Services\Ai\ClaudeProvider;
use App\Services\Currency\CurrencyConverter;
use App\Services\Documents\DocumentNumberGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bound to the contract so the assistant can be stubbed in tests and
        // repointed at another provider without touching the UI.
        $this->app->bind(
            AiProvider::class,
            ClaudeProvider::class,
        );

        // Both memoise per request, so they are shared rather than rebuilt.
        $this->app->singleton(CurrencyConverter::class);
        $this->app->singleton(DocumentNumberGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The owner passes every gate. Granting the permissions individually would
        // silently leave the owner locked out of each new module as it ships.
        Gate::before(fn (User $user) => $user->hasRole('owner') ? true : null);

        // Catch missing relationships in dev before they become N+1 queries in production.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());
    }
}
