<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers;

use DanielCristea\SimpleSurveys\Enums\WidgetPosition;
use DanielCristea\SimpleSurveys\Enums\WidgetTheme;
use DanielCristea\SimpleSurveys\PageContext;
use DanielCristea\SimpleSurveys\Services\SurveyAppearanceResolver;
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
        private readonly SurveyAppearanceResolver $appearanceResolver,
    ) {}

    public function __invoke(Request $request): Response
    {
        $guard = config('simple-surveys.guard');
        $user = $request->user(is_string($guard) ? $guard : null);

        if (! $user instanceof Authenticatable) {
            return response()->noContent()->header('Cache-Control', 'no-store, private');
        }

        $surveyKey = $request->query('survey');
        $urlParam = $request->query('url');
        $positionParam = $request->query('position', 'bottom-right');
        $themeParam = $request->query('theme');

        $defaultPosition = is_string($positionParam)
            ? (WidgetPosition::tryFrom($positionParam) ?? WidgetPosition::BottomRight)
            : WidgetPosition::BottomRight;

        $defaultTheme = is_string($themeParam) && $themeParam !== ''
            ? WidgetTheme::tryFrom($themeParam)
            : null;

        $context = is_string($urlParam) && $urlParam !== ''
            ? PageContext::fromPath($urlParam)
            : null;

        $activeSurvey = $this->resolveActiveSurvey(
            $user,
            is_string($surveyKey) && $surveyKey !== '' ? $surveyKey : null,
            $context,
        );

        if ($activeSurvey === null) {
            return response()->noContent()->header('Cache-Control', 'no-store, private');
        }

        $appearance = $this->appearanceResolver->forSurvey($activeSurvey);

        /** @var view-string $view */
        $view = 'simple-surveys::widget';

        return response()
            ->view($view, [
                'activeSurvey' => $activeSurvey,
                'submitUrl' => route('simple-surveys.responses.store', $activeSurvey->key()),
                'dismissUrl' => route('simple-surveys.dismiss', $activeSurvey->key()),
                'openUrl' => route('simple-surveys.open', $activeSurvey->key()),
                'impressionUrl' => config('simple-surveys.tracking.impressions', true)
                    ? route('simple-surveys.impression', $activeSurvey->key())
                    : null,
                'position' => $appearance->resolvedPosition($defaultPosition)->value,
                'theme' => $appearance->resolvedTheme($defaultTheme)->value,
                'inlineStyle' => $appearance->toInlineStyle(),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    private function resolveActiveSurvey(Authenticatable $user, ?string $surveyKey, ?PageContext $context): ?Survey
    {
        if ($surveyKey !== null) {
            $candidate = $this->registry->find($surveyKey);

            if ($candidate !== null && $this->eligibility->isEligible($candidate, $user, context: $context)) {
                return $candidate;
            }

            return null;
        }

        return $this->eligibility->nextFor($user, context: $context);
    }
}
