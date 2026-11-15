<?php

namespace App\Services;

use RuntimeException;

/**
 * A quotation was asked to do something it is not in a state to do — sent
 * with no lines, converted before it was accepted. The message is written
 * for the person at the screen.
 */
class QuotationNotReady extends RuntimeException
{
}
