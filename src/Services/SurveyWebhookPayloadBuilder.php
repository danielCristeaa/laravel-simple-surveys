<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

use Carbon\CarbonInterface;
use DanielCristea\SimpleSurveys\Events\SurveyDismissed;
use DanielCristea\SimpleSurveys\Events\SurveyOpened;
use DanielCristea\SimpleSurveys\Events\SurveySeen;
use DanielCristea\SimpleSurveys\Events\SurveySubmitted;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Database\Eloquent\Model;

final class SurveyWebhookPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function forSeen(SurveySeen $event): array
    {
        return $this->envelope(
            eventName: 'survey.seen',
            occurredAt: $event->impression->first_seen_at,
            survey: $event->survey,
            respondent: $event->respondent,
            context: $event->context,
            tracking: $event->tracking,
            data: [
                'impression_id' => $event->impression->getKey(),
                'first_seen_at' => $event->impression->first_seen_at->toIso8601String(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forOpened(SurveyOpened $event): array
    {
        return $this->envelope(
            eventName: 'survey.opened',
            occurredAt: $event->open->last_opened_at,
            survey: $event->survey,
            respondent: $event->respondent,
            context: $event->context,
            tracking: $event->tracking,
            data: [
                'open_id' => $event->open->getKey(),
                'open_count' => $event->open->open_count,
                'first_opened_at' => $event->open->first_opened_at->toIso8601String(),
                'last_opened_at' => $event->open->last_opened_at->toIso8601String(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forDismissed(SurveyDismissed $event): array
    {
        return $this->envelope(
            eventName: 'survey.dismissed',
            occurredAt: $event->dismissal->dismissed_at,
            survey: $event->survey,
            respondent: $event->respondent,
            context: $event->context,
            tracking: $event->tracking,
            data: [
                'dismissal_id' => $event->dismissal->getKey(),
                'source' => $event->dismissal->source,
                'dismiss_count' => $event->dismissal->dismiss_count,
                'dismissed_at' => $event->dismissal->dismissed_at->toIso8601String(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forSubmitted(SurveySubmitted $event): array
    {
        $event->response->loadMissing(['answers.question']);

        /** @var array<string, mixed> $answers */
        $answers = [];

        foreach ($event->response->answers as $answer) {
            $answers[$answer->question->key] = $answer->typedValue();
        }

        return $this->envelope(
            eventName: 'survey.submitted',
            occurredAt: $event->response->created_at ?? now(),
            survey: $event->survey,
            respondent: $event->respondent,
            context: $event->context,
            tracking: $event->tracking,
            data: [
                'response_id' => $event->response->getKey(),
                'answers' => $answers,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $tracking
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function envelope(
        string $eventName,
        CarbonInterface $occurredAt,
        Survey $survey,
        Model $respondent,
        array $context,
        array $tracking,
        array $data,
    ): array {
        return [
            'event' => $eventName,
            'occurred_at' => $occurredAt->toIso8601String(),
            'survey' => [
                'key' => $survey->key(),
                'title' => $survey->getTitle(),
            ],
            'respondent' => [
                'type' => $respondent->getMorphClass(),
                'id' => $respondent->getKey(),
            ],
            'tracking' => $tracking,
            'context' => $context,
            'data' => $data,
        ];
    }
}
