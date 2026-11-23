<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Enums;

enum ResponseStatus: string
{
    case Completed = 'completed';
    case Dismissed = 'dismissed';
}
