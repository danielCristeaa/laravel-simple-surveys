<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Enums;

enum SurveyStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Closed = 'closed';
}
