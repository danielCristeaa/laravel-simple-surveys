<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers;

use DanielCristea\SimpleSurveys\Services\SurveyEligibilityResolver;
use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class WidgetController
{
    public function __construct(
        private readonly SurveyRegistry $registry,
        private readonly SurveyEligibilityResolver $eligibility,
    ) {}

    public function __invoke(Request $request): Response
    {
        $guard = config('simple-surveys.guard');
        $user = $request->user(is_string($guard) ? $guard : null);

        if (! $user instanceof Authenticatable) {
            return response()->noContent()->header('Cache-Control', 'no-store, private');
        }

        $surveyKey = $request->query('survey');
        $positionParam = $request->query('position', 'bottom-right');
        $position = in_array($positionParam, ['bottom-right', 'bottom-left'], true)
            ? (string) $positionParam
            : 'bottom-right';

        $activeSurvey = $this->resolveActiveSurvey(
            $user,
            is_string($surveyKey) && $surveyKey !== '' ? $surveyKey : null,
        );

        if ($activeSurvey === null) {
            return response()->noContent()->header('Cache-Control', 'no-store, private');
        }

        /** @var view-string $view */
        $view = 'simple-surveys::widget';

        return response()
            ->view($view, [
                'activeSurvey' => $activeSurvey,
                'submitUrl' => route('simple-surveys.responses.store', $activeSurvey->key()),
                'dismissUrl' => route('simple-surveys.dismiss', $activeSurvey->key()),
                'openUrl' => route('simple-surveys.open', $activeSurvey->key()),
                'position' => $position,
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    private function resolveActiveSurvey(Authenticatable $user, ?string $surveyKey): ?Survey
    {
        if ($surveyKey !== null) {
            $candidate = $this->registry->find($surveyKey);

            if ($candidate !== null && $this->eligibility->isEligible($candidate, $user)) {
                return $candidate;
            }

            return null;
        }

        return $this->eligibility->nextFor($user);
    }
}
