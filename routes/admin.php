<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Http\Controllers\Admin\SurveyAppearanceController;
use DanielCristea\SimpleSurveys\Http\Controllers\Admin\SurveyExportController;
use DanielCristea\SimpleSurveys\Http\Controllers\Admin\SurveyResetController;
use DanielCristea\SimpleSurveys\Http\Controllers\Admin\SurveyResultsController;
use DanielCristea\SimpleSurveys\Http\Controllers\Admin\SurveyStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SurveyResultsController::class, 'index'])
    ->name('simple-surveys.admin.index');

Route::get('/{surveyKey}', [SurveyResultsController::class, 'show'])
    ->name('simple-surveys.admin.show');

Route::get('/{surveyKey}/appearance', [SurveyAppearanceController::class, 'edit'])
    ->name('simple-surveys.admin.appearance.edit');

Route::put('/{surveyKey}/appearance', [SurveyAppearanceController::class, 'update'])
    ->name('simple-surveys.admin.appearance.update');

Route::delete('/{surveyKey}/appearance', [SurveyAppearanceController::class, 'destroy'])
    ->name('simple-surveys.admin.appearance.reset');

Route::get('/{surveyKey}/export', SurveyExportController::class)
    ->name('simple-surveys.admin.export');

Route::post('/{surveyKey}/close', [SurveyStatusController::class, 'close'])
    ->name('simple-surveys.admin.close');

Route::post('/{surveyKey}/reopen', [SurveyStatusController::class, 'reopen'])
    ->name('simple-surveys.admin.reopen');

Route::post('/{surveyKey}/reset', SurveyResetController::class)
    ->name('simple-surveys.admin.reset');
