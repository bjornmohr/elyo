<?php

namespace App\Providers;

use App\Runtime\RuntimeProfile;
use App\Services\Privacy\AuditLoggerContract;
use App\Services\Privacy\DatabaseAuditLogger;
use App\Services\Privacy\MappingCryptography;
use App\Services\Privacy\MappingService;
use App\Services\Privacy\MappingServiceContract;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MappingCryptography::class, fn (): MappingCryptography => new MappingCryptography(
            (string) config('privacy.mapping.encryption_key'),
            (string) config('privacy.mapping.hmac_key'),
            (string) config('privacy.mapping.subject_derivation_key'),
            (string) config('app.key'),
        ));

        $this->app->singleton(AuditLoggerContract::class, DatabaseAuditLogger::class);
        $this->app->bind(MappingServiceContract::class, MappingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Runs even when the configuration is cached, where the fail-safe
        // checks inside config/runtime.php would never execute.
        RuntimeProfile::assertMatchesEnvironment(
            (string) config('runtime.profile'),
            (string) config('app.env'),
        );
    }
}
