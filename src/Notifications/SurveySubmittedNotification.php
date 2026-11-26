<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Notifications;

use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Notifications\Channels\SlackWebhookChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class SurveySubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $tracking
     * @param  array<int, string>  $channels
     */
    public function __construct(
        public readonly SurveyResponse $response,
        public readonly string $surveyKey,
        public readonly string $surveyTitle,
        public readonly array $context = [],
        public readonly array $tracking = [],
        public readonly array $channels = ['mail'],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $resolved = array_map(
            static fn (string $channel): string => $channel === 'slack' ? SlackWebhookChannel::class : $channel,
            $this->channels,
        );

        return array_values(array_unique($resolved));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->response->loadMissing(['answers.question', 'respondent']);

        $message = (new MailMessage)
            ->subject("New survey response: {$this->surveyTitle}")
            ->greeting('New survey response received')
            ->line("A new response was submitted for \"{$this->surveyTitle}\" ({$this->surveyKey}).")
            ->line('Respondent: '.$this->respondentSummary());

        foreach ($this->formattedAnswers() as $item) {
            $message->line("{$item['label']}: {$item['display']}");
        }

        if (isset($this->context['url']) && is_string($this->context['url']) && trim($this->context['url']) !== '') {
            $message->line('Submitted from: '.$this->context['url']);
        }

        $resultsUrl = $this->adminResultsUrl();

        if ($resultsUrl !== null) {
            $message->action('View Survey Results', $resultsUrl);
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSlackWebhook(object $notifiable): array
    {
        $this->response->loadMissing(['answers.question', 'respondent']);

        $headerText = Str::limit("New survey response: {$this->surveyTitle}", 150, '...');

        $metaLines = [
            "*Survey:* {$this->surveyTitle} (`{$this->surveyKey}`)",
            '*Respondent:* '.$this->respondentSummary(),
        ];

        if (isset($this->context['url']) && is_string($this->context['url']) && trim($this->context['url']) !== '') {
            $metaLines[] = '*Page:* '.$this->context['url'];
        }

        /** @var array<int, array<string, mixed>> $blocks */
        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => $headerText,
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => implode("\n", $metaLines),
                ],
            ],
        ];

        $answerLines = [];

        foreach ($this->formattedAnswers() as $item) {
            $answerLines[] = "*{$item['label']}:* {$item['display']}";
        }

        if ($answerLines !== []) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => implode("\n", $answerLines),
                ],
            ];
        }

        $resultsUrl = $this->adminResultsUrl();

        if ($resultsUrl !== null) {
            $blocks[] = [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "<{$resultsUrl}|View Survey Results>",
                    ],
                ],
            ];
        }

        return [
            'text' => "New survey response: {$this->surveyTitle}",
            'blocks' => $blocks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->response->loadMissing(['answers.question']);

        /** @var array<string, mixed> $answers */
        $answers = [];

        foreach ($this->response->answers as $answer) {
            $answers[$answer->question->key] = $answer->typedValue();
        }

        return [
            'event' => 'survey.submitted',
            'survey_key' => $this->surveyKey,
            'survey_title' => $this->surveyTitle,
            'response_id' => $this->response->getKey(),
            'respondent' => [
                'type' => $this->response->respondent_type,
                'id' => $this->response->respondent_id,
            ],
            'answers' => $answers,
            'context' => $this->context,
            'tracking' => $this->tracking,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, value: mixed, display: string}>
     */
    protected function formattedAnswers(): array
    {
        /** @var array<int, array{key: string, label: string, value: mixed, display: string}> $items */
        $items = [];

        foreach ($this->response->answers as $answer) {
            $value = $answer->typedValue();
            $items[] = [
                'key' => $answer->question->key,
                'label' => $answer->question->label,
                'value' => $value,
                'display' => $this->formatAnswerValue($value),
            ];
        }

        return $items;
    }

    protected function formatAnswerValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            $parts = array_values(array_filter(
                array_map(static fn (mixed $item): string => is_scalar($item) ? (string) $item : '', $value),
                static fn (string $item): bool => $item !== '',
            ));

            return $parts !== [] ? implode(', ', $parts) : '—';
        }

        return is_scalar($value) ? (string) $value : '—';
    }

    protected function respondentSummary(): string
    {
        $respondent = $this->response->respondent;

        if ($respondent !== null) {
            $name = $respondent->getAttribute('name');
            $email = $respondent->getAttribute('email');

            $nameStr = is_string($name) && trim($name) !== '' ? trim($name) : null;
            $emailStr = is_string($email) && trim($email) !== '' ? trim($email) : null;

            if ($nameStr !== null && $emailStr !== null) {
                return "{$nameStr} ({$emailStr})";
            }

            if ($emailStr !== null) {
                return $emailStr;
            }

            if ($nameStr !== null) {
                return $nameStr;
            }
        }

        return "{$this->response->respondent_type} #{$this->response->respondent_id}";
    }

    protected function adminResultsUrl(): ?string
    {
        if (! (bool) config('simple-surveys.admin.enabled', true)) {
            return null;
        }

        if (! Route::has('simple-surveys.admin.show')) {
            return null;
        }

        return route('simple-surveys.admin.show', $this->surveyKey);
    }
}
