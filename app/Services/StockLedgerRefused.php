<?php

namespace App\Services;

use RuntimeException;

/**
 * A stock movement that would not make sense — a count of nothing, a
 * negative shelf, a kind of movement that does not exist. The message is
 * written for the person at the screen.
 */
class StockLedgerRefused extends RuntimeException
{
}
