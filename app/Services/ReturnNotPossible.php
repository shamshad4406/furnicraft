<?php

namespace App\Services;

use RuntimeException;

/**
 * A return was asked for that the order cannot support — more units than were
 * bought, an order that never arrived, or nothing selected at all. The
 * message is written for the person who asked.
 */
class ReturnNotPossible extends RuntimeException
{
}
