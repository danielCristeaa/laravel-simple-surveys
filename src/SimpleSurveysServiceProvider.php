<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys;

use DanielCristea\SimpleSurveys\Console\Commands\SyncSurveysCommand;
use DanielCristea\SimpleSurveys\Http\Middleware\AuthorizeSimpleSurveys;
use DanielCristea\SimpleSurveys\Services\SurveyEligibilityResolver;
use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use DanielCristea\SimpleSurveys\Services\SurveyResultsAggregator;
use DanielCristea\SimpleSurveys\Services\SurveySynchronizer;
use DanielCristea\SimpleSurveys\Services\SurveyValidator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class SimpleSurveysServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('simple-surveys')
            ->hasConfigFile('simple-surveys')
            ->hasViews('simple-surveys')
            ->hasMigration('create_simple_surveys_tables')
            ->hasCommand(SyncSurveysCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SurveyRegistry::class);
        $this->app->singleton(SurveyEligibilityResolver::class);
        $this->app->singleton(SurveyValidator::class);
        $this->app->singleton(SurveySynchronizer::class);
        $this->app->singleton(SurveyResultsAggregator::class);
        $this->app->singleton(SimpleSurveys::class);
    }

    public function packageBooted(): void
    {
        Blade::componentNamespace('DanielCristea\\SimpleSurveys\\View\\Components', 'simple-surveys');

        $this->registerCustomerRoutes();
        $this->registerAdminRoutes();
    }

    private function registerCustomerRoutes(): void
    {
        if (! config('simple-surveys.routes.enabled', true)) {
            return;
        }

        $prefix = config('simple-surveys.routes.prefix', 'simple-surveys');
        $middleware = config('simple-surveys.routes.middleware', ['web', 'auth']);

        Route::prefix(is_string($prefix) ? $prefix : 'simple-surveys')
            ->middleware(is_array($middleware) ? $middleware : ['web', 'auth'])
            ->group(__DIR__.'/../routes/web.php');
    }

    private function registerAdminRoutes(): void
    {
        if (! config('simple-surveys.admin.enabled', true)) {
            return;
        }

        $prefix = config('simple-surveys.admin.prefix', 'simple-surveys/admin');
        $middleware = config('simple-surveys.admin.middleware', ['web', 'auth']);
        $resolvedMiddleware = is_array($middleware) ? $middleware : ['web', 'auth'];
        $resolvedMiddleware[] = AuthorizeSimpleSurveys::class;

        Route::prefix(is_string($prefix) ? $prefix : 'simple-surveys/admin')
            ->middleware($resolvedMiddleware)
            ->group(__DIR__.'/../routes/admin.php');
    }
}
