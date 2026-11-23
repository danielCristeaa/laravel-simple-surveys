<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Http\Controllers\Admin\SurveyExportController;
use DanielCristea\SimpleSurveys\Http\Controllers\Admin\SurveyResultsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SurveyResultsController::class, 'index'])
    ->name('simple-surveys.admin.index');

Route::get('/{surveyKey}', [SurveyResultsController::class, 'show'])
    ->name('simple-surveys.admin.show');

Route::get('/{surveyKey}/export', SurveyExportController::class)
    ->name('simple-surveys.admin.export');
