<?php

namespace App\Services;

use RuntimeException;

/**
 * A consignment was asked for that would not add up — more units than the
 * order has left to send, or no units at all. The message is written for the
 * person at the screen.
 */
class ShipmentNotPossible extends RuntimeException
{
}
