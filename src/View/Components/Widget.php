<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\View\Components;

use DanielCristea\SimpleSurveys\Services\SurveyEligibilityResolver;
use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

final class Widget extends Component
{
    public ?Survey $activeSurvey = null;

    public ?string $submitUrl = null;

    public ?string $dismissUrl = null;

    public ?string $openUrl = null;

    public ?string $widgetEndpointUrl = null;

    public string $position;

    public bool $dynamic;

    public function __construct(
        SurveyRegistry $registry,
        SurveyEligibilityResolver $eligibility,
        ?string $survey = null,
        string $position = 'bottom-right',
        bool $dynamic = false,
    ) {
        $this->position = in_array($position, ['bottom-right', 'bottom-left'], true)
            ? $position
            : 'bottom-right';
        $this->dynamic = $dynamic;

        if ($this->dynamic) {
            $query = array_filter([
                'survey' => $survey !== null && $survey !== '' ? $survey : null,
                'position' => $this->position,
            ], static fn (?string $value): bool => $value !== null);

            $this->widgetEndpointUrl = route('simple-surveys.widget', $query);
        }

        $guard = config('simple-surveys.guard');
        $user = Auth::guard(is_string($guard) ? $guard : null)->user();

        if ($user === null) {
            return;
        }

        if ($survey !== null && $survey !== '') {
            $candidate = $registry->find($survey);

            if ($candidate !== null && $eligibility->isEligible($candidate, $user)) {
                $this->activeSurvey = $candidate;
            }
        } else {
            $this->activeSurvey = $eligibility->nextFor($user);
        }

        if ($this->activeSurvey !== null) {
            $this->submitUrl = route('simple-surveys.responses.store', $this->activeSurvey->key());
            $this->dismissUrl = route('simple-surveys.dismiss', $this->activeSurvey->key());
            $this->openUrl = route('simple-surveys.open', $this->activeSurvey->key());
        }
    }

    public function shouldRender(): bool
    {
        return $this->dynamic || $this->activeSurvey !== null;
    }

    public function render(): View
    {
        /** @var view-string $view */
        $view = $this->dynamic ? 'simple-surveys::widget-dynamic' : 'simple-surveys::widget';

        return view($view);
    }
}
