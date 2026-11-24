<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Http\Controllers\EligibleSurveyController;
use DanielCristea\SimpleSurveys\Http\Controllers\WidgetController;
use Illuminate\Support\Facades\Route;

Route::get('/widget', WidgetController::class)
    ->name('simple-surveys.widget');

Route::get('/eligible', EligibleSurveyController::class)
    ->name('simple-surveys.eligible');
