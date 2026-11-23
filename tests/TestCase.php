<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Tests;

use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\SimpleSurveysServiceProvider;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        SimpleSurveys::clear();
    }

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
        config()->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('plan')->default('free');
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('status')->default('paid');
            $table->unsignedInteger('total_cents')->default(0);
            $table->timestamps();
        });

        $migration = require __DIR__.'/../database/migrations/create_simple_survey_responses_table.php.stub';
        $migration->up();
    }
}
