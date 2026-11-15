<?php

namespace App\Services;

use RuntimeException;

/**
 * A review was asked to do something its state does not allow — featured on
 * the homepage before anybody approved it, say. The message is written for
 * the person at the screen.
 */
class ReviewNotPublishable extends RuntimeException
{
}
