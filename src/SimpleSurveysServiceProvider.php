<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class SimpleSurveysServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('simple-surveys')
            ->hasConfigFile('simple-surveys');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SimpleSurveys::class);
    }
}
