<?php

namespace App\Providers;

use App\Services\Insights\Contracts\DashboardSummaryProvider;
use App\Services\Insights\Contracts\EmployeeDashboardProvider;
use App\Services\Insights\Contracts\InfectionRadarProvider;
use App\Services\Insights\Contracts\MeasureImpactProvider;
use App\Services\Insights\Contracts\MeasureStatisticsProvider;
use App\Services\Insights\Contracts\RiskLandscapeProvider;
use App\Services\Insights\Contracts\UsageFunnelProvider;
use App\Services\Insights\Db\DbMeasureStatisticsProvider;
use App\Services\Insights\Demo\DemoDashboardSummaryProvider;
use App\Services\Insights\Demo\DemoEmployeeDashboardProvider;
use App\Services\Insights\Demo\DemoInfectionRadarProvider;
use App\Services\Insights\Demo\DemoMeasureImpactProvider;
use App\Services\Insights\Demo\DemoMeasureStatisticsProvider;
use App\Services\Insights\Demo\DemoRiskLandscapeProvider;
use App\Services\Insights\Demo\DemoUsageFunnelProvider;
use App\Services\Insights\Prod\NullDashboardSummaryProvider;
use App\Services\Insights\Prod\NullEmployeeDashboardProvider;
use App\Services\Insights\Prod\NullInfectionRadarProvider;
use App\Services\Insights\Prod\NullMeasureImpactProvider;
use App\Services\Insights\Prod\NullRiskLandscapeProvider;
use App\Services\Insights\Prod\NullUsageFunnelProvider;
use Illuminate\Support\ServiceProvider;

class InsightsServiceProvider extends ServiceProvider
{
    /**
     * Contract => [demo implementation, prod implementation].
     * The mode is read lazily at resolve time so runtime config changes
     * (tests, config:clear) pick the right side.
     */
    private const BINDINGS = [
        MeasureStatisticsProvider::class => [DemoMeasureStatisticsProvider::class, DbMeasureStatisticsProvider::class],
        MeasureImpactProvider::class => [DemoMeasureImpactProvider::class, NullMeasureImpactProvider::class],
        RiskLandscapeProvider::class => [DemoRiskLandscapeProvider::class, NullRiskLandscapeProvider::class],
        UsageFunnelProvider::class => [DemoUsageFunnelProvider::class, NullUsageFunnelProvider::class],
        InfectionRadarProvider::class => [DemoInfectionRadarProvider::class, NullInfectionRadarProvider::class],
        DashboardSummaryProvider::class => [DemoDashboardSummaryProvider::class, NullDashboardSummaryProvider::class],
        EmployeeDashboardProvider::class => [DemoEmployeeDashboardProvider::class, NullEmployeeDashboardProvider::class],
    ];

    public function register(): void
    {
        foreach (self::BINDINGS as $contract => [$demo, $prod]) {
            $this->app->bind($contract, fn ($app) => $app->make(
                config('elyo.data_mode') === 'demo' ? $demo : $prod,
            ));
        }
    }
}
