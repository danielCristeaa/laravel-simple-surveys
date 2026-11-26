<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Notifications\SurveySubmittedNotification;

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
    | Funnel Tracking & Context
    |--------------------------------------------------------------------------
    |
    | Controls optional lightweight funnel telemetry sent by the customer
    | widget and cookies automatically captured as tracking context across
    | survey events, responses, CSV exports, and webhooks.
    |
    | "cookies" accepts either a list of cookie names (['click_id']) or an
    | associative map of ['tracking_key' => 'cookie_name'].
    |
    */
    'tracking' => [
        'impressions' => true,
        'cookies' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lifecycle Webhooks
    |--------------------------------------------------------------------------
    |
    | Optionally forward survey lifecycle events (seen, opened, dismissed,
    | submitted) to external HTTP endpoints such as a Data collector,
    | Slack/Zapier/n8n workflow, or analytics pipeline.
    |
    | "events" accepts a list of event names to send to "url" (e.g. ['submitted'])
    | or an associative map of ['event_name' => 'https://endpoint.example/hook'].
    |
    */
    'webhooks' => [
        'enabled' => false,
        'url' => null,
        'events' => ['seen', 'opened', 'dismissed', 'submitted'],
        'secret' => null,
        'signature_header' => 'X-Simple-Surveys-Signature',
        'headers' => [],
        'queued' => true,
        'queue' => null,
        'timeout' => 5,
        'tries' => 3,
        'backoff' => [10, 60, 300],
    ],

    /*
    |--------------------------------------------------------------------------
    | Submission Notifications
    |--------------------------------------------------------------------------
    |
    | Optionally dispatch built-in Laravel notifications when a new survey
    | response is submitted. Completely disabled by default.
    |
    | "channels" lists the notification channels to use (e.g. ['mail'],
    | ['mail', 'slack'], or any custom Laravel notification channel).
    |
    | "routes" maps channels to on-demand destinations, for example:
    |   'mail' => 'team@example.com' (or ['pm@example.com', 'ops@example.com']),
    |   'slack' => env('SIMPLE_SURVEYS_SLACK_WEBHOOK_URL'),
    |
    */
    'notifications' => [
        'enabled' => false,
        'notification' => SurveySubmittedNotification::class,
        'channels' => ['mail'],
        'routes' => [
            'mail' => null,
            'slack' => null,
        ],
        'queued' => true,
        'queue' => null,
        'slack_timeout' => 5,
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
