<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Tests;

use DanielCristea\SimpleSurveys\SimpleSurveysServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SimpleSurveysServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
    }
}
