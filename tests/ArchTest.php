<?php

declare(strict_types=1);

arch('strict types are enforced')
    ->expect('DanielCristea\SimpleSurveys')
    ->toUseStrictTypes();

arch('no debugging calls remain')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->each->not->toBeUsed();
