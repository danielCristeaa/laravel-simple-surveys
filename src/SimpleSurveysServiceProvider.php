<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys;

use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class SimpleSurveysServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('simple-surveys')
            ->hasConfigFile('simple-surveys')
            ->hasMigration('create_simple_survey_responses_table');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SurveyRegistry::class);
        $this->app->singleton(SimpleSurveys::class);
    }
}
