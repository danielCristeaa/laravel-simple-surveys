<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database Table Name
    |--------------------------------------------------------------------------
    |
    | The table used to store survey responses and dismissals.
    |
    */
    'table_name' => 'simple_survey_responses',

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
    ],
];
