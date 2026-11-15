<?php

namespace App\Services;

use RuntimeException;

/**
 * The gateway would not send the money back.
 *
 * Carries a message written for the person at the screen, because the only
 * sensible response is for a human to look at the payment in Razorpay. A
 * refund must never be recorded as done when it was not.
 */
class RefundFailed extends RuntimeException
{
}
