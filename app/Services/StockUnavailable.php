<?php

namespace App\Services;

use RuntimeException;

/**
 * Thrown when stock cannot be held for an order. Carries one message per
 * problem line so the cart can show exactly what went wrong.
 */
class StockUnavailable extends RuntimeException
{
    /**
     * @param  array<int, string>  $problems
     */
    public function __construct(public array $problems = [])
    {
        parent::__construct($problems[0] ?? 'Some items are no longer available.');
    }
}
