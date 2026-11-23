<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Tests\Support;

use Illuminate\Database\Eloquent\Model;

final class Order extends Model
{
    /** @var list<string> */
    protected $guarded = [];
}
