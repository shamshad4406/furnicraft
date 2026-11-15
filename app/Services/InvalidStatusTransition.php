<?php

namespace App\Services;

use RuntimeException;

/**
 * An order was asked to move somewhere it cannot go from where it is.
 *
 * The message is written for the person who clicked, not for a log file, so
 * controllers can show it as-is.
 */
class InvalidStatusTransition extends RuntimeException
{
}
