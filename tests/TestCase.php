<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Tests;

use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Services\SurveySerializer;
use DanielCristea\SimpleSurveys\Services\SurveyTranslator;
use DanielCristea\SimpleSurveys\Services\SurveyValidator;
use DanielCristea\SimpleSurveys\SimpleSurveysServiceProvider;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;

abstract class TestCase extends Orchestra
{
    private ?string $isolatedLangPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        SimpleSurveys::clear();
    }

    protected function tearDown(): void
    {
        if ($this->isolatedLangPath !== null && File::isDirectory($this->isolatedLangPath)) {
            File::deleteDirectory($this->isolatedLangPath);
            $this->isolatedLangPath = null;
        }

        parent::tearDown();
    }

    /**
     * Configure an isolated temporary lang/ directory compatible across Laravel 11.0–13.x.
     */
    protected function useIsolatedLangPath(): string
    {
        $tempLangPath = sys_get_temp_dir().'/lss-i18n-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($tempLangPath);
        $this->isolatedLangPath = $tempLangPath;

        $originalLangPath = app()->langPath();
        app()->useLangPath($tempLangPath);

        // Resolve translator first so deferred package namespace callbacks have run
        /** @var Translator $existingTranslator */
        $existingTranslator = app('translator');

        /** @var FileLoader $existingLoader */
        $existingLoader = $existingTranslator->getLoader();

        $loaderFile = (new ReflectionClass(FileLoader::class))->getFileName();
        $paths = array_values(array_filter([
            is_string($loaderFile) ? dirname($loaderFile).'/lang' : null,
            $originalLangPath,
            $tempLangPath,
        ]));

        $newLoader = new FileLoader(app('files'), $paths);

        foreach ($existingLoader->namespaces() as $namespace => $hint) {
            $newLoader->addNamespace($namespace, $hint);
        }

        app()->instance('translation.loader', $newLoader);

        $translator = new Translator($newLoader, app()->getLocale());
        $translator->setFallback(app()->getFallbackLocale());

        app()->instance('translator', $translator);
        app()->forgetInstance(SurveyTranslator::class);
        app()->forgetInstance(SurveySerializer::class);
        app()->forgetInstance(SurveyValidator::class);

        return $tempLangPath;
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

        $migration = require __DIR__.'/../database/migrations/create_simple_surveys_tables.php.stub';
        $migration->up();
    }
}
