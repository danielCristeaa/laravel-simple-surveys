<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers;

use DanielCristea\SimpleSurveys\PageContext;
use DanielCristea\SimpleSurveys\Services\SurveyEligibilityResolver;
use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use DanielCristea\SimpleSurveys\Services\SurveySerializer;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EligibleSurveyController
{
    public function __construct(
        private readonly SurveyRegistry $registry,
        private readonly SurveyEligibilityResolver $eligibility,
        private readonly SurveySerializer $serializer,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $guard = config('simple-surveys.guard');
        $user = $request->user(is_string($guard) ? $guard : null);

        if (! $user instanceof Authenticatable) {
            return (new JsonResponse(['survey' => null]))
                ->header('Cache-Control', 'no-store, private');
        }

        $surveyKey = $request->query('survey');
        $urlParam = $request->query('url');
        $context = is_string($urlParam) && $urlParam !== ''
            ? PageContext::fromPath($urlParam)
            : null;

        $activeSurvey = $this->resolveActiveSurvey(
            $user,
            is_string($surveyKey) && $surveyKey !== '' ? $surveyKey : null,
            $context,
        );

        return (new JsonResponse([
            'survey' => $activeSurvey !== null ? $this->serializer->toArray($activeSurvey) : null,
        ]))->header('Cache-Control', 'no-store, private');
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
