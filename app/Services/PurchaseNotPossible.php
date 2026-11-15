<?php

namespace App\Services;

use RuntimeException;

/**
 * A purchase order was asked to do something it cannot — receive more than
 * was ordered, be edited after it went to the supplier, be cancelled when
 * part of it has already arrived. The message is written for the person at
 * the screen.
 */
class PurchaseNotPossible extends RuntimeException
{
}
