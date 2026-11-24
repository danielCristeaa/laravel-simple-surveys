<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Http\Controllers\SurveyDismissalController;
use DanielCristea\SimpleSurveys\Http\Controllers\SurveyOpenController;
use DanielCristea\SimpleSurveys\Http\Controllers\SurveySubmissionController;
use Illuminate\Support\Facades\Route;

Route::post('/{surveyKey}/open', SurveyOpenController::class)
    ->name('simple-surveys.open');

Route::post('/{surveyKey}/responses', SurveySubmissionController::class)
    ->name('simple-surveys.responses.store');

Route::post('/{surveyKey}/dismiss', SurveyDismissalController::class)
    ->name('simple-surveys.dismiss');
