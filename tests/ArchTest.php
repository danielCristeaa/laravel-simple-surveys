<?php

declare(strict_types=1);

arch('strict types are enforced')
    ->expect('DanielCristea\SimpleSurveys')
    ->toUseStrictTypes();

arch('no debugging calls remain')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->each->not->toBeUsed();

arch('enums are valid php enums')
    ->expect('DanielCristea\SimpleSurveys\Enums')
    ->toBeEnums();

arch('contracts are interfaces')
    ->expect('DanielCristea\SimpleSurveys\Contracts')
    ->toBeInterfaces();

arch('services and controllers are final')
    ->expect([
        'DanielCristea\SimpleSurveys\Services',
        'DanielCristea\SimpleSurveys\Http\Controllers',
    ])
    ->toBeFinal();
