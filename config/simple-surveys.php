<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database Table Names
    |--------------------------------------------------------------------------
    |
    | The tables used to store surveys, questions, responses, answers,
    | dismissals, opens, and survey impressions.
    |
    */
    'tables' => [
        'surveys' => 'simple_surveys',
        'questions' => 'simple_survey_questions',
        'responses' => 'simple_survey_responses',
        'answers' => 'simple_survey_answers',
        'dismissals' => 'simple_survey_dismissals',
        'opens' => 'simple_survey_opens',
        'impressions' => 'simple_survey_impressions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Respondent Primary Key Type
    |--------------------------------------------------------------------------
    |
    | Determines the column type used for the polymorphic respondent_id column
    | in the published migration. Supported values: "id", "uuid", "ulid".
    |
    */
    'respondent_key_type' => 'id',

    /*
    |--------------------------------------------------------------------------
    | Authentication Guard
    |--------------------------------------------------------------------------
    |
    | The auth guard used to resolve the currently authenticated user for
    | survey eligibility, submissions, and admin access. Leave null to use
    | your application's default guard.
    |
    */
    'guard' => null,

    /*
    |--------------------------------------------------------------------------
    | Customer Survey Routes
    |--------------------------------------------------------------------------
    |
    | Configuration for the endpoints used by the customer-facing widget to
    | submit responses and dismiss surveys.
    |
    */
    'routes' => [
        'enabled' => true,
        'prefix' => 'simple-surveys',
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Funnel Tracking
    |--------------------------------------------------------------------------
    |
    | Controls optional lightweight funnel telemetry sent by the customer
    | widget. Set "impressions" to false to disable tracking unique users
    | who have seen a survey banner.
    |
    */
    'tracking' => [
        'impressions' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Results Dashboard
    |--------------------------------------------------------------------------
    |
    | Configuration for the hidden admin results page where authorized users
    | can view running and past survey results.
    |
    */
    'admin' => [
        'enabled' => true,
        'prefix' => 'simple-surveys/admin',
        'middleware' => ['web', 'auth'],
        'gate' => 'viewSimpleSurveys',
        'appearance_editor' => true,
    ],
];
