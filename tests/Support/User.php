<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class User extends Authenticatable
{
    /** @var list<string> */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
        ];
    }
}
